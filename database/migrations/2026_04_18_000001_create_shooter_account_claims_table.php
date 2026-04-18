<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shooter_account_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooter_id')->constrained('shooters')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('match_id')->nullable()->constrained('matches')->nullOnDelete();

            // pending | approved | rejected | withdrawn
            $table->string('status', 20)->default('pending')->index();
            $table->text('evidence')->nullable();

            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_note')->nullable();

            $table->timestamps();

            $table->index(['shooter_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shooter_account_claims');
    }
};
