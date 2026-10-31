<?php

namespace ErnestDefoe\InsertCoin\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\InsertCoin\Tests\integration\ArcadeTestCase;
use PHPUnit\Framework\Attributes\Test;

/** Personal bests, crowns, the dethroned alert, moderation and the boards. */
class ChampionTest extends ArcadeTestCase
{
    #[Test]
    public function a_better_score_takes_the_crown_and_tells_the_old_champion(): void
    {
        $this->play(2, 'coil', 500);
        $this->assertSame(2, $this->championOf('coil'));
        $this->assertSame(1, $this->crowns(2));

        [, $body] = $this->play(3, 'coil', 800);
        $this->assertTrue($body['data']['crown']);
        $this->assertSame(2, $body['data']['previous']['id']);

        $this->assertSame(3, $this->championOf('coil'));
        $this->assertSame(0, $this->crowns(2));
        $this->assertSame(1, $this->crowns(3));
        $this->assertSame(2, $this->database()->table('insertcoin_crowns')->count());

        $alert = $this->database()->table('notifications')->where('type', 'insertCoinDethroned')->first();
        $this->assertNotNull($alert);
        $this->assertSame(2, (int) $alert->user_id);
        $this->assertSame(3, (int) $alert->from_user_id);
        $this->assertSame($this->gameId('coil'), (int) $alert->subject_id);

        // The alert reads back through the API with its game.
        [$status, $list, $raw] = $this->json('GET', '/api/notifications', 2);
        $this->assertSame(200, $status, $raw);
        $this->assertSame('insertCoinDethroned', $list['data'][0]['attributes']['contentType']);
        $this->assertContains('insert-coin-games', array_column($list['included'], 'type'));
    }

    #[Test]
    public function a_tie_keeps_the_crown_where_it_is_and_the_champion_improving_is_no_new_crown(): void
    {
        $this->play(2, 'coil', 500);
        [, $body] = $this->play(3, 'coil', 500);
        $this->assertFalse($body['data']['crown']);
        $this->assertSame(2, $this->championOf('coil'));

        [, $body] = $this->play(2, 'coil', 900);
        $this->assertTrue($body['data']['personalBest']);
        $this->assertFalse($body['data']['crown']);
        $this->assertSame(1, $this->database()->table('insertcoin_crowns')->count());
        $this->assertSame(0, $this->database()->table('notifications')->count());
        $this->assertSame(900.0, (float) $this->database()->table('insertcoin_games')->where('slug', 'coil')->value('champion_score'));
    }

    #[Test]
    public function a_run_that_scores_nothing_sets_no_record(): void
    {
        [$status, $body] = $this->play(2, 'coil', 0);

        $this->assertSame(200, $status);
        $this->assertFalse($body['data']['personalBest']);
        $this->assertNull($this->championOf('coil'));
        $this->assertSame(0, $this->database()->table('insertcoin_bests')->count());
    }

    #[Test]
    public function a_worse_score_is_not_a_personal_best(): void
    {
        $this->play(2, 'coil', 500);
        [, $body] = $this->play(2, 'coil', 300);

        $this->assertSame('approved', $body['data']['status']);
        $this->assertFalse($body['data']['personalBest']);
        $this->assertSame(500.0, (float) $this->database()->table('insertcoin_bests')->where('user_id', 2)->value('score'));
    }

    #[Test]
    public function on_a_low_wins_game_the_fastest_time_is_champion(): void
    {
        $this->play(2, 'quickdraw', 1800);
        $this->play(3, 'quickdraw', 2400);
        $this->assertSame(2, $this->championOf('quickdraw'));

        $this->play(3, 'quickdraw', 1500);
        $this->assertSame(3, $this->championOf('quickdraw'));

        [, $board] = $this->json('GET', '/api/insert-coin/games/'.$this->gameId('quickdraw').'/scores', 2, null, ['period' => 'all']);
        $this->assertSame([3, 2], array_map(fn ($r) => $r['user']['id'], $board['data']['rows']));
        $this->assertEquals(['rank' => 2, 'score' => 1800.0], $board['data']['me']);
    }

    #[Test]
    public function an_outlier_is_held_until_a_moderator_approves_it(): void
    {
        $this->setting('ernestdefoe-insert-coin.outlier_players', 1);
        $this->play(2, 'coil', 1000);

        [, $body] = $this->play(3, 'coil', 9000);
        $this->assertSame('flagged', $body['data']['status']);
        $this->assertFalse($body['data']['crown']);
        $this->assertSame(2, $this->championOf('coil'));

        [, $queue] = $this->json('GET', '/api/insert-coin/queue', 1);
        $this->assertCount(1, $queue['data']);
        $this->assertSame('outlier', $queue['data'][0]['reason']);

        // Members can't see or act on the queue.
        [$status] = $this->json('GET', '/api/insert-coin/queue', 2);
        $this->assertSame(403, $status);

        [$status] = $this->json('POST', '/api/insert-coin/scores/'.$body['data']['id'].'/approve', 1);
        $this->assertSame(204, $status);
        $this->assertSame(3, $this->championOf('coil'));
        $this->assertSame(1, $this->database()->table('notifications')->where('type', 'insertCoinDethroned')->where('user_id', 2)->count());
    }

    #[Test]
    public function on_a_crown_approval_game_the_crown_waits_for_a_moderator(): void
    {
        $this->database()->table('insertcoin_games')->where('slug', 'coil')->update(['crown_approval' => true]);

        [, $body] = $this->play(2, 'coil', 400);
        $this->assertSame('pending', $body['data']['status']);
        $this->assertNull($this->championOf('coil'));

        $this->json('POST', '/api/insert-coin/scores/'.$body['data']['id'].'/approve', 1);
        $this->assertSame(2, $this->championOf('coil'));

        // Below the crown, scores count straight away.
        [, $body] = $this->play(3, 'coil', 300);
        $this->assertSame('approved', $body['data']['status']);
    }

    #[Test]
    public function deleting_the_champions_score_passes_the_crown_silently(): void
    {
        $this->play(2, 'coil', 500);
        $this->play(2, 'coil', 700);
        [, $carol] = $this->play(3, 'coil', 900);

        $this->json('DELETE', '/api/insert-coin/scores/'.$carol['data']['id'], 1);

        $this->assertSame(2, $this->championOf('coil'));
        $this->assertSame(700.0, (float) $this->database()->table('insertcoin_games')->where('slug', 'coil')->value('champion_score'));
        $this->assertSame(0, $this->crowns(3));
        $this->assertSame(1, $this->crowns(2));
        $this->assertNull($this->database()->table('insertcoin_bests')->where('user_id', 3)->first());
    }

    #[Test]
    public function wiping_and_banning_a_member_hands_their_crowns_on(): void
    {
        $this->play(2, 'coil', 500);
        $this->play(3, 'coil', 900);
        $this->play(3, 'brickfall', 100);

        [$status] = $this->json('POST', '/api/insert-coin/users/3/ban', 1, ['wipe' => true]);
        $this->assertSame(204, $status);

        $this->assertSame(2, $this->championOf('coil'));
        $this->assertNull($this->championOf('brickfall'));
        $this->assertSame(0, $this->crowns(3));
        $this->assertSame(0, $this->database()->table('insertcoin_scores')->where('user_id', 3)->count());
        $this->assertNotNull($this->database()->table('users')->where('id', 3)->value('insertcoin_banned_at'));

        // An admin can't be banned.
        [$status] = $this->json('POST', '/api/insert-coin/users/1/ban', 1);
        $this->assertSame(403, $status);
    }

    #[Test]
    public function wiping_a_game_starts_it_over(): void
    {
        $this->play(2, 'coil', 500);
        $this->play(3, 'coil', 900);

        [$status] = $this->json('DELETE', '/api/insert-coin/games/'.$this->gameId('coil').'/scores', 1);
        $this->assertSame(204, $status);

        $this->assertNull($this->championOf('coil'));
        $this->assertSame(0, $this->crowns(3));
        $this->assertSame(0, $this->database()->table('insertcoin_bests')->count());
    }

    #[Test]
    public function a_deleted_champions_crown_passes_to_the_next_best(): void
    {
        $this->play(2, 'coil', 500);
        $this->play(3, 'coil', 900);

        [$status] = $this->json('DELETE', '/api/users/3', 1);
        $this->assertSame(204, $status);

        $this->assertSame(2, $this->championOf('coil'));
        $this->assertSame(1, $this->crowns(2));
    }

    #[Test]
    public function the_week_and_month_boards_only_count_their_period(): void
    {
        $now = Carbon::now();
        $this->play(3, 'coil', 9000, 30, $now->copy()->subMonths(2));
        $this->play(2, 'coil', 500, 30, $now->copy()->subMinutes(5));
        $this->play(4, 'coil', 700, 30, $now->copy()->subMinutes(2));
        Carbon::setTestNow($now);

        [, $all] = $this->json('GET', '/api/insert-coin/games/'.$this->gameId('coil').'/scores', 2, null, ['period' => 'all']);
        [, $week] = $this->json('GET', '/api/insert-coin/games/'.$this->gameId('coil').'/scores', 2, null, ['period' => 'week']);

        $this->assertSame([3, 4, 2], array_map(fn ($r) => $r['user']['id'], $all['data']['rows']));
        $this->assertSame([4, 2], array_map(fn ($r) => $r['user']['id'], $week['data']['rows']));
        $this->assertEquals(['rank' => 2, 'score' => 500.0], $week['data']['me']);
    }

    #[Test]
    public function the_profile_lists_crowns_and_bests(): void
    {
        $this->play(2, 'coil', 500);
        $this->play(2, 'twofold', 2048);
        $this->play(3, 'twofold', 4096);

        [$status, $body] = $this->json('GET', '/api/insert-coin/users/2');
        $this->assertSame(200, $status);
        $this->assertSame(['coil'], array_column($body['data']['crowns'], 'slug'));
        $this->assertSame(2, $body['data']['played']);
        $this->assertSame(1, $body['data']['user']['crowns']);

        [, $user] = $this->json('GET', '/api/users/2');
        $this->assertSame(1, $user['data']['attributes']['arcadeCrowns']);
        $this->assertArrayNotHasKey('arcadeBanned', $user['data']['attributes']);
    }
}
