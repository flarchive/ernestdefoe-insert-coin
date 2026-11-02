<?php

namespace ErnestDefoe\InsertCoin\Source;

use ErnestDefoe\InsertCoin\Model\Game;

/** The starter games: one HTML file each, shipped in the extension's games/ folder. */
class BundledRenderer implements Renderer
{
    public function html(Game $game): ?string
    {
        if (! preg_match('/^[a-z0-9-]+$/', (string) $game->path)) {
            return null;
        }

        $file = dirname(__DIR__, 2).'/games/'.$game->path.'/index.html';

        return is_file($file) ? (string) file_get_contents($file) : null;
    }

    public function baseUrl(Game $game): ?string
    {
        return null;
    }
}
