<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * A game, wherever its files live (`source`): one of the starter games shipped
 * in the extension, an uploaded pack (files under public assets, the entry page
 * in `entry_html`, served only through the sandboxed frame), or a URL on
 * another site.
 *
 * The champion is kept on the game itself, so a grid of cards with crowns is
 * one query plus the champions' users.
 */
return Migration::createTable('insertcoin_games', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('category_id')->nullable();
    $table->string('slug', 100)->unique();
    $table->string('title', 150);
    $table->text('description')->nullable();
    $table->string('source', 20);
    $table->string('path', 191)->nullable();
    $table->string('url', 1000)->nullable();
    $table->string('entry', 255)->nullable();
    $table->mediumText('entry_html')->nullable();
    $table->string('thumbnail', 255)->nullable();
    $table->unsignedSmallInteger('width')->default(4);
    $table->unsignedSmallInteger('height')->default(3);
    $table->boolean('mobile')->default(true);
    $table->string('score_order', 4)->default('high');
    $table->string('score_label', 30)->nullable();
    $table->double('score_ceiling')->nullable();
    $table->unsignedInteger('min_seconds')->default(3);
    $table->unsignedInteger('max_seconds')->default(10800);
    $table->boolean('crown_approval')->default(false);
    $table->boolean('enabled')->default(true);
    $table->unsignedInteger('position')->default(0);
    $table->unsignedInteger('plays_count')->default(0);
    $table->unsignedInteger('champion_user_id')->nullable();
    $table->double('champion_score')->nullable();
    $table->dateTime('champion_since')->nullable();
    $table->timestamps();

    $table->foreign('category_id')->references('id')->on('insertcoin_categories')->nullOnDelete();
    $table->foreign('champion_user_id')->references('id')->on('users')->nullOnDelete();
    $table->index(['enabled', 'position']);
    $table->index('category_id');
    $table->index('champion_user_id');
});
