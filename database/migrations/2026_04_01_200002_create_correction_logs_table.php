<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correction_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained('target_sets')->cascadeOnDelete();
            $table->foreignId('shooter_id')->constrained('shooters')->cascadeOnDelete();
            $table->string('action', 30);
            $table->json('details')->nullable();
            $table->string('device_id')->nullable();
            $table->timestamp('performed_at')->nullable();
            $table->timestamps();

            $table->index(['match_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correction_logs');
    }
};
