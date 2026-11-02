<?php

namespace ErnestDefoe\InsertCoin\Api;

use ErnestDefoe\InsertCoin\Boards;
use ErnestDefoe\InsertCoin\Presenter;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** GET /api/insert-coin/games/{id}/scores?period=all|month|week&offset= */
class BoardController implements RequestHandlerInterface
{
    use Concerns;
    use PresentsBoards;

    public function __construct(protected Presenter $presenter, protected Boards $boards)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $game = $this->visibleGame($request, $actor);
        $query = $request->getQueryParams();
        $limit = max(1, min(50, (int) Arr::get($query, 'limit', 10)));
        $offset = max(0, min(100000, (int) Arr::get($query, 'offset', 0)));

        return new JsonResponse(['data' => $this->board($game, Boards::period(Arr::get($query, 'period')), $offset, $actor, $limit)]);
    }
}
