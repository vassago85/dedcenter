<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('match_category_shooter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_category_id')->constrained('match_categories')->cascadeOnDelete();
            $table->foreignId('shooter_id')->constrained('shooters')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['match_category_id', 'shooter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_category_shooter');
        Schema::dropIfExists('match_categories');
    }
};
