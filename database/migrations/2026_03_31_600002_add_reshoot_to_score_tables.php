<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scores', function (Blueprint $table) {
            $table->boolean('is_reshoot')->default(false)->after('is_hit');
            $table->text('reshoot_reason')->nullable()->after('is_reshoot');
        });

        Schema::table('prs_stage_results', function (Blueprint $table) {
            $table->boolean('is_reshoot')->default(false)->after('official_time_seconds');
            $table->text('reshoot_reason')->nullable()->after('is_reshoot');
        });

        Schema::table('prs_shot_scores', function (Blueprint $table) {
            $table->boolean('is_reshoot')->default(false)->after('result');
            $table->text('reshoot_reason')->nullable()->after('is_reshoot');
        });

        Schema::table('elr_shots', function (Blueprint $table) {
            $table->boolean('is_reshoot')->default(false)->after('points_awarded');
            $table->text('reshoot_reason')->nullable()->after('is_reshoot');
        });
    }

    public function down(): void
    {
        Schema::table('scores', function (Blueprint $table) {
            $table->dropColumn(['is_reshoot', 'reshoot_reason']);
        });

        Schema::table('prs_stage_results', function (Blueprint $table) {
            $table->dropColumn(['is_reshoot', 'reshoot_reason']);
        });

        Schema::table('prs_shot_scores', function (Blueprint $table) {
            $table->dropColumn(['is_reshoot', 'reshoot_reason']);
        });

        Schema::table('elr_shots', function (Blueprint $table) {
            $table->dropColumn(['is_reshoot', 'reshoot_reason']);
        });
    }
};
