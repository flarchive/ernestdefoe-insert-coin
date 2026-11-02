<?php

namespace ErnestDefoe\InsertCoin\Api;

use ErnestDefoe\InsertCoin\Arcade;
use ErnestDefoe\InsertCoin\Model\Category;
use ErnestDefoe\InsertCoin\Model\Game;
use ErnestDefoe\InsertCoin\Model\Score;
use ErnestDefoe\InsertCoin\Presenter;
use ErnestDefoe\InsertCoin\StarterGames;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The admin page's API (admins only), by route:
 *
 *   GET    /insert-coin/manage                     every game, the categories, the queue size
 *   POST   /insert-coin/manage/games               add a game played from a URL on another site
 *   PATCH  /insert-coin/manage/games/{id}          edit a game
 *   DELETE /insert-coin/manage/games/{id}          delete a game, its scores and its files
 *   POST   /insert-coin/manage/games/order         {ids: [...]} the order of the grid
 *   POST   /insert-coin/manage/categories          add a category
 *   PATCH  /insert-coin/manage/categories/{id}     rename it
 *   DELETE /insert-coin/manage/categories/{id}     delete it (its games stay, uncategorised)
 *   POST   /insert-coin/manage/categories/order    {ids: [...]}
 *   POST   /insert-coin/manage/starters            put back any starter game that was deleted
 */
class ManageController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(
        protected Presenter $presenter,
        protected Arcade $arcade,
        protected TranslatorInterface $translator,
        protected UrlGenerator $url,
        protected ConnectionInterface $db,
        protected Factory $filesystems
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();
        $body = $this->body($request);

        switch ((string) $request->getAttribute('routeName')) {
            case 'insert-coin.manage':
                return $this->index();
            case 'insert-coin.manage.games.create':
                $game = new Game();
                $game->source = 'url';
                $game->enabled = false;
                $game->position = (int) Game::query()->max('position') + 1;
                $this->fill($game, $body + ['scoreOrder' => 'high']);

                return new JsonResponse(['data' => $this->presenter->game($game, true)], 201);
            case 'insert-coin.manage.games.update':
                /** @var Game $game */
                $game = Game::query()->findOrFail($this->routeId($request));
                $this->fill($game, $body);

                return new JsonResponse(['data' => $this->presenter->game($game->load('champion'), true)]);
            case 'insert-coin.manage.games.delete':
                $this->delete(Game::query()->findOrFail($this->routeId($request)));
                break;
            case 'insert-coin.manage.games.order':
                $this->order(Game::query(), $body['ids'] ?? []);
                break;
            case 'insert-coin.manage.categories.create':
            case 'insert-coin.manage.categories.update':
                $category = $request->getMethod() === 'POST' ? new Category() : Category::query()->findOrFail($this->routeId($request));
                $name = trim((string) ($body['name'] ?? ''));

                if ($name === '' || mb_strlen($name) > 100) {
                    throw new ValidationException(['name' => $this->translator->trans('ernestdefoe-insert-coin.api.category_name')]);
                }

                $category->name = $name;
                $category->icon = Str::limit(trim((string) ($body['icon'] ?? '')), 100, '') ?: null;

                if (! $category->exists) {
                    $category->slug = $this->uniqueSlug(Category::query(), Str::slug($name) ?: 'category');
                    $category->position = (int) Category::query()->max('position') + 1;
                }

                $category->save();

                return new JsonResponse(['data' => ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug, 'icon' => $category->icon]]);
            case 'insert-coin.manage.categories.delete':
                Category::query()->findOrFail($this->routeId($request))->delete();
                break;
            case 'insert-coin.manage.categories.order':
                $this->order(Category::query(), $body['ids'] ?? []);
                break;
            case 'insert-coin.manage.starters':
                return new JsonResponse(['data' => ['added' => StarterGames::install($this->db)]]);
        }

        return new EmptyResponse(204);
    }

    protected function index(): ResponseInterface
    {
        return new JsonResponse(['data' => [
            'games' => Game::query()->with('champion')->orderBy('position')->orderBy('title')->get()
                ->map(fn (Game $game) => $this->presenter->game($game, true))->all(),
            'categories' => Category::query()->orderBy('position')->orderBy('name')->get()
                ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'icon' => $c->icon])->all(),
            'queue' => Score::query()->where('status', '!=', Score::APPROVED)->count(),
        ]]);
    }

    /** @param array<string, mixed> $body */
    protected function fill(Game $game, array $body): void
    {
        $errors = [];
        $text = fn (string $key, int $max) => Str::limit(trim((string) $body[$key]), $max, '');

        if (array_key_exists('title', $body)) {
            $game->title = $text('title', 150);
        }

        if (! $game->title) {
            $errors['title'] = 'title';
        }

        if (array_key_exists('slug', $body) || ! $game->slug) {
            $slug = Str::slug((string) ($body['slug'] ?? '')) ?: Str::slug((string) $game->title);
            $taken = Game::query()->where('slug', $slug)->when($game->exists, fn ($q) => $q->whereKeyNot($game->id))->exists();

            if ($slug === '' || ctype_digit($slug) || $taken || in_array($slug, Game::RESERVED, true)) {
                $errors['slug'] = 'slug';
            }

            $game->slug = Str::limit($slug, 100, '');
        }

        if (array_key_exists('description', $body)) {
            $game->description = $text('description', 2000) ?: null;
        }

        if (array_key_exists('categoryId', $body)) {
            $game->category_id = $body['categoryId'] ? (Category::query()->whereKey((int) $body['categoryId'])->value('id') ?: null) : null;
        }

        foreach (['width' => [1, 4000], 'height' => [1, 4000], 'minSeconds' => [0, 3600], 'maxSeconds' => [10, 86400]] as $key => [$min, $max]) {
            if (array_key_exists($key, $body)) {
                $game->{Str::snake($key)} = max($min, min($max, (int) $body[$key]));
            }
        }

        foreach (['mobile', 'enabled', 'crownApproval'] as $key) {
            if (array_key_exists($key, $body)) {
                $game->{Str::snake($key)} = (bool) $body[$key];
            }
        }

        if (array_key_exists('scoreLabel', $body)) {
            $game->score_label = $text('scoreLabel', 30) ?: null;
        }

        if (array_key_exists('scoreCeiling', $body)) {
            $game->score_ceiling = is_numeric($body['scoreCeiling']) && $body['scoreCeiling'] > 0 ? (float) $body['scoreCeiling'] : null;
        }

        $flipped = false;
        if (array_key_exists('scoreOrder', $body)) {
            $order = $body['scoreOrder'] === 'low' ? 'low' : 'high';
            $flipped = $game->exists && $game->score_order !== $order;
            $game->score_order = $order;
        }

        if ($game->source === 'url' && (array_key_exists('url', $body) || ! $game->url)) {
            $url = trim((string) ($body['url'] ?? ''));
            $host = parse_url($url, PHP_URL_HOST);

            // 🚨 Another site only. A URL on the forum's own host would be
            // framed with that site's origin, i.e. the forum's.
            if (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('~^https?://~i', $url) || ! $host
                || strcasecmp($host, (string) parse_url($this->url->to('forum')->base(), PHP_URL_HOST)) === 0 || strlen($url) > 1000) {
                $errors['url'] = 'url';
            }

            $game->url = $url;
        }

        if (array_key_exists('thumbnailUrl', $body) && $game->source === 'url') {
            $thumb = trim((string) $body['thumbnailUrl']);
            $game->thumbnail = $thumb !== '' && filter_var($thumb, FILTER_VALIDATE_URL) && preg_match('~^https://~i', $thumb) ? Str::limit($thumb, 255, '') : $game->thumbnail;
        }

        if ($errors) {
            throw new ValidationException(array_map(fn ($key) => $this->translator->trans('ernestdefoe-insert-coin.api.field.'.$key), $errors));
        }

        $game->save();

        // Lower-is-better turned around: every best and the crown mean the opposite now.
        if ($flipped) {
            $this->arcade->rebuildGame($game);
        }
    }

    protected function delete(Game $game): void
    {
        $assets = $this->filesystems->disk('flarum-assets');
        $champion = $game->champion_user_id;
        $game->delete();
        $this->arcade->recount([$champion]);

        if ($game->source !== 'bundled' && $game->path && preg_match('/^[a-z0-9-]+$/', $game->path)) {
            $assets->deleteDirectory('insert-coin/packs/'.$game->path);
        }

        if ($game->thumbnail && str_starts_with($game->thumbnail, 'insert-coin/thumbs/')) {
            $assets->delete($game->thumbnail);
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<Game>|\Illuminate\Database\Eloquent\Builder<Category> $query
     * @param mixed $ids
     */
    protected function order($query, mixed $ids): void
    {
        $this->db->transaction(function () use ($query, $ids) {
            foreach (array_values(array_filter((array) $ids, 'is_numeric')) as $position => $id) {
                (clone $query)->whereKey((int) $id)->update(['position' => $position]);
            }
        });
    }

    /** @param \Illuminate\Database\Eloquent\Builder<Category> $query */
    protected function uniqueSlug($query, string $slug): string
    {
        $try = $slug;

        for ($n = 2; (clone $query)->where('slug', $try)->exists(); $n++) {
            $try = $slug.'-'.$n;
        }

        return $try;
    }
}
