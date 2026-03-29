<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->string('scoring_type', 20)->default('standard')->after('status');
        });

        Schema::create('stage_times', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooter_id')->constrained('shooters')->cascadeOnDelete();
            $table->foreignId('target_set_id')->constrained('target_sets')->cascadeOnDelete();
            $table->decimal('time_seconds', 8, 2);
            $table->string('device_id')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['shooter_id', 'target_set_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_times');

        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('scoring_type');
        });
    }
};
