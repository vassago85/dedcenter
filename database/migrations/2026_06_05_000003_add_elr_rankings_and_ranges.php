<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supports the expanded ELR team feature: admin-configured per-stage,
 * per-division gong ranges; the alternate-scoring capture flag; and the
 * stored per-stage team totals used by the ranking views + exports.
 *
 *  - elr_stage_division_ranges: the admin-facing source of truth for which
 *    gongs (1-based ordinal within the stage) each division engages on each
 *    stage. Saving these materialises the existing elr_division_targets pivot
 *    so the sequence engine + scoring keep working unchanged.
 *
 *  - matches.alternate_scoring: captured-only flag for a future alternate
 *    team scoring mode (no logic yet).
 *
 *  - elr_team_stage_entries.{team_total_score, shooter_1_*, shooter_2_*}:
 *    snapshot of a team's stage result, written on completion/correction.
 *    Rankings still compute live from shots; these are for record + export.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elr_stage_division_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('elr_stage_id')->constrained('elr_stages')->cascadeOnDelete();
            $table->foreignId('match_division_id')->constrained('match_divisions')->cascadeOnDelete();
            $table->unsignedSmallInteger('gong_start');
            $table->unsignedSmallInteger('gong_end');
            $table->timestamps();

            $table->unique(['elr_stage_id', 'match_division_id'], 'elr_stage_div_range_unique');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->boolean('alternate_scoring')->default(false)->after('elr_team_time_limit_seconds');
        });

        Schema::table('elr_team_stage_entries', function (Blueprint $table) {
            $table->decimal('team_total_score', 10, 2)->nullable()->after('timed_out');
            $table->foreignId('shooter_1_id')->nullable()->after('team_total_score');
            $table->decimal('shooter_1_score', 10, 2)->nullable()->after('shooter_1_id');
            $table->foreignId('shooter_2_id')->nullable()->after('shooter_1_score');
            $table->decimal('shooter_2_score', 10, 2)->nullable()->after('shooter_2_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elr_stage_division_ranges');

        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('alternate_scoring');
        });

        Schema::table('elr_team_stage_entries', function (Blueprint $table) {
            $table->dropColumn([
                'team_total_score', 'shooter_1_id', 'shooter_1_score', 'shooter_2_id', 'shooter_2_score',
            ]);
        });
    }
};
