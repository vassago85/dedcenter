<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elr_shots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooter_id')->constrained('shooters')->cascadeOnDelete();
            $table->foreignId('elr_target_id')->constrained('elr_targets')->cascadeOnDelete();
            $table->unsignedSmallInteger('shot_number');
            $table->string('result', 20);
            $table->decimal('points_awarded', 8, 2)->default(0);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('device_id')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['shooter_id', 'elr_target_id', 'shot_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elr_shots');
    }
};
