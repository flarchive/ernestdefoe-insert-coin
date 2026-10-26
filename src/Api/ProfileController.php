<?php

namespace ErnestDefoe\InsertCoin\Api;

use ErnestDefoe\InsertCoin\Access;
use ErnestDefoe\InsertCoin\Model\Best;
use ErnestDefoe\InsertCoin\Model\Game;
use ErnestDefoe\InsertCoin\Presenter;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** GET /api/insert-coin/users/{id} — a member's arcade card: crowns held and best scores. */
class ProfileController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(protected Presenter $presenter)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        if (! Access::canPlay($actor)) {
            throw new PermissionDeniedException();
        }

        /** @var User $user */
        $user = User::query()->whereVisibleTo($actor)->findOrFail($this->routeId($request));
        $visible = Game::query()->visibleTo($actor)->select('id');

        $crowns = Game::query()->visibleTo($actor)->where('champion_user_id', $user->id)->orderByDesc('champion_since')->get();
        $bests = Best::query()->where('user_id', $user->id)->whereIn('game_id', $visible)->with('game')->orderByDesc('achieved_at')->limit(24)->get();

        return new JsonResponse(['data' => [
            'user' => $this->presenter->person($user),
            'played' => Best::query()->where('user_id', $user->id)->whereIn('game_id', $visible)->count(),
            'banned' => Access::canModerate($actor) && Access::banned($user),
            'crowns' => $crowns->map(fn (Game $game) => $this->presenter->game($game) + ['crownScore' => $game->champion_score])->all(),
            'bests' => $bests->filter(fn (Best $best) => $best->game !== null)->map(fn (Best $best) => [
                'game' => ['id' => $best->game->id, 'slug' => $best->game->slug, 'title' => $best->game->title, 'thumbnailUrl' => $this->presenter->thumbnail($best->game), 'scoreLabel' => $best->game->score_label],
                'score' => $best->score,
                'champion' => $best->game->champion_user_id === $user->id,
                'at' => $this->at($best->achieved_at),
            ])->values()->all(),
        ]]);
    }
}
