<?php

namespace ErnestDefoe\InsertCoin\Api;

use ErnestDefoe\InsertCoin\Access;
use ErnestDefoe\InsertCoin\Arcade;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** POST /api/insert-coin/games/{id}/sessions — Start was pressed: a signed, short-lived session. */
class SessionController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(protected Arcade $arcade)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $game = $this->visibleGame($request, $actor);

        if (! Access::canSubmit($actor) || ! $game->enabled) {
            throw new PermissionDeniedException();
        }

        $session = $this->arcade->start($game, $actor);

        return new JsonResponse(['data' => [
            'id' => $session->id,
            'token' => $this->arcade->token($session),
            'expiresAt' => $this->arcade->expiresAt($session, $game)->toIso8601String(),
        ]], 201);
    }
}
