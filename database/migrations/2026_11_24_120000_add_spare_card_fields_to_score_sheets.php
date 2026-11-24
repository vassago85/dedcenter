<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anonymous spare cards for walk-ins.
 *
 * A "spare" is a `ScoreSheet` printed ahead of the day with NO shooter
 * attached. The MD photographs it, picks a shooter in the scanner, and
 * on confirm the sheet is bound (`claimed_at` + `shooter_id` set in
 * one UPDATE). Once claimed, a spare is indistinguishable from a
 * pre-assigned card — token lookups return the bound shooter, and
 * re-scans can't rewrite the binding.
 *
 * Because Hunters and Varmint have different distances, a spare's
 * frozen layout is always class-specific. `alrha_class` is set when the
 * spare is printed; for pre-assigned cards it stays NULL (the shooter
 * carries the class).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('score_sheets', function (Blueprint $table) {
            // Spares have no shooter until claimed at scan time. SQLite
            // doesn't support altering foreign-key nullability in-place,
            // so we drop and re-add the FK with the same cascade rule.
            $table->dropForeign(['shooter_id']);
        });

        Schema::table('score_sheets', function (Blueprint $table) {
            $table->foreignId('shooter_id')
                ->nullable()
                ->change()
                ->constrained('shooters')
                ->cascadeOnDelete();

            $table->string('alrha_class', 16)->nullable()->after('shooter_id');
            $table->timestamp('claimed_at')->nullable()->after('printed_at');
        });
    }

    public function down(): void
    {
        Schema::table('score_sheets', function (Blueprint $table) {
            $table->dropColumn(['alrha_class', 'claimed_at']);
            $table->dropForeign(['shooter_id']);
        });

        Schema::table('score_sheets', function (Blueprint $table) {
            $table->foreignId('shooter_id')
                ->nullable(false)
                ->change()
                ->constrained('shooters')
                ->cascadeOnDelete();
        });
    }
};
