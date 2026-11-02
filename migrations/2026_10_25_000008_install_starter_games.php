<?php

use ErnestDefoe\InsertCoin\StarterGames;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        StarterGames::install($schema->getConnection());
    },
    'down' => function (Builder $schema) {
        $schema->getConnection()->table('insertcoin_games')->where('source', 'bundled')->delete();
    },
];
