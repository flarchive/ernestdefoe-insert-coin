<?php

namespace ErnestDefoe\InsertCoin\Api;

use ErnestDefoe\InsertCoin\Model\Game;
use Flarum\User\User;

/** A board as JSON: its rows, the next page, and where the actor stands. */
trait PresentsBoards
{
    /** @return array<string, mixed> */
    protected function board(Game $game, string $period, int $offset, User $actor, int $limit = 10): array
    {
        $board = $this->boards->rows($game, $period, $offset, $limit);

        return [
            'period' => $period,
            'rows' => array_map(fn ($row) => [
                'rank' => $row['rank'],
                'user' => $this->presenter->person($row['user']),
                'score' => $row['score'],
                'at' => $this->at($row['at']),
            ], $board['rows']),
            'next' => $board['next'],
            'me' => $actor->isGuest() ? null : $this->boards->standing($game, $actor->id, $period),
        ];
    }
}
