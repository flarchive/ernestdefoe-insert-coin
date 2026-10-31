<?php

namespace ErnestDefoe\InsertCoin\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\InsertCoin\Tests\integration\ArcadeTestCase;
use PHPUnit\Framework\Attributes\Test;

/** The arcade's pages cost the same number of queries for few champions or many. */
class QueryCountTest extends ArcadeTestCase
{
    private function queries(string $path, ?int $actor = 2, array $query = []): int
    {
        $count = 0;
        $this->database()->listen(function () use (&$count) {
            $count++;
        });

        $this->json('GET', $path, $actor, null, $query); // warm caches
        $count = 0;
        [$status, , $raw] = $this->json('GET', $path, $actor, null, $query);
        $this->assertSame(200, $status, $raw);

        return $count;
    }

    private function members(int $from, int $count): void
    {
        // The app is booted by now, so straight into the table.
        for ($i = $from; $i < $from + $count; $i++) {
            $this->database()->table('users')->insert([
                'id' => $i, 'username' => "player$i", 'email' => "player$i@machine.local", 'is_email_confirmed' => 1,
                'password' => '', 'joined_at' => Carbon::now(),
            ]);
        }
    }

    #[Test]
    public function the_arcade_home_and_a_board_do_not_grow_with_players(): void
    {
        // One crown taken from someone, so every eager load has rows to load.
        $now = Carbon::now();
        $this->play(4, 'coil', 50, 30, $now->copy()->subMinutes(40));
        $this->play(3, 'coil', 100, 30, $now->copy()->subMinutes(30));
        Carbon::setTestNow($now);
        $home = $this->queries('/api/insert-coin');
        $board = $this->queries('/api/insert-coin/games/'.$this->gameId('coil').'/scores', 2, ['period' => 'week']);
        $game = $this->queries('/api/insert-coin/games/coil');

        $this->members(10, 7);
        $slugs = ['coil', 'brickfall', 'twofold', 'rock-belt', 'rooftop-dash', 'gem-rush', 'quickdraw'];
        foreach ($slugs as $i => $slug) {
            $this->play(10 + $i, $slug, $slug === 'quickdraw' ? 2000 : 1000 + $i, 120, $now->copy()->subMinutes(20 - $i));
            $this->play(3, $slug, $slug === 'quickdraw' ? 2500 : 900, 120, $now->copy()->subMinutes(10 - $i));
        }
        Carbon::setTestNow($now);

        $this->assertSame($home, $this->queries('/api/insert-coin'));
        $this->assertSame($board, $this->queries('/api/insert-coin/games/'.$this->gameId('coil').'/scores', 2, ['period' => 'week']));
        $this->assertSame($game, $this->queries('/api/insert-coin/games/coil'));
        $this->assertLessThanOrEqual(20, $this->queries('/api/insert-coin/users/3'));
    }
}
