<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('target_sets', function (Blueprint $table) {
            $table->boolean('is_tiebreaker')->default(false)->after('sort_order');
            $table->decimal('par_time_seconds', 8, 2)->nullable()->after('is_tiebreaker');
        });
    }

    public function down(): void
    {
        Schema::table('target_sets', function (Blueprint $table) {
            $table->dropColumn(['is_tiebreaker', 'par_time_seconds']);
        });
    }
};
