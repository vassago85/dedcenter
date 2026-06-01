<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->string('elr_engagement_mode', 30)
                ->default('target_by_target')
                ->after('elr_scoring_profile_id');

            // Optional per-station defaults. Null = derive from the configured
            // stage targets. Kept flexible so the format can change later.
            $table->unsignedSmallInteger('elr_targets_per_shooter')
                ->nullable()
                ->after('elr_engagement_mode');

            $table->unsignedSmallInteger('elr_shots_per_target')
                ->default(3)
                ->after('elr_targets_per_shooter');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn([
                'elr_engagement_mode',
                'elr_targets_per_shooter',
                'elr_shots_per_target',
            ]);
        });
    }
};
