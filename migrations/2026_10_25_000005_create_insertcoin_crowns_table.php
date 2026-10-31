<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/** Every time a crown changed hands: "recent crowns" and a member's history. */
return Migration::createTable('insertcoin_crowns', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedInteger('game_id');
    $table->unsignedInteger('user_id');
    $table->unsignedInteger('previous_user_id')->nullable();
    $table->double('score');
    $table->dateTime('created_at');

    $table->foreign('game_id')->references('id')->on('insertcoin_games')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
    $table->foreign('previous_user_id')->references('id')->on('users')->nullOnDelete();
    $table->index('created_at');
    $table->index(['user_id', 'created_at']);
});
