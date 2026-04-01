<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->string('website_url')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->text('short_description')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('assignable_by_match_director')->default(true);
            $table->text('internal_notes')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sponsor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type'); // platform, match, matchbook
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('placement_key')->index();
            $table->string('label_override')->nullable();
            $table->boolean('active')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['scope_type', 'scope_id', 'placement_key', 'sponsor_id'], 'sponsor_assignment_unique');
            $table->index(['scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsor_assignments');
        Schema::dropIfExists('sponsors');
    }
};
