<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // Season leaderboard scoring mode.
            //   true  → each match is normalised to round(shooter / match_winner × points_value),
            //           then a shooter's season total = sum of their best N relative scores.
            //   false → raw weighted totals are summed instead (legacy / absolute scoring).
            // Default true because SeasonStandingsService has always produced relative scores.
            $table->boolean('uses_relative_scoring')->default(true)->after('best_of');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('uses_relative_scoring');
        });
    }
};
