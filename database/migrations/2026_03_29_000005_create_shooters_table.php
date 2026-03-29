<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shooters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('squad_id')->constrained('squads')->cascadeOnDelete();
            $table->string('name');
            $table->string('bib_number')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shooters');
    }
};
