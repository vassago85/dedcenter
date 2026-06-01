<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-match, per-division target eligibility.
 *
 * The Peregrine ELR Challenge runs Minor and Major on the SAME stations but
 * with different target subsets — Minor engages T1–T3, Major engages T2–T4.
 * Storing this as a pivot rather than enum / boolean on the target lets a
 * match director arbitrarily configure any division to shoot any subset
 * (and lets us support 3+ divisions later without schema churn).
 *
 * When no row exists for a (division, stage) pair the engine treats it as
 * "this division shoots every target" — i.e. the legacy default so matches
 * that don't bother configuring this still work.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elr_division_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_division_id')->constrained('match_divisions')->cascadeOnDelete();
            $table->foreignId('elr_target_id')->constrained('elr_targets')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['match_division_id', 'elr_target_id'], 'elr_div_target_unique');
            $table->index('elr_target_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elr_division_targets');
    }
};
