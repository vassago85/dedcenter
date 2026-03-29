<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type'); // league, club, competition, challenge
            $table->foreignId('parent_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('status')->default('pending'); // pending, approved, active, archived
            $table->foreignId('created_by')->constrained('users');
            $table->string('logo_path')->nullable();
            $table->unsignedInteger('best_of')->nullable();
            $table->decimal('entry_fee_default', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
