<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('payment_reference')->unique();
            $table->string('payment_status')->default('pending_payment');
            $table->string('proof_of_payment_path')->nullable();
            $table->decimal('amount', 8, 2)->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_registrations');
    }
};
