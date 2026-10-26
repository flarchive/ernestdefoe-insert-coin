<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Every accepted run. `status` is approved, or flagged (an outlier) or pending
 * (a crown waiting for approval): only approved rows count anywhere.
 */
return Migration::createTable('insertcoin_scores', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedInteger('game_id');
    $table->unsignedInteger('user_id');
    $table->unsignedBigInteger('session_id')->nullable()->unique();
    $table->double('score');
    $table->unsignedInteger('duration')->default(0);
    $table->string('status', 10)->default('approved');
    $table->string('reason', 20)->nullable();
    $table->dateTime('created_at');

    $table->foreign('game_id')->references('id')->on('insertcoin_games')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
    // This week's / this month's boards, and the moderation queue.
    $table->index(['game_id', 'status', 'created_at']);
    $table->index(['user_id', 'created_at']);
    $table->index(['status', 'created_at']);
});
