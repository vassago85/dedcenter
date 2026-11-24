<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Printed ALRHA mark-sense score cards.
 *
 * One row per printed card. The `token` is the only identifier the QR
 * on the paper carries — short and random so the camera doesn't have
 * to resolve the whole payload against the match graph. A reprint of
 * the same shooter gets a new token: the old card still decodes
 * against the layout it was printed with (stored in `layout`), so a
 * shooter who marks an old sheet after a reprint still imports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('shooter_id')->constrained('shooters')->cascadeOnDelete();
            $table->string('token', 32)->unique();
            // Frozen at print time: the list of (elr_target_id, shot_number,
            // label, distance_m, alrha_block, is_cold_bore) rows plus the
            // millimetre positions of the Hit and Miss bubbles on the page.
            // Reading an old sheet never has to re-derive from the live
            // target graph, which can shift between print and scan.
            $table->json('layout');
            $table->unsignedSmallInteger('format_version')->default(1);
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();
            $table->index(['match_id', 'shooter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_sheets');
    }
};
