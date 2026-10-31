<?php

use Flarum\Database\Migration;
use Flarum\Group\Group;

// Anyone may play, members' scores count, moderators look after the boards.
return Migration::addPermissions([
    'insertCoin.play' => Group::GUEST_ID,
    'insertCoin.submit' => Group::MEMBER_ID,
    'insertCoin.moderate' => Group::MODERATOR_ID,
]);
