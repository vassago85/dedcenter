<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('label', 100);
            $table->text('description');
            $table->enum('category', ['repeatable', 'lifetime', 'match_special']);
            $table->enum('scope', ['stage', 'match', 'lifetime']);
            $table->boolean('is_repeatable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('achievement_id')->constrained('achievements')->cascadeOnDelete();
            $table->foreignId('match_id')->nullable()->constrained('matches')->cascadeOnDelete();
            $table->unsignedBigInteger('stage_id')->nullable();
            $table->unsignedBigInteger('shooter_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('awarded_at')->useCurrent();

            $table->foreign('stage_id')->references('id')->on('target_sets')->nullOnDelete();
            $table->foreign('shooter_id')->references('id')->on('shooters')->nullOnDelete();

            $table->index(['user_id', 'achievement_id']);
            $table->index(['match_id', 'achievement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievements');
    }
};
