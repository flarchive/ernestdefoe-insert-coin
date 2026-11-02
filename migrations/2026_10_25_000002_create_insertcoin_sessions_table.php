<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * One row per press of Start. The token handed to the browser is an HMAC of
 * the row, so nothing secret is stored; `submitted_at` makes it one-use.
 */
return Migration::createTable('insertcoin_sessions', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedInteger('game_id');
    $table->unsignedInteger('user_id');
    $table->dateTime('started_at');
    $table->dateTime('submitted_at')->nullable();

    $table->foreign('game_id')->references('id')->on('insertcoin_games')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
    $table->index(['user_id', 'started_at']);
    $table->index('game_id');
    $table->index('started_at');
});
