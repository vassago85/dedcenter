<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds first-class support for the ALRHA scoring mode.
 *
 * ALRHA re-uses ELR's stages/targets/shots plumbing (already offline-first,
 * multi-shot, multiplier-based), so we only need a handful of new columns
 * plus a pair of pivot tables for peer-scoring groups.
 *
 * Columns added:
 *  - matches.alrha_class:      'hunters' | 'varmint' (one class per match).
 *  - elr_targets.is_cold_bore: excludes CBC hits from class totals.
 *  - elr_targets.alrha_block:  'far' | 'near' | 'cbc' — drives which block
 *                              the scoring UI shows for the active relay.
 *  - shooters.is_coached:      flag for §4 coached scores (kept but never
 *                              eligible for match / series prize tables).
 *  - shooters.gong_position:   printed on the squadding sheet — which
 *                              gong lane each shooter/team is on.
 *  - shooters.shared_rifle_key: opaque group id (e.g. rifle serial or an
 *                              MD-set label). Two shooters that share
 *                              a rifle must not be in overlapping relays.
 *
 * New tables:
 *  - alrha_scoring_groups:         (match_id, squad_id/relay, type)
 *  - alrha_scoring_group_members:  hunter pair → team_id refs; varmint
 *                                  triple → shooter_id refs.
 *
 * SQLite in test environments doesn't support ->after() so we guard those.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('matches', function (Blueprint $table) use ($driver) {
            $col = $table->string('alrha_class', 16)->nullable();
            if ($driver !== 'sqlite') {
                $col->after('scoring_type');
            }
        });

        Schema::table('elr_targets', function (Blueprint $table) use ($driver) {
            $col1 = $table->boolean('is_cold_bore')->default(false);
            if ($driver !== 'sqlite') {
                $col1->after('must_hit_to_advance');
            }
            $col2 = $table->string('alrha_block', 8)->nullable();
            if ($driver !== 'sqlite') {
                $col2->after('is_cold_bore');
            }
        });

        Schema::table('shooters', function (Blueprint $table) use ($driver) {
            $col1 = $table->boolean('is_coached')->default(false);
            if ($driver !== 'sqlite') {
                $col1->after('status');
            }
            $col2 = $table->unsignedSmallInteger('gong_position')->nullable();
            if ($driver !== 'sqlite') {
                $col2->after('is_coached');
            }
            $col3 = $table->string('shared_rifle_key', 64)->nullable();
            if ($driver !== 'sqlite') {
                $col3->after('gong_position');
            }
            $table->index(['squad_id', 'shared_rifle_key'], 'shooters_squad_rifle_idx');
        });

        Schema::create('alrha_scoring_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('squad_id')->constrained('squads')->cascadeOnDelete();
            $table->string('type', 24); // hunter_pair | varmint_triple
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('alrha_scoring_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alrha_scoring_group_id')
                ->constrained('alrha_scoring_groups')
                ->cascadeOnDelete();
            // Exactly one of team_id / shooter_id is set per row: teams for
            // hunter_pair groups, shooters for varmint_triple groups. The
            // upstream service enforces the mutual-exclusion invariant.
            $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->foreignId('shooter_id')->nullable()->constrained('shooters')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alrha_scoring_group_members');
        Schema::dropIfExists('alrha_scoring_groups');

        Schema::table('shooters', function (Blueprint $table) {
            $table->dropIndex('shooters_squad_rifle_idx');
            $table->dropColumn(['is_coached', 'gong_position', 'shared_rifle_key']);
        });

        Schema::table('elr_targets', function (Blueprint $table) {
            $table->dropColumn(['is_cold_bore', 'alrha_block']);
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('alrha_class');
        });
    }
};
