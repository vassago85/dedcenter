<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the configuration + per-shot column the new ELR "team gong sequence"
 * mode needs:
 *
 *  - matches.elr_team_time_limit_seconds: the per-team countdown for a
 *    team's whole turn at a stage. Null = no limit (the timer is hidden).
 *    Stored in seconds; the match-edit UI sets it in minutes.
 *
 *  - elr_shots.impact_number: which IMPACT this hit was for its gong
 *    (1-based, counting hits only). In team-sequence scoring a miss does
 *    not consume a multiplier slot, so impact_number drives the multiplier
 *    rather than shot_number. Nullable: legacy / single-shooter shots and
 *    misses leave it null and keep using shot_number-based scoring.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedInteger('elr_team_time_limit_seconds')
                ->nullable()
                ->after('elr_shots_per_target');
        });

        Schema::table('elr_shots', function (Blueprint $table) {
            $table->unsignedTinyInteger('impact_number')
                ->nullable()
                ->after('shot_number');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('elr_team_time_limit_seconds');
        });

        Schema::table('elr_shots', function (Blueprint $table) {
            $table->dropColumn('impact_number');
        });
    }
};
