<?php

namespace ErnestDefoe\InsertCoin\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\InsertCoin\Tests\integration\ArcadeTestCase;
use PHPUnit\Framework\Attributes\Test;

/** Play sessions and the rules a score must pass. */
class SessionTest extends ArcadeTestCase
{
    #[Test]
    public function starter_games_are_installed_and_listed(): void
    {
        [$status, $body] = $this->json('GET', '/api/insert-coin');

        $this->assertSame(200, $status);
        $this->assertCount(7, $body['data']['games']);
        $this->assertSame(['coil', 'brickfall', 'twofold', 'rock-belt', 'rooftop-dash', 'gem-rush', 'quickdraw'], array_column($body['data']['games'], 'slug'));
        $this->assertNotEmpty($body['data']['games'][0]['frameUrl']);
    }

    #[Test]
    public function a_guest_can_look_but_not_start_a_session(): void
    {
        [$status] = $this->json('GET', '/api/insert-coin/games/coil');
        $this->assertSame(200, $status);

        [$status] = $this->json('POST', '/api/insert-coin/games/'.$this->gameId('coil').'/sessions');
        $this->assertSame(401, $status);
    }

    #[Test]
    public function a_score_counts_once_with_the_right_token(): void
    {
        $session = $this->startRun(2, 'coil');
        Carbon::setTestNow(Carbon::now()->addSeconds(30));
        $path = '/api/insert-coin/sessions/'.$session['id'].'/score';

        [$status] = $this->json('POST', $path, 2, ['token' => str_repeat('0', 64), 'score' => 500]);
        $this->assertSame(422, $status);

        [$status, $body, $raw] = $this->json('POST', $path, 2, ['token' => $session['token'], 'score' => 500]);
        $this->assertSame(200, $status, $raw);
        $this->assertSame('approved', $body['data']['status']);
        $this->assertTrue($body['data']['personalBest']);
        $this->assertTrue($body['data']['crown']);
        $this->assertEquals(['rank' => 1, 'score' => 500.0], $body['data']['me']);

        [$status] = $this->json('POST', $path, 2, ['token' => $session['token'], 'score' => 900]);
        $this->assertSame(422, $status);
        $this->assertSame(1, $this->database()->table('insertcoin_scores')->count());
    }

    #[Test]
    public function another_members_session_is_not_found(): void
    {
        $session = $this->startRun(2, 'coil');
        Carbon::setTestNow(Carbon::now()->addSeconds(30));

        [$status] = $this->json('POST', '/api/insert-coin/sessions/'.$session['id'].'/score', 3, ['token' => $session['token'], 'score' => 500]);
        $this->assertSame(404, $status);
    }

    #[Test]
    public function a_run_must_last_between_the_games_limits(): void
    {
        $this->database()->table('insertcoin_games')->where('slug', 'coil')->update(['min_seconds' => 5]);
        [$status] = $this->play(2, 'coil', 100, 2);
        $this->assertSame(422, $status);

        [$status] = $this->play(2, 'coil', 100, 10801);
        $this->assertSame(422, $status);

        [$status] = $this->play(2, 'coil', 100, 5);
        $this->assertSame(200, $status);
    }

    #[Test]
    public function a_score_over_the_ceiling_or_not_a_number_is_refused(): void
    {
        $this->database()->table('insertcoin_games')->where('slug', 'coil')->update(['score_ceiling' => 20000]);

        foreach ([20001, -5, 'lots', null, 'INF', 'NAN'] as $score) {
            [$status] = $this->play(2, 'coil', $score);
            $this->assertSame(422, $status, var_export($score, true));
        }

        $this->assertSame(0, $this->database()->table('insertcoin_scores')->count());
    }

    #[Test]
    public function a_banned_member_cannot_start(): void
    {
        $this->database()->table('users')->where('id', 2)->update(['insertcoin_banned_at' => Carbon::now()]);

        [$status] = $this->json('POST', '/api/insert-coin/games/'.$this->gameId('coil').'/sessions', 2);
        $this->assertSame(403, $status);
    }

    #[Test]
    public function a_switched_off_game_is_hidden_from_members(): void
    {
        $this->database()->table('insertcoin_games')->where('slug', 'coil')->update(['enabled' => false]);

        [$status] = $this->json('GET', '/api/insert-coin/games/coil', 2);
        $this->assertSame(404, $status);
        [$status] = $this->json('POST', '/api/insert-coin/games/'.$this->gameId('coil').'/sessions', 2);
        $this->assertSame(404, $status);
        [$status, $body] = $this->json('GET', '/api/insert-coin', 2);
        $this->assertNotContains('coil', array_column($body['data']['games'], 'slug'));
    }

    #[Test]
    public function starting_too_many_runs_is_slowed_down(): void
    {
        $now = Carbon::now();
        $this->database()->table('insertcoin_sessions')->insert(array_fill(0, 120, [
            'game_id' => $this->gameId('coil'), 'user_id' => 2, 'started_at' => $now->copy()->subMinute(),
        ]));
        Carbon::setTestNow($now);

        [$status] = $this->json('POST', '/api/insert-coin/games/'.$this->gameId('coil').'/sessions', 2);
        $this->assertSame(422, $status);
    }

    #[Test]
    public function the_frame_is_sandboxed_and_carries_the_sdk(): void
    {
        $response = $this->send($this->request('GET', '/arcade/frame/'.$this->gameId('coil')));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('sandbox allow-scripts', $response->getHeaderLine('Content-Security-Policy'));
        $this->assertStringNotContainsString('allow-same-origin', $response->getHeaderLine('Content-Security-Policy'));
        $this->assertStringContainsString('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $html = (string) $response->getBody();
        $this->assertStringContainsString('w.InsertCoin', $html);
        $this->assertLessThan(strpos($html, 'w.InsertCoin'), stripos($html, '<head'));
    }
}
