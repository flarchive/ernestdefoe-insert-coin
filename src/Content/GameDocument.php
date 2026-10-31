<?php

namespace ErnestDefoe\InsertCoin\Content;

use ErnestDefoe\InsertCoin\Model\Game;
use Flarum\Frontend\Document;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Psr\Http\Message\ServerRequestInterface;

/** A game page's title and description, for search engines and link previews. */
class GameDocument
{
    public function __invoke(Document $document, ServerRequestInterface $request): void
    {
        $slug = (string) Arr::get($request->getQueryParams(), 'slug');

        /** @var Game|null $game */
        $game = Game::query()->visibleTo(RequestUtil::getActor($request))->where('slug', $slug)->first();

        if ($game) {
            $document->title = $game->title;
            $document->meta['description'] = Str::limit((string) $game->description, 160);
        }
    }
}
