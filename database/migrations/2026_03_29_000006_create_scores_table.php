<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooter_id')->constrained('shooters')->cascadeOnDelete();
            $table->foreignId('gong_id')->constrained('gongs')->cascadeOnDelete();
            $table->boolean('is_hit')->default(false);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('device_id')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['shooter_id', 'gong_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
    }
};
