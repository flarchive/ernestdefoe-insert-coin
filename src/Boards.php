<?php

namespace ErnestDefoe\InsertCoin;

use Carbon\Carbon;
use ErnestDefoe\InsertCoin\Model\Best;
use ErnestDefoe\InsertCoin\Model\Game;
use ErnestDefoe\InsertCoin\Model\Score;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Leaderboards for all time (the bests table), this month and this week (the
 * approved scores since the period began, best per member). Each board is one
 * query for the rows and one for their members, whatever its length.
 */
class Boards
{
    public const PERIODS = ['all', 'month', 'week'];

    public static function period(mixed $period): string
    {
        return in_array($period, self::PERIODS, true) ? $period : 'all';
    }

    public static function since(string $period): ?Carbon
    {
        return match ($period) {
            'month' => Carbon::now()->startOfMonth(),
            'week' => Carbon::now()->startOfWeek(),
            default => null,
        };
    }

    /**
     * @return array{rows: list<array{rank: int, user: User, score: float, at: Carbon|string}>, next: ?int}
     */
    public function rows(Game $game, string $period, int $offset = 0, int $limit = 10): array
    {
        $dir = $game->direction();

        if ($period === 'all') {
            $found = Best::query()->where('game_id', $game->id)
                ->orderBy('score', $dir)->orderBy('achieved_at')
                ->offset($offset)->limit($limit + 1)
                ->get(['user_id', 'score', 'achieved_at as at']);
        } else {
            $found = $this->periodBests($game, $period)
                ->orderBy('best', $dir)->orderBy('at')
                ->offset($offset)->limit($limit + 1)
                ->get()
                ->each(fn ($row) => $row->setAttribute('score', (float) $row->getAttribute('best')));
        }

        $next = $found->count() > $limit ? $offset + $limit : null;
        $found = $found->take($limit);
        $users = User::query()->whereIn('id', $found->pluck('user_id')->all())->get()->keyBy('id');

        $rows = [];
        foreach ($found->values() as $i => $row) {
            if ($user = $users->get($row->getAttribute('user_id'))) {
                $rows[] = ['rank' => $offset + $i + 1, 'user' => $user, 'score' => (float) $row->getAttribute('score'), 'at' => $row->getAttribute('at')];
            }
        }

        return ['rows' => $rows, 'next' => $next];
    }

    /**
     * A member's best on a board and their place on it.
     *
     * @return array{rank: int, score: float}|null
     */
    public function standing(Game $game, int $userId, string $period): ?array
    {
        $better = $game->lowWins() ? '<' : '>';

        if ($period === 'all') {
            $best = Best::query()->where('game_id', $game->id)->where('user_id', $userId)->first();

            if (! $best) {
                return null;
            }

            $ahead = Best::query()->where('game_id', $game->id)
                ->where(fn ($q) => $q->where('score', $better, $best->score)
                    ->orWhere(fn ($q) => $q->where('score', $best->score)->where('achieved_at', '<', $best->achieved_at)))
                ->count();

            return ['rank' => $ahead + 1, 'score' => $best->score];
        }

        $mine = $this->periodBests($game, $period)->where('user_id', $userId)->first();

        if (! $mine) {
            return null;
        }

        $score = (float) $mine->getAttribute('best');

        // A member's best this period beats yours exactly when one of their
        // scores this period does: a plain WHERE, no HAVING over an aggregate
        // (which SQLite compares to a bound number as text).
        $ahead = Score::query()
            ->where('game_id', $game->id)
            ->where('status', Score::APPROVED)
            ->where('created_at', '>=', self::since($period))
            ->where('score', $better, $score)
            ->distinct()
            ->count('user_id');

        return ['rank' => $ahead + 1, 'score' => $score];
    }

    /** @return Builder<Score> */
    protected function periodBests(Game $game, string $period): Builder
    {
        $aggregate = $game->lowWins() ? 'min' : 'max';

        return Score::query()
            ->where('game_id', $game->id)
            ->where('status', Score::APPROVED)
            ->where('created_at', '>=', self::since($period))
            ->groupBy('user_id')
            ->select('user_id')
            ->selectRaw("$aggregate(score) as best")
            ->selectRaw('min(created_at) as at');
    }
}
