<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow a team to keep scoring past the per-team time limit on an ELR stage,
 * but require the MD to capture WHY. The reason is stored alongside the
 * timer lifecycle so it surfaces in audits / detailed breakdowns.
 *
 * Replaces the old "hard lock at zero" behavior — the MD now consciously
 * acknowledges overtime instead of the app silently refusing taps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('elr_team_stage_entries', function (Blueprint $table) {
            $table->string('overtime_reason', 500)->nullable()->after('timed_out');
        });
    }

    public function down(): void
    {
        Schema::table('elr_team_stage_entries', function (Blueprint $table) {
            $table->dropColumn('overtime_reason');
        });
    }
};
