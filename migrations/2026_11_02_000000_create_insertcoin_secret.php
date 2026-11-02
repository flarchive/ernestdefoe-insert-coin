<?php

use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Str;

// The key that signs play sessions, made once here so two first plays can't
// each write a different one. Existing forums keep the key they have.
return [
    'up' => function (Builder $schema) {
        $schema->getConnection()->table('settings')->insertOrIgnore([
            'key' => 'ernestdefoe-insert-coin.secret',
            'value' => Str::random(64),
        ]);
    },
    'down' => function (Builder $schema) {
        //
    },
];
