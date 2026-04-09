<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_shot_sequence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('target_sets')->cascadeOnDelete();
            $table->unsignedInteger('shot_number');
            $table->foreignId('position_id')->constrained('stage_positions')->cascadeOnDelete();
            $table->foreignId('gong_id')->constrained('gongs')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_shot_sequence');
    }
};
