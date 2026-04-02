<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disqualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('shooter_id')->constrained('shooters')->cascadeOnDelete();
            $table->foreignId('target_set_id')->nullable()->constrained('target_sets')->cascadeOnDelete();
            $table->text('reason');
            $table->foreignId('issued_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['shooter_id', 'match_id', 'target_set_id'], 'dq_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disqualifications');
    }
};
