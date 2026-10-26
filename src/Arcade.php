<?php

namespace ErnestDefoe\InsertCoin;

use Carbon\Carbon;
use ErnestDefoe\InsertCoin\Event\CrownTaken;
use ErnestDefoe\InsertCoin\Event\ScoreAccepted;
use ErnestDefoe\InsertCoin\Model\Best;
use ErnestDefoe\InsertCoin\Model\Crown;
use ErnestDefoe\InsertCoin\Model\Game;
use ErnestDefoe\InsertCoin\Model\PlaySession;
use ErnestDefoe\InsertCoin\Model\Score;
use ErnestDefoe\InsertCoin\Notification\DethronedBlueprint;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\Notification\NotificationSyncer;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

/**
 * Play sessions, the rules a score must pass, personal bests and crowns.
 *
 * A run starts with a session the server signs; the score for it is accepted
 * once, only from the member who started it, only between the game's minimum
 * and maximum play time and only under its ceiling. A score far beyond the
 * current champion's is held as an outlier, and on games that ask for it a
 * score that would take the crown waits for a moderator. Only approved scores
 * count anywhere.
 *
 * The champion is the top of the game's bests, kept on the game row and
 * changed under a lock on that row, so two runs finishing together can't both
 * take the crown.
 */
class Arcade
{
    public const PREFIX = 'ernestdefoe-insert-coin.';

    /** Runs one member may start in ten minutes, across all games. */
    public const SESSIONS_PER_10_MIN = 120;

    /** Scores one member may submit in a minute. */
    public const SCORES_PER_MINUTE = 20;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected NotificationSyncer $notifications,
        protected Dispatcher $events,
        protected ConnectionInterface $db,
        protected TranslatorInterface $translator
    ) {
    }

    // ---------------------------------------------------------------- sessions

    public function start(Game $game, User $actor): PlaySession
    {
        if (PlaySession::query()->where('user_id', $actor->id)->where('started_at', '>', Carbon::now()->subMinutes(10))->count() >= self::SESSIONS_PER_10_MIN) {
            $this->refuse('slow_down');
        }

        $session = new PlaySession();
        $session->game_id = $game->id;
        $session->user_id = $actor->id;
        $session->started_at = Carbon::now();
        $session->save();

        Game::query()->whereKey($game->id)->increment('plays_count');

        return $session;
    }

    /** The token for a session: an HMAC of the row, so nothing secret is stored. */
    public function token(PlaySession $session): string
    {
        return hash_hmac('sha256', implode('|', [$session->id, $session->game_id, $session->user_id, $session->started_at->getTimestamp()]), $this->secret());
    }

    public function expiresAt(PlaySession $session, Game $game): Carbon
    {
        return $session->started_at->copy()->addSeconds($game->max_seconds);
    }

    protected function secret(): string
    {
        $secret = (string) $this->settings->get(self::PREFIX.'secret');

        if (strlen($secret) < 32) {
            $secret = Str::random(64);
            $this->settings->set(self::PREFIX.'secret', $secret);
        }

        return $secret;
    }

    // ------------------------------------------------------------------ scores

    /**
     * Accept the score for a run, or refuse it with the reason.
     *
     * @return array{score: Score, personalBest: bool, crown: bool, previous: ?User}
     */
    public function submit(PlaySession $session, string $token, mixed $value, User $actor): array
    {
        if ($session->user_id !== $actor->id || ! hash_equals($this->token($session), $token)) {
            $this->refuse('bad_session');
        }

        if ($session->submitted_at !== null) {
            $this->refuse('already_submitted');
        }

        /** @var Game $game */
        $game = Game::query()->findOrFail($session->game_id);
        $now = Carbon::now();
        $elapsed = max(0, $now->getTimestamp() - $session->started_at->getTimestamp());

        if (! is_numeric($value) || ! is_finite($score = (float) $value) || $score < 0) {
            $this->refuse('bad_score');
        }

        if ($elapsed > $game->max_seconds) {
            $this->refuse('expired');
        }

        if ($elapsed < $game->min_seconds) {
            $this->refuse('too_quick');
        }

        if ($game->score_ceiling !== null && $score > $game->score_ceiling) {
            $this->refuse('over_ceiling');
        }

        if (Score::query()->where('user_id', $actor->id)->where('created_at', '>', $now->copy()->subMinute())->count() >= self::SCORES_PER_MINUTE) {
            $this->refuse('slow_down');
        }

        $result = $this->db->transaction(function () use ($session, $game, $actor, $score, $elapsed, $now) {
            // One use, even with two submissions racing: only one of them
            // changes the row.
            if (! PlaySession::query()->whereKey($session->id)->whereNull('submitted_at')->update(['submitted_at' => $now])) {
                $this->refuse('already_submitted');
            }

            /** @var Game $game */
            $game = Game::query()->lockForUpdate()->findOrFail($game->id);

            $row = new Score();
            $row->game_id = $game->id;
            $row->user_id = $actor->id;
            $row->session_id = $session->id;
            $row->score = $score;
            $row->duration = $elapsed;
            $row->created_at = $now;
            [$row->status, $row->reason] = $this->judge($game, $score);
            $row->save();

            $outcome = $row->status === Score::APPROVED
                ? $this->record($game, $row)
                : ['personalBest' => false, 'crown' => false, 'previous' => null, 'crownRow' => null];

            return ['score' => $row, 'game' => $game] + $outcome;
        });

        $this->announce($result['game'], $result['score'], $actor, $result['previous'], $result['crownRow']);

        return [
            'score' => $result['score'],
            'personalBest' => $result['personalBest'],
            'crown' => $result['crown'],
            'previous' => $result['previous'],
        ];
    }

    /**
     * Approved, an outlier, or a crown waiting for approval.
     *
     * @return array{0: string, 1: ?string}
     */
    protected function judge(Game $game, float $score): array
    {
        $top = $game->champion_score;

        if ($score <= 0) {
            return [Score::APPROVED, null];
        }

        if ($top !== null && $top > 0 && $game->beats($score, $top)) {
            $factor = max(1.1, (float) ($this->settings->get(self::PREFIX.'outlier_factor') ?: 3));
            $far = $game->lowWins() ? $score * $factor < $top : $score > $top * $factor;

            if ($far && Best::query()->where('game_id', $game->id)->count() >= max(1, (int) ($this->settings->get(self::PREFIX.'outlier_players') ?: 5))) {
                return [Score::FLAGGED, 'outlier'];
            }
        }

        if ($game->crown_approval && $game->beats($score, $top)) {
            return [Score::PENDING, 'crown'];
        }

        return [Score::APPROVED, null];
    }

    /**
     * Count an approved score: the member's best, and the crown if it beats
     * the champion's. Runs inside the transaction holding the game's lock.
     *
     * @return array{personalBest: bool, crown: bool, previous: ?User, crownRow: ?Crown}
     */
    protected function record(Game $game, Score $score): array
    {
        $none = ['personalBest' => false, 'crown' => false, 'previous' => null, 'crownRow' => null];

        // Nothing scored is a run played, not a record: no best, no crown.
        if ($score->score <= 0) {
            return $none;
        }

        $best = Best::query()->where('game_id', $game->id)->where('user_id', $score->user_id)->first();

        if ($best && ! $game->beats($score->score, $best->score)) {
            return $none;
        }

        $this->putBest($game->id, $score->user_id, $score);

        if (! $game->beats($score->score, $game->champion_score)) {
            return ['personalBest' => true] + $none;
        }

        // The champion beating their own record keeps the crown they hold.
        if ($game->champion_user_id === $score->user_id) {
            $game->champion_score = $score->score;
            $game->save();

            return ['personalBest' => true] + $none;
        }

        $previousId = $game->champion_user_id;
        $game->champion_user_id = $score->user_id;
        $game->champion_score = $score->score;
        $game->champion_since = $score->created_at;
        $game->save();

        $crown = new Crown();
        $crown->game_id = $game->id;
        $crown->user_id = $score->user_id;
        $crown->previous_user_id = $previousId;
        $crown->score = $score->score;
        $crown->created_at = $score->created_at;
        $crown->save();

        $this->recount(array_filter([$score->user_id, $previousId]));

        return [
            'personalBest' => true,
            'crown' => true,
            'previous' => $previousId ? User::query()->find($previousId) : null,
            'crownRow' => $crown,
        ];
    }

    protected function putBest(int $gameId, int $userId, Score $score): void
    {
        $values = ['score_id' => $score->id, 'score' => $score->score, 'achieved_at' => $score->created_at];
        $query = Best::query()->where('game_id', $gameId)->where('user_id', $userId);

        if (! $query->update($values)) {
            Best::query()->insert($values + ['game_id' => $gameId, 'user_id' => $userId]);
        }
    }

    /** Tell the old champion, and let integrations (points, realtime) know. */
    protected function announce(Game $game, Score $score, User $actor, ?User $previous, ?Crown $crown): void
    {
        if ($score->status !== Score::APPROVED) {
            return;
        }

        $this->events->dispatch(new ScoreAccepted($game, $score, $actor));

        if ($crown) {
            $this->events->dispatch(new CrownTaken($game, $actor, $previous, $crown));

            if ($previous && $previous->id !== $actor->id) {
                $this->notifications->sync(new DethronedBlueprint($game, $actor, $score->score), [$previous]);
            }
        }
    }

    // -------------------------------------------------------------- moderation

    /** Count a held score after all, as of when it was played. */
    public function approve(Score $score): void
    {
        $this->db->transaction(function () use ($score) {
            /** @var Game $game */
            $game = Game::query()->lockForUpdate()->findOrFail($score->game_id);
            $score->status = Score::APPROVED;
            $score->reason = null;
            $score->save();

            $outcome = $this->record($game, $score);

            if ($outcome['crown']) {
                /** @var User $user */
                $user = User::query()->findOrFail($score->user_id);
                $this->db->afterCommit(fn () => $this->announce($game, $score, $user, $outcome['previous'], $outcome['crownRow']));
            }
        });
    }

    /** Delete one score, and put the member's best and the crown right. */
    public function reject(Score $score): void
    {
        $this->db->transaction(function () use ($score) {
            $score->delete();
            $this->rebuild($score->game_id, [$score->user_id]);
        });
    }

    /** Wipe a game's scores, or one member's scores on it. */
    public function wipeGame(Game $game, ?int $userId = null): void
    {
        $this->db->transaction(function () use ($game, $userId) {
            foreach ([Score::query(), Best::query(), Crown::query()] as $query) {
                $query->where('game_id', $game->id)->when($userId, fn ($q) => $q->where('user_id', $userId))->delete();
            }

            $this->recrown($game->id);
        });
    }

    /** Wipe a member's scores on every game. */
    public function wipeUser(int $userId): void
    {
        $this->db->transaction(function () use ($userId) {
            $gameIds = Best::query()->where('user_id', $userId)->pluck('game_id')->all();

            foreach ([Score::query(), Best::query(), Crown::query()] as $query) {
                $query->where('user_id', $userId)->delete();
            }

            foreach ($gameIds as $gameId) {
                $this->recrown($gameId);
            }

            $this->recount([$userId]);
        });
    }

    /** Rebuild every best on a game, as when it switches between high and low wins. */
    public function rebuildGame(Game $game): void
    {
        $this->db->transaction(function () use ($game) {
            Best::query()->where('game_id', $game->id)->delete();
            $users = Score::query()->where('game_id', $game->id)->where('status', Score::APPROVED)->where('score', '>', 0)->distinct()->pluck('user_id')->all();
            $this->rebuild($game->id, $users);
        });
    }

    /**
     * Rebuild members' bests on a game from their approved scores, then the crown.
     *
     * @param int[] $userIds
     */
    protected function rebuild(int $gameId, array $userIds): void
    {
        /** @var Game $game */
        $game = Game::query()->lockForUpdate()->findOrFail($gameId);

        foreach ($userIds as $userId) {
            $top = Score::query()
                ->where('game_id', $gameId)->where('user_id', $userId)->where('status', Score::APPROVED)->where('score', '>', 0)
                ->orderBy('score', $game->direction())->orderBy('created_at')->first();

            $top
                ? $this->putBest($gameId, $userId, $top)
                : Best::query()->where('game_id', $gameId)->where('user_id', $userId)->delete();
        }

        $this->recrown($gameId);
    }

    /**
     * Hand the crown to whoever tops the bests now, silently: a moderator's
     * cleanup isn't a dethroning.
     */
    public function recrown(int $gameId): void
    {
        /** @var Game $game */
        $game = Game::query()->lockForUpdate()->findOrFail($gameId);
        $top = Best::query()->where('game_id', $gameId)->orderBy('score', $game->direction())->orderBy('achieved_at')->first();
        $previous = $game->champion_user_id;

        $game->champion_user_id = $top?->user_id;
        $game->champion_score = $top?->score;
        $game->champion_since = $top ? ($previous === $top->user_id ? $game->champion_since : $top->achieved_at) : null;
        $game->save();

        $this->recount(array_filter([$previous, $top?->user_id]));
    }

    /**
     * Crowns held, recounted for a few members.
     *
     * @param array<int|null> $userIds
     */
    public function recount(array $userIds): void
    {
        foreach (array_unique(array_filter($userIds)) as $id) {
            User::query()->whereKey($id)->update(['insertcoin_crowns' => Game::query()->where('champion_user_id', $id)->count()]);
        }
    }

    /** @return never */
    protected function refuse(string $reason): void
    {
        throw new ValidationException(['score' => $this->translator->trans('ernestdefoe-insert-coin.api.'.$reason)]);
    }
}
