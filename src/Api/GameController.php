<?php

namespace ErnestDefoe\InsertCoin\Api;

use ErnestDefoe\InsertCoin\Access;
use ErnestDefoe\InsertCoin\Boards;
use ErnestDefoe\InsertCoin\Model\Game;
use ErnestDefoe\InsertCoin\Presenter;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/insert-coin/games/{slug} — a game's cabinet: the game, its
 * category, the all-time board and where you stand on it.
 */
class GameController implements RequestHandlerInterface
{
    use Concerns;
    use PresentsBoards;

    public function __construct(protected Presenter $presenter, protected Boards $boards)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $slug = (string) Arr::get($request->getAttributes(), 'routeParameters.slug');

        /** @var Game $game */
        $game = Game::query()->visibleTo($actor)->with(['champion', 'category'])
            ->where(fn ($q) => $q->where('slug', $slug)->when(ctype_digit($slug) && strlen($slug) <= 10, fn ($q) => $q->orWhere('id', (int) $slug)))
            ->firstOrFail();

        $period = Boards::period(Arr::get($request->getQueryParams(), 'period'));

        return new JsonResponse(['data' => $this->presenter->game($game) + [
            'category' => $game->category ? ['id' => $game->category->id, 'name' => $game->category->name, 'slug' => $game->category->slug] : null,
            'canSubmit' => Access::canSubmit($actor),
            'banned' => Access::banned($actor),
            'board' => $this->board($game, $period, 0, $actor),
        ]]);
    }
}
