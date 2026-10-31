<?php

namespace ErnestDefoe\InsertCoin\Api;

use ErnestDefoe\InsertCoin\Access;
use ErnestDefoe\InsertCoin\Model\Crown;
use ErnestDefoe\InsertCoin\Model\Game;
use ErnestDefoe\InsertCoin\Presenter;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** GET /api/insert-coin/crowns?limit= — the latest crowns, for the widget. Three queries. */
class CrownsController implements RequestHandlerInterface
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

        $limit = max(1, min(20, (int) Arr::get($request->getQueryParams(), 'limit', 5)));
        $crowns = Crown::query()->whereIn('game_id', Game::query()->visibleTo($actor)->select('id'))
            ->with(['user', 'game'])->latest('created_at')->limit($limit)->get();

        return new JsonResponse(['data' => $crowns->filter(fn (Crown $c) => $c->user && $c->game)->map(fn (Crown $crown) => [
            'user' => $this->presenter->person($crown->user),
            'game' => ['slug' => $crown->game->slug, 'title' => $crown->game->title, 'thumbnailUrl' => $this->presenter->thumbnail($crown->game), 'scoreLabel' => $crown->game->score_label],
            'score' => $crown->score,
            'at' => $this->at($crown->created_at),
        ])->values()->all()]);
    }
}
