<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (team x stage) for the ELR team gong-sequence mode. Captures
 * the lifecycle of a team's turn at a stage so the scoring UI, timer, and
 * audit trail have a single source of truth:
 *
 *  - first_shooter_id: which team member fires first (S1) at this stage.
 *    Swaps each stage per the rotation rule.
 *  - position: the team's firing order within its squad for this stage
 *    (1-based). Advisory recommendation; the RO still picks the team.
 *  - started_at / completed_at: bound the team's turn for the timer.
 *  - timed_out: set when the per-team countdown expired before completion.
 *
 * Per-shooter and team totals are computed from elr_shots, not stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elr_team_stage_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('elr_stage_id')->constrained('elr_stages')->cascadeOnDelete();
            $table->foreignId('squad_id')->nullable()->constrained('squads')->nullOnDelete();
            $table->foreignId('first_shooter_id')->nullable()->constrained('shooters')->nullOnDelete();
            $table->unsignedSmallInteger('position')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('timed_out')->default(false);
            $table->string('device_id')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'elr_stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elr_team_stage_entries');
    }
};
