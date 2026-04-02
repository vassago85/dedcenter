<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->string('label');
            $table->string('type')->default('text'); // text, number, select, checkbox
            $table->json('options')->nullable(); // for select type
            $table->boolean('is_required')->default(false);
            $table->boolean('show_on_scoreboard')->default(false);
            $table->boolean('show_on_results')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('match_id');
        });

        Schema::create('match_registration_custom_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_registration_id')->constrained('match_registrations')->cascadeOnDelete();
            $table->foreignId('match_custom_field_id')->constrained('match_custom_fields')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['match_registration_id', 'match_custom_field_id'], 'reg_custom_field_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_registration_custom_values');
        Schema::dropIfExists('match_custom_fields');
    }
};
