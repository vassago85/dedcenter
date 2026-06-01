<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peregrine ELR Challenge style matches treat each ELR stage as a sponsored
 * shooting "station" (Warrior / Brothers Arms / Integrix / Delta / Zeiss).
 * The match director needs to brand each station independently — sponsor name
 * for placards/PDFs, colour for the on-tablet header and printed scorecard.
 *
 * Both columns are nullable so non-Peregrine ELR matches (Forster 2-Mile etc.)
 * keep rendering with just a label. `color` is stored as free text so it can
 * accept either a hex (#F97316) or a token name ("orange") depending on what
 * the UI chooses — the Vue PWA and the PDF blade decide how to interpret.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('elr_stages', function (Blueprint $table) {
            $table->string('sponsor')->nullable()->after('label');
            $table->string('color', 32)->nullable()->after('sponsor');
        });
    }

    public function down(): void
    {
        Schema::table('elr_stages', function (Blueprint $table) {
            $table->dropColumn(['sponsor', 'color']);
        });
    }
};
