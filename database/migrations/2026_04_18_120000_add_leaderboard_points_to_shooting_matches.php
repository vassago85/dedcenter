<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-match weight for the season leaderboard.
 *
 * Every shooter's relative score on a match is `round(shooter / winner * leaderboard_points)`,
 * and the season leaderboard sums each shooter's best 3 of those across the season.
 *
 * Default 100 = regular match (out of 100).
 * Use 200 for a season final (out of 200).
 *
 * Unsigned small int is plenty of room (0–65535) and keeps the schema lean.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedSmallInteger('leaderboard_points')->default(100)->after('scores_published');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('leaderboard_points');
        });
    }
};
