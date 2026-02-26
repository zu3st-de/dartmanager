<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tournament_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('player1_id')
                ->nullable()
                ->constrained('players')
                ->nullOnDelete();

            $table->foreignId('player2_id')
                ->nullable()
                ->constrained('players')
                ->nullOnDelete();

            $table->unsignedInteger('round');
            $table->unsignedInteger('position');

            $table->foreignId('winner_id')
                ->nullable()
                ->constrained('players')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
