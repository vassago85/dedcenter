<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elr_scoring_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->nullable()->constrained('matches')->cascadeOnDelete();
            $table->string('name');
            $table->json('multipliers');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elr_scoring_profiles');
    }
};
