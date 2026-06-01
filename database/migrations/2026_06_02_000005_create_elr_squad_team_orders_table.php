<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rotation order for ELR teams within a squad, per station.
 *
 * The Peregrine format starts every squad with an explicit team order at
 * the first station, then "last team moves to first, everyone shifts back"
 * at each station change. Rather than recomputing that on the fly from a
 * single seed order, we materialise the order per station so:
 *
 *   - Match directors can override an individual station's order if a
 *     team withdraws mid-day.
 *   - The mobile RO UI can answer "who shoots next?" in O(1).
 *   - Audits / replays are deterministic — no implicit rotation rules
 *     buried in code.
 *
 * `position` is 1-based. `shooter_first_id` records which of the team's
 * two shooters takes the first shot AT THIS STATION; the UI alternates
 * who that is per station so neither shooter is the wind reader all day.
 *
 * Nullable foreign keys on shooter_first_id because the leadoff might
 * not be assigned for every station up front, but the order can still
 * exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elr_squad_team_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('squad_id')->constrained('squads')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('elr_stage_id')->constrained('elr_stages')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->foreignId('shooter_first_id')->nullable()->constrained('shooters')->nullOnDelete();
            $table->timestamps();

            $table->unique(['squad_id', 'elr_stage_id', 'team_id'], 'elr_rot_squad_stage_team_unique');
            $table->unique(['squad_id', 'elr_stage_id', 'position'], 'elr_rot_squad_stage_pos_unique');
            $table->index(['elr_stage_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elr_squad_team_orders');
    }
};
