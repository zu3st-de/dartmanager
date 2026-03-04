<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('games', function (Blueprint $table) {
            $table->integer('player1_score')->nullable()->change();
            $table->integer('player2_score')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('games', function (Blueprint $table) {
            $table->integer('player1_score')->nullable(false)->change();
            $table->integer('player2_score')->nullable(false)->change();
        });
    }
};
