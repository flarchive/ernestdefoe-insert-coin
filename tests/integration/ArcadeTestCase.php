<?php

namespace ErnestDefoe\InsertCoin\Tests\integration;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;

/** The arcade with a few members, and helpers to play a run at a given time. */
abstract class ArcadeTestCase extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-insert-coin');

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'carol', 'email' => 'carol@machine.local', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'dave', 'email' => 'dave@machine.local', 'is_email_confirmed' => 1],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{0: int, 1: array<string, mixed>|null, 2: string} */
    protected function json(string $method, string $path, ?int $actor = null, ?array $body = null, array $query = []): array
    {
        $options = $actor ? ['authenticatedAs' => $actor] : [];
        if ($body !== null) {
            $options['json'] = $body;
        }

        $request = $this->request($method, $path, $options);
        if ($query) {
            $request = $request->withQueryParams($query);
        }

        $response = $this->send($actor ? $request : $request->withAttribute('bypassCsrfToken', true));
        $raw = (string) $response->getBody();

        return [$response->getStatusCode(), json_decode($raw, true), $raw];
    }

    protected function gameId(string $slug): int
    {
        return (int) $this->database()->table('insertcoin_games')->where('slug', $slug)->value('id');
    }

    /** @return array<string, mixed> */
    protected function startRun(int $user, string $slug, ?Carbon $at = null): array
    {
        Carbon::setTestNow($at ?? Carbon::now());
        [$status, $body, $raw] = $this->json('POST', '/api/insert-coin/games/'.$this->gameId($slug).'/sessions', $user);
        $this->assertSame(201, $status, $raw);

        return $body['data'];
    }

    /**
     * Start a run, let $seconds pass, send $score.
     *
     * @return array{0: int, 1: array<string, mixed>|null, 2: string}
     */
    protected function play(int $user, string $slug, mixed $score, int $seconds = 30, ?Carbon $at = null): array
    {
        $at ??= Carbon::now();
        $session = $this->startRun($user, $slug, $at);
        Carbon::setTestNow($at->copy()->addSeconds($seconds));

        return $this->json('POST', '/api/insert-coin/sessions/'.$session['id'].'/score', $user, ['token' => $session['token'], 'score' => $score]);
    }

    protected function championOf(string $slug): ?int
    {
        $id = $this->database()->table('insertcoin_games')->where('slug', $slug)->value('champion_user_id');

        return $id === null ? null : (int) $id;
    }

    protected function crowns(int $user): int
    {
        return (int) $this->database()->table('users')->where('id', $user)->value('insertcoin_crowns');
    }
}
