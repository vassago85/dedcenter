<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('featured_items', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->unsignedBigInteger('item_id');
            $table->string('placement');
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['type', 'placement', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('featured_items');
    }
};
