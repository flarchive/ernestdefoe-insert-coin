<?php

namespace ErnestDefoe\InsertCoin;

use Flarum\User\User;

/** Who may do what. Permissions only: the actor's groups are already loaded. */
class Access
{
    public static function canPlay(User $actor): bool
    {
        return $actor->hasPermission('insertCoin.play');
    }

    public static function banned(User $actor): bool
    {
        return $actor->getAttribute('insertcoin_banned_at') !== null;
    }

    public static function canSubmit(User $actor): bool
    {
        return ! $actor->isGuest() && $actor->hasPermission('insertCoin.submit') && ! self::banned($actor);
    }

    public static function canModerate(User $actor): bool
    {
        return $actor->hasPermission('insertCoin.moderate');
    }
}
