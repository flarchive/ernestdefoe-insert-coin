<?php

namespace ErnestDefoe\InsertCoin\Api;

use ErnestDefoe\InsertCoin\Access;
use ErnestDefoe\InsertCoin\Model\Category;
use ErnestDefoe\InsertCoin\Model\Crown;
use ErnestDefoe\InsertCoin\Model\Game;
use ErnestDefoe\InsertCoin\Model\PlaySession;
use ErnestDefoe\InsertCoin\Presenter;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/insert-coin — everything the arcade home shows, in one request and
 * a fixed number of queries: categories, games with their champions, what you
 * played lately, the Hall of Fame and the latest crowns.
 */
class HomeController implements RequestHandlerInterface
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

        $games = Game::query()->visibleTo($actor)->with('champion')->orderBy('position')->orderBy('title')->get();
        $gameIds = $games->pluck('id')->all();

        $recent = $actor->isGuest() ? [] : PlaySession::query()
            ->where('user_id', $actor->id)->whereIn('game_id', $gameIds)
            ->groupBy('game_id')->select('game_id')->selectRaw('max(started_at) as last')
            ->orderByDesc('last')->limit(6)->pluck('game_id')->map(fn ($id) => (int) $id)->all();

        $crowns = Crown::query()->whereIn('game_id', $gameIds)->with(['user', 'previous'])->latest('created_at')->limit(8)->get();
        $hall = User::query()->where('insertcoin_crowns', '>', 0)->orderByDesc('insertcoin_crowns')->orderBy('id')->limit(10)->get();

        return new JsonResponse(['data' => [
            'categories' => Category::query()->orderBy('position')->orderBy('name')->get()
                ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'icon' => $c->icon])->all(),
            'games' => $games->map(fn (Game $game) => $this->presenter->game($game))->all(),
            'recent' => $recent,
            'hallOfFame' => $hall->map(fn (User $user) => $this->presenter->person($user))->all(),
            'recentCrowns' => $crowns->map(fn (Crown $crown) => [
                'gameId' => $crown->game_id,
                'user' => $this->presenter->person($crown->user),
                'previous' => $this->presenter->person($crown->previous),
                'score' => $crown->score,
                'at' => $this->at($crown->created_at),
            ])->all(),
        ]]);
    }
}
