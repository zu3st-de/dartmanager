<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tv_tournaments', function (Blueprint $table) {
            if (! Schema::hasColumn('tv_tournaments', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            }
        });

        DB::table('tv_tournaments')
            ->select('tv_tournaments.id', 'tournaments.user_id')
            ->leftJoin('tournaments', 'tv_tournaments.tournament_id', '=', 'tournaments.id')
            ->whereNull('tv_tournaments.user_id')
            ->orderBy('tv_tournaments.id')
            ->get()
            ->each(function ($entry): void {
                DB::table('tv_tournaments')
                    ->where('id', $entry->id)
                    ->update([
                        'user_id' => $entry->user_id,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('tv_tournaments', function (Blueprint $table) {
            if (Schema::hasColumn('tv_tournaments', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};
