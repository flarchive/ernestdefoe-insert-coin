<?php

namespace ErnestDefoe\InsertCoin\Source;

use ErnestDefoe\InsertCoin\Model\Game;
use Illuminate\Contracts\Filesystem\Factory;

/**
 * An uploaded pack. Its files are public assets, but its HTML is not: the
 * entry page lives in the database and is only ever served by the frame,
 * sandboxed, so nothing in a pack runs with the forum's origin.
 */
class PackRenderer implements Renderer
{
    public function __construct(protected Factory $filesystems)
    {
    }

    public function html(Game $game): ?string
    {
        return $game->entry_html;
    }

    public function baseUrl(Game $game): ?string
    {
        return $this->filesystems->disk('flarum-assets')->url('insert-coin/packs/'.$game->path.'/');
    }
}
