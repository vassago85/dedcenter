<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedTinyInteger('match_days')->default(1);
            $table->foreignId('parent_match_id')->nullable()->constrained('matches')->nullOnDelete();
        });

        Schema::table('target_sets', function (Blueprint $table) {
            $table->unsignedTinyInteger('day_number')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_match_id');
            $table->dropColumn('match_days');
        });

        Schema::table('target_sets', function (Blueprint $table) {
            $table->dropColumn('day_number');
        });
    }
};
