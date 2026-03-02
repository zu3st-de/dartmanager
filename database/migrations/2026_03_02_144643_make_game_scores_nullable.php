<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedInteger('player1_score')->nullable()->change();
            $table->unsignedInteger('player2_score')->nullable()->change();
            $table->unsignedInteger('winning_rest')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedInteger('player1_score')->nullable(false)->change();
            $table->unsignedInteger('player2_score')->nullable(false)->change();
            $table->unsignedInteger('winning_rest')->nullable(false)->change();
        });
    }
};
