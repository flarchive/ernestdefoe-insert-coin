<?php

namespace ErnestDefoe\InsertCoin\Api;

use ErnestDefoe\InsertCoin\Model\Game;
use ErnestDefoe\InsertCoin\Presenter;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context;

/**
 * A game as a JSON:API resource: what a "dethroned" notification's subject
 * and a realtime push carry. The arcade's own pages read the plain JSON
 * controllers instead.
 *
 * @extends AbstractDatabaseResource<Game>
 */
class GameResource extends AbstractDatabaseResource
{
    public function __construct(protected Presenter $presenter)
    {
    }

    public function type(): string
    {
        return 'insert-coin-games';
    }

    public function model(): string
    {
        return Game::class;
    }

    public function scope(Builder $query, Context $context): void
    {
        $query->visibleTo($context->getActor());
    }

    public function endpoints(): array
    {
        return [Endpoint\Show::make()];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('title'),
            Schema\Str::make('slug'),
            Schema\Str::make('thumbnailUrl')->nullable()->get(fn (Game $game) => $this->presenter->thumbnail($game)),
            Schema\Str::make('scoreLabel')->nullable()->property('score_label'),
        ];
    }
}
