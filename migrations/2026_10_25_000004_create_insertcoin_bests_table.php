<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Each member's best approved score per game, kept on every submission. The
 * all-time board, "your best", your rank and the champion read these indexed
 * rows instead of ranking every score.
 */
return Migration::createTable('insertcoin_bests', function (Blueprint $table) {
    $table->unsignedInteger('game_id');
    $table->unsignedInteger('user_id');
    $table->unsignedBigInteger('score_id');
    $table->double('score');
    $table->dateTime('achieved_at');

    $table->primary(['game_id', 'user_id']);
    $table->foreign('game_id')->references('id')->on('insertcoin_games')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
    $table->index(['game_id', 'score', 'achieved_at']);
    $table->index('user_id');
});
