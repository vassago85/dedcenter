<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot the distance and multiplier that were in effect AT THE MOMENT a
 * shot was recorded. Required so that editing match settings (distances,
 * shot multipliers) after the match has started never silently rewrites
 * historical scores — the original points were calculated against these
 * numbers and the leaderboard must remain reproducible.
 *
 * Both columns are nullable so existing shots (which never captured these)
 * can fall back to the live target distance + profile multiplier. New
 * shots written by ELRScoringService and ElrScoreController will always
 * populate them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('elr_shots', function (Blueprint $table) {
            $table->unsignedSmallInteger('distance_at_score')->nullable()->after('points_awarded');
            $table->decimal('multiplier_at_score', 5, 3)->nullable()->after('distance_at_score');
        });
    }

    public function down(): void
    {
        Schema::table('elr_shots', function (Blueprint $table) {
            $table->dropColumn(['distance_at_score', 'multiplier_at_score']);
        });
    }
};
