<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('season_standings_enabled')->default(true)->after('bank_branch_code');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->text('public_bio')->nullable()->after('notes');
        });

        Schema::create('match_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role'); // match_director, range_officer
            $table->timestamps();

            $table->unique(['match_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_staff');

        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('public_bio');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('season_standings_enabled');
        });
    }
};
