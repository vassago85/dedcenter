<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When true the ELR engine scores hits as `distance × multiplier`
 * (Peregrine ELR Challenge style — 1678m hit on shot 1 = 1678 × 1.5).
 * When false it falls back to the legacy `base_points × multiplier`
 * behaviour for existing matches that were set up before this toggle.
 *
 * Default `false` so we do NOT silently change existing ELR matches.
 * New matches set up after this migration will toggle this on via the
 * match-setup UI (Phase B).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->boolean('elr_distance_based_scoring')
                ->default(false)
                ->after('elr_shots_per_target');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('elr_distance_based_scoring');
        });
    }
};
