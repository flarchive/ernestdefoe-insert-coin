<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('insertcoin_categories', function (Blueprint $table) {
    $table->increments('id');
    $table->string('name', 100);
    $table->string('slug', 100)->unique();
    $table->string('icon', 100)->nullable();
    $table->unsignedInteger('position')->default(0);
});
