<?php

namespace ErnestDefoe\InsertCoin\Api;

use Carbon\Carbon;
use ErnestDefoe\InsertCoin\Access;
use ErnestDefoe\InsertCoin\Arcade;
use ErnestDefoe\InsertCoin\Model\Game;
use ErnestDefoe\InsertCoin\Model\Score;
use ErnestDefoe\InsertCoin\Presenter;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Looking after the boards (insertCoin.moderate), by route:
 *
 *   GET    /insert-coin/queue                held scores: outliers and crowns awaiting approval
 *   POST   /insert-coin/scores/{id}/approve  count a held score
 *   DELETE /insert-coin/scores/{id}          delete one score
 *   DELETE /insert-coin/games/{id}/scores    wipe a game (?user= for one member's scores on it)
 *   DELETE /insert-coin/users/{id}/scores    wipe a member's scores everywhere
 *   POST   /insert-coin/users/{id}/ban       ban from the arcade ({wipe: true} wipes too)
 *   DELETE /insert-coin/users/{id}/ban       lift the ban
 */
class ModerationController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(protected Arcade $arcade, protected Presenter $presenter)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        if (! Access::canModerate($actor)) {
            throw new PermissionDeniedException();
        }

        $route = (string) $request->getAttribute('routeName');

        switch ($route) {
            case 'insert-coin.queue':
                return $this->queue($request);
            case 'insert-coin.scores.approve':
                $this->arcade->approve($this->heldOrAny($request));
                break;
            case 'insert-coin.scores.delete':
                $this->arcade->reject($this->heldOrAny($request));
                break;
            case 'insert-coin.games.wipe':
                /** @var Game $game */
                $game = Game::query()->findOrFail($this->routeId($request));
                $user = (int) Arr::get($request->getQueryParams(), 'user', 0);
                $this->arcade->wipeGame($game, $user ?: null);
                break;
            case 'insert-coin.users.wipe':
                $this->arcade->wipeUser($this->member($request, $actor)->id);
                break;
            case 'insert-coin.users.ban':
                $user = $this->member($request, $actor);
                $user->setAttribute('insertcoin_banned_at', Carbon::now())->save();

                if (! empty($this->body($request)['wipe'])) {
                    $this->arcade->wipeUser($user->id);
                }
                break;
            case 'insert-coin.users.unban':
                $this->member($request, $actor)->setAttribute('insertcoin_banned_at', null)->save();
                break;
        }

        return new EmptyResponse(204);
    }

    protected function queue(ServerRequestInterface $request): ResponseInterface
    {
        $offset = max(0, (int) Arr::get($request->getQueryParams(), 'offset', 0));
        $scores = Score::query()->where('status', '!=', Score::APPROVED)->with(['user', 'game'])
            ->orderByDesc('created_at')->offset($offset)->limit(21)->get();

        return new JsonResponse([
            'data' => $scores->take(20)->map(fn (Score $score) => [
                'id' => $score->id,
                'score' => $score->score,
                'duration' => $score->duration,
                'status' => $score->status,
                'reason' => $score->reason,
                'at' => $this->at($score->created_at),
                'user' => $this->presenter->person($score->user),
                'game' => $score->game ? [
                    'id' => $score->game->id,
                    'slug' => $score->game->slug,
                    'title' => $score->game->title,
                    'scoreLabel' => $score->game->score_label,
                    'championScore' => $score->game->champion_score,
                ] : null,
            ])->values()->all(),
            'meta' => ['next' => $scores->count() > 20 ? $offset + 20 : null],
        ]);
    }

    protected function heldOrAny(ServerRequestInterface $request): Score
    {
        return Score::query()->findOrFail($this->routeId($request));
    }

    /** A member a moderator may act on: never an admin. */
    protected function member(ServerRequestInterface $request, User $actor): User
    {
        /** @var User $user */
        $user = User::query()->findOrFail($this->routeId($request));

        if ($user->isAdmin() || $user->id === $actor->id) {
            throw new PermissionDeniedException();
        }

        return $user;
    }
}
