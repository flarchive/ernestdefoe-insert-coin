<?php

use Flarum\Database\Migration;

/**
 * Crowns held, counted on the member so a crown can show on any user card and
 * the Hall of Fame is one indexed query; and the arcade ban.
 */
return [
    'up' => function ($schema) {
        $schema->table('users', function ($table) {
            $table->unsignedInteger('insertcoin_crowns')->default(0)->index();
            $table->dateTime('insertcoin_banned_at')->nullable();
        });
    },
    'down' => function ($schema) {
        $schema->table('users', function ($table) {
            $table->dropIndex(['insertcoin_crowns']);
            $table->dropColumn(['insertcoin_crowns', 'insertcoin_banned_at']);
        });
    },
];
