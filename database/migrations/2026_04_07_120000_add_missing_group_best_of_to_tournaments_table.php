<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tournaments', 'group_best_of')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->unsignedTinyInteger('group_best_of')->nullable()->after('group_advance_count');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tournaments', 'group_best_of')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->dropColumn('group_best_of');
            });
        }
    }
};
