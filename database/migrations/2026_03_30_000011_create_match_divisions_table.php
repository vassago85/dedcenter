<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_divisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('shooters', function (Blueprint $table) {
            $table->foreignId('match_division_id')->nullable()->constrained('match_divisions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shooters', function (Blueprint $table) {
            $table->dropForeign(['match_division_id']);
            $table->dropColumn('match_division_id');
        });

        Schema::dropIfExists('match_divisions');
    }
};
