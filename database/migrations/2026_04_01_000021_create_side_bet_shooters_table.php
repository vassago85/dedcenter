<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('side_bet_shooters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('shooter_id')->constrained('shooters')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['match_id', 'shooter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('side_bet_shooters');
    }
};
