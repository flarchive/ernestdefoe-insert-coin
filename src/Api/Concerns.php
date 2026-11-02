<?php

namespace ErnestDefoe\InsertCoin\Api;

use ErnestDefoe\InsertCoin\Model\Game;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

/** The lookups every controller starts with. */
trait Concerns
{
    /** The id in the route, or 0 (not found) for one too big for an id column. */
    protected function routeId(ServerRequestInterface $request, string $name = 'id'): int
    {
        $id = (string) Arr::get($request->getAttributes(), 'routeParameters.'.$name);

        return strlen($id) <= 10 && (int) $id <= 2147483647 ? (int) $id : 0;
    }

    /** A game the actor may play, or a 404: the same answer whether it is missing or switched off. */
    protected function visibleGame(ServerRequestInterface $request, User $actor): Game
    {
        return Game::query()->visibleTo($actor)->findOrFail($this->routeId($request));
    }

    /** @return array<string, mixed> */
    protected function body(ServerRequestInterface $request): array
    {
        $body = (array) $request->getParsedBody();

        return (array) ($body['data'] ?? $body);
    }

    protected function at(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return ($value instanceof \DateTimeInterface ? \Carbon\Carbon::instance($value) : \Carbon\Carbon::parse((string) $value, 'UTC'))->toIso8601String();
    }
}
