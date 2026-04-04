<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ammo_loads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rifle_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('bullet_brand_type')->nullable();
            $table->string('bullet_weight', 100)->nullable();
            $table->string('muzzle_velocity', 100)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('rifle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ammo_loads');
    }
};
