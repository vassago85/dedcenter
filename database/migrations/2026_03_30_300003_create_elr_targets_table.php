<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elr_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('elr_stage_id')->constrained('elr_stages')->cascadeOnDelete();
            $table->string('name');
            $table->integer('distance_m');
            $table->decimal('base_points', 8, 2)->default(10.00);
            $table->unsignedSmallInteger('max_shots')->default(3);
            $table->boolean('must_hit_to_advance')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elr_targets');
    }
};
