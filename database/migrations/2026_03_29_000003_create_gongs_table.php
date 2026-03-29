<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gongs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_set_id')->constrained('target_sets')->cascadeOnDelete();
            $table->integer('number');
            $table->string('label')->nullable();
            $table->decimal('multiplier', 5, 2)->default(1.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gongs');
    }
};
