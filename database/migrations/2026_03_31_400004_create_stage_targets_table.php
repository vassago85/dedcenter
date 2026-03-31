<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('target_sets')->cascadeOnDelete();
            $table->unsignedInteger('sequence_number');
            $table->string('target_name')->nullable();
            $table->string('target_reference')->nullable();
            $table->unsignedInteger('distance_meters')->nullable();
            $table->decimal('target_size_mm', 8, 2)->nullable();
            $table->decimal('target_size_mrad', 6, 3)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_targets');
    }
};
