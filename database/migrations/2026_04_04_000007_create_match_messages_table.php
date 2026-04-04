<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by')->constrained('users')->cascadeOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->string('audience', 50)->default('all');
            $table->foreignId('squad_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('match_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_messages');
    }
};
