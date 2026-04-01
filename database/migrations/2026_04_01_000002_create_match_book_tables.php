<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->unique()->constrained('matches')->cascadeOnDelete();
            $table->string('subtitle')->nullable();
            $table->string('cover_image_path')->nullable();

            // Venue / location
            $table->string('venue')->nullable();
            $table->string('gps_coordinates')->nullable();
            $table->string('venue_maps_link')->nullable();
            $table->string('range_maps_link')->nullable();
            $table->string('hospital_maps_link')->nullable();
            $table->text('directions')->nullable();

            // Match director / emergency
            $table->string('match_director_name')->nullable();
            $table->string('match_director_phone')->nullable();
            $table->string('match_director_email')->nullable();
            $table->string('emergency_hospital_name')->nullable();
            $table->string('emergency_hospital_address')->nullable();
            $table->string('emergency_phone')->nullable();

            // Content sections
            $table->text('program')->nullable();
            $table->text('procedures')->nullable();
            $table->text('safety')->nullable();
            $table->json('timetable')->nullable();
            $table->text('match_breakdown')->nullable();
            $table->text('welcome_note')->nullable();
            $table->text('custom_notes')->nullable();
            $table->text('sponsor_acknowledgement')->nullable();

            // Branding
            $table->string('primary_color')->nullable()->default('#1e3a5f');
            $table->string('secondary_color')->nullable();
            $table->string('accent_color')->nullable();
            $table->string('text_color')->nullable();
            $table->string('highlight_color')->nullable();

            // Output options
            $table->boolean('include_summary_cards')->default(true);
            $table->boolean('include_dope_card')->default(false);
            $table->boolean('include_score_sheet')->default(true);
            $table->string('match_type')->nullable(); // centerfire, rimfire

            // Logos
            $table->string('federation_logo_path')->nullable();
            $table->string('club_logo_path')->nullable();

            // Status
            $table->string('status')->default('draft'); // draft, ready, published
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('match_book_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_book_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('maps_link')->nullable();
            $table->string('gps_coordinates')->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('match_book_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_book_id')->constrained()->cascadeOnDelete();
            $table->integer('stage_number');
            $table->string('name');
            $table->text('brief')->nullable();
            $table->text('notes')->nullable();
            $table->text('engagement_rules')->nullable();
            $table->boolean('compulsory_sequence')->default(true);
            $table->boolean('timed')->default(false);
            $table->integer('time_limit')->nullable();
            $table->integer('round_count')->nullable();
            $table->integer('positions_count')->nullable();
            $table->integer('movement_meters')->default(0);
            $table->string('prop_image_path')->nullable();
            $table->string('sequence_display_format')->default('blocks'); // blocks, table
            $table->timestamps();
        });

        Schema::create('match_book_shots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_book_stage_id')->constrained()->cascadeOnDelete();
            $table->integer('shot_number');
            $table->integer('position');
            $table->string('gong_label');
            $table->string('gong_name')->nullable();
            $table->decimal('distance_m', 8, 2);
            $table->integer('size_mm')->nullable();
            $table->string('shape')->nullable();
            $table->decimal('mil', 6, 2)->nullable();
            $table->decimal('moa', 6, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_book_shots');
        Schema::dropIfExists('match_book_stages');
        Schema::dropIfExists('match_book_locations');
        Schema::dropIfExists('match_books');
    }
};
