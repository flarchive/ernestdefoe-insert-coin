<?php

namespace ErnestDefoe\InsertCoin\Api;

use ErnestDefoe\InsertCoin\Access;
use ErnestDefoe\InsertCoin\Arcade;
use ErnestDefoe\InsertCoin\Boards;
use ErnestDefoe\InsertCoin\Model\Game;
use ErnestDefoe\InsertCoin\Model\PlaySession;
use ErnestDefoe\InsertCoin\Presenter;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/insert-coin/sessions/{id}/score {token, score} — the end of a run.
 * Answers with how it went: counted or held, a personal best, a crown, and
 * where the player stands now.
 */
class ScoreController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(protected Arcade $arcade, protected Boards $boards, protected Presenter $presenter)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        if (! Access::canSubmit($actor)) {
            throw new PermissionDeniedException();
        }

        /** @var PlaySession $session */
        $session = PlaySession::query()->where('user_id', $actor->id)->findOrFail($this->routeId($request));
        $body = $this->body($request);

        $result = $this->arcade->submit($session, (string) ($body['token'] ?? ''), $body['score'] ?? null, $actor);
        $score = $result['score'];

        /** @var Game $game */
        $game = Game::query()->findOrFail($session->game_id);

        return new JsonResponse(['data' => [
            'id' => $score->id,
            'score' => $score->score,
            'status' => $score->status,
            'reason' => $score->reason,
            'personalBest' => $result['personalBest'],
            'crown' => $result['crown'],
            'previous' => $this->presenter->person($result['previous']),
            'me' => $this->boards->standing($game, $actor->id, 'all'),
        ]]);
    }
}
