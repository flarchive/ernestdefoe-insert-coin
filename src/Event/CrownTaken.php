<?php

namespace ErnestDefoe\InsertCoin\Event;

use ErnestDefoe\InsertCoin\Model\Crown;
use ErnestDefoe\InsertCoin\Model\Game;
use Flarum\User\User;

/** A member took a game's crown, from $previous or on an empty board. */
class CrownTaken
{
    public function __construct(public Game $game, public User $champion, public ?User $previous, public Crown $crown)
    {
    }
}
