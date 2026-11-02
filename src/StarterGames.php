<?php

namespace ErnestDefoe\InsertCoin;

use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;

/**
 * The seven original games that ship with the arcade, so a fresh install is
 * fun the minute it's switched on. Installed by a migration, and again from the
 * admin page ("Restore starter games") for any that were deleted.
 */
class StarterGames
{
    public const CATEGORIES = [
        ['slug' => 'action', 'name' => 'Action', 'icon' => 'fas fa-bolt'],
        ['slug' => 'puzzle', 'name' => 'Puzzle', 'icon' => 'fas fa-puzzle-piece'],
        ['slug' => 'arcade', 'name' => 'Arcade', 'icon' => 'fas fa-ghost'],
        ['slug' => 'skill', 'name' => 'Skill', 'icon' => 'fas fa-bullseye'],
    ];

    public const GAMES = [
        ['slug' => 'coil', 'title' => 'Coil', 'category' => 'arcade', 'width' => 1, 'height' => 1, 'order' => 'high', 'label' => 'points', 'ceiling' => 20000, 'min' => 1,
            'description' => 'A glowing snake that glides between cells: grab the pulsing pellets, chase the golden ones before they fade, and never touch the wall.'],
        ['slug' => 'brickfall', 'title' => 'Brickfall', 'category' => 'arcade', 'width' => 4, 'height' => 3, 'order' => 'high', 'label' => 'points', 'ceiling' => 2000000, 'min' => 3,
            'description' => 'Break neon bricks, chain hits for combo multipliers and catch the power-ups that fall: wide paddle, multi-ball and slow motion.'],
        ['slug' => 'twofold', 'title' => 'Twofold', 'category' => 'puzzle', 'width' => 1, 'height' => 1, 'order' => 'high', 'label' => 'points', 'ceiling' => 4000000, 'min' => 3,
            'description' => 'Slide glowing number tiles across the board, merge equal pairs and keep doubling until the board locks up.'],
        ['slug' => 'rock-belt', 'title' => 'Rock Belt', 'category' => 'action', 'width' => 16, 'height' => 9, 'order' => 'high', 'label' => 'points', 'ceiling' => 3000000, 'min' => 6,
            'description' => 'Pilot a drifting neon ship through a looping rock belt: split boulders into shards, dodge saucers that shoot back and pop your shield when it gets crowded.'],
        ['slug' => 'rooftop-dash', 'title' => 'Rooftop Dash', 'category' => 'action', 'width' => 16, 'height' => 9, 'order' => 'high', 'label' => 'points', 'ceiling' => 1000000, 'min' => 3,
            'description' => 'Sprint, jump and slide across a neon skyline at sunset, grabbing coins as the city speeds up.'],
        ['slug' => 'gem-rush', 'title' => 'Gem Rush', 'category' => 'puzzle', 'width' => 3, 'height' => 4, 'order' => 'high', 'label' => 'points', 'ceiling' => 250000, 'min' => 85,
            'description' => 'A 90-second match-3 rush: swap jewels, chain cascades and set off line gems and colour bombs for big combos and extra time.'],
        ['slug' => 'quickdraw', 'title' => 'Quickdraw', 'category' => 'skill', 'width' => 16, 'height' => 9, 'order' => 'low', 'label' => 'ms', 'ceiling' => 10000, 'min' => 10,
            'description' => 'A neon showdown against five outlaws. Wait for DRAW!, fire faster than they can, and don\'t flinch: jumping early costs you.'],
    ];

    /** Add the categories and games that are missing; returns how many games were added. */
    public static function install(ConnectionInterface $db): int
    {
        $categories = $db->table('insertcoin_categories')->pluck('id', 'slug')->all();
        $position = (int) $db->table('insertcoin_categories')->max('position');

        foreach (self::CATEGORIES as $category) {
            if (! isset($categories[$category['slug']])) {
                $categories[$category['slug']] = $db->table('insertcoin_categories')->insertGetId($category + ['position' => ++$position]);
            }
        }

        $existing = $db->table('insertcoin_games')->where('source', 'bundled')->pluck('path')->all();
        $taken = $db->table('insertcoin_games')->pluck('slug')->all();
        $position = (int) $db->table('insertcoin_games')->max('position');
        $now = Carbon::now();
        $added = 0;

        foreach (self::GAMES as $game) {
            if (in_array($game['slug'], $existing, true)) {
                continue;
            }

            $slug = $game['slug'];
            for ($n = 2; in_array($slug, $taken, true); $n++) {
                $slug = $game['slug'].'-'.$n;
            }

            $db->table('insertcoin_games')->insert([
                'category_id' => $categories[$game['category']] ?? null,
                'slug' => $slug,
                'title' => $game['title'],
                'description' => $game['description'],
                'source' => 'bundled',
                'path' => $game['slug'],
                'thumbnail' => 'extensions/ernestdefoe-insert-coin/covers/'.$game['slug'].'.webp',
                'width' => $game['width'],
                'height' => $game['height'],
                'mobile' => true,
                'score_order' => $game['order'],
                'score_label' => $game['label'],
                'score_ceiling' => $game['ceiling'],
                'min_seconds' => $game['min'],
                'max_seconds' => 10800,
                'enabled' => true,
                'position' => ++$position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $taken[] = $slug;
            $added++;
        }

        return $added;
    }
}
