<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('target_sets', function (Blueprint $table) {
            $table->integer('stage_number')->nullable()->after('par_time_seconds');
            $table->integer('total_shots')->nullable()->after('stage_number');
            $table->boolean('is_timed_stage')->default(false)->after('total_shots');
            $table->text('notes')->nullable()->after('is_timed_stage');
        });
    }

    public function down(): void
    {
        Schema::table('target_sets', function (Blueprint $table) {
            $table->dropColumn([
                'stage_number',
                'total_shots',
                'is_timed_stage',
                'notes',
            ]);
        });
    }
};
