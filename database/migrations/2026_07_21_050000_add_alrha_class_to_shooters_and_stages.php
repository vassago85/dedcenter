<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Move ALRHA class from match-level to shooter/registration/stage-level so
 * one match can run Hunters and Varmint concurrently on shared relays.
 *
 * A single ALRHA event fires both classes at the same time on the same
 * range — a shooter simply picks one class at entry. The initial schema
 * assumed one class per match; this migration adds the per-shooter class
 * (source of truth once squadded), the per-registration class (chosen at
 * entry, copied to shooter), and a stage-level class tag so the ELR stage
 * tree can hold both class trees side-by-side.
 *
 * `matches.alrha_class` is kept as-is for back-compat with existing
 * single-class matches (e.g. seed match #50). It becomes optional: any
 * match whose stages carry `alrha_class` tags is treated as dual-class.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('shooters', function (Blueprint $table) use ($driver) {
            $col = $table->string('alrha_class', 16)->nullable();
            if ($driver !== 'sqlite') {
                $col->after('shared_rifle_key');
            }
        });

        Schema::table('match_registrations', function (Blueprint $table) use ($driver) {
            $col = $table->string('alrha_class', 16)->nullable();
            if ($driver !== 'sqlite') {
                $col->after('category_id');
            }
        });

        Schema::table('elr_stages', function (Blueprint $table) use ($driver) {
            $col = $table->string('alrha_class', 16)->nullable();
            if ($driver !== 'sqlite') {
                $col->after('sort_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('elr_stages', function (Blueprint $table) {
            $table->dropColumn('alrha_class');
        });

        Schema::table('match_registrations', function (Blueprint $table) {
            $table->dropColumn('alrha_class');
        });

        Schema::table('shooters', function (Blueprint $table) {
            $table->dropColumn('alrha_class');
        });
    }
};
