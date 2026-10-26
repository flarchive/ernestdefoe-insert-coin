<?php

namespace ErnestDefoe\InsertCoin\Event;

use ErnestDefoe\InsertCoin\Model\Game;
use ErnestDefoe\InsertCoin\Model\Score;
use Flarum\User\User;

/** An approved score was counted. Realtime boards listen for it. */
class ScoreAccepted
{
    public function __construct(public Game $game, public Score $score, public User $user)
    {
    }
}
