<?php

namespace ErnestDefoe\InsertCoin\Source;

use ErnestDefoe\InsertCoin\Model\Game;

/**
 * Turns a game into the page the sandboxed frame shows. The arcade adds the
 * SDK and the `<base>` itself; a renderer returns the game's own HTML.
 *
 * This is the seam for other kinds of game: a Flash add-on registers a
 * renderer for its source (a page that boots Ruffle with the SWF) through
 * ErnestDefoe\InsertCoin\Extend\GameSource.
 */
interface Renderer
{
    public function html(Game $game): ?string;

    /** Where the page's relative URLs point, or null. */
    public function baseUrl(Game $game): ?string;
}
