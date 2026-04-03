<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->boolean('self_squadding_enabled')->default(true)->after('featured_until');
            $table->boolean('team_event')->default(false)->after('self_squadding_enabled');
            $table->unsignedInteger('team_size')->default(3)->after('team_event');
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('max_size')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('shooters', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('match_division_id')
                ->constrained('teams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shooters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });

        Schema::dropIfExists('teams');

        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['self_squadding_enabled', 'team_event', 'team_size']);
        });
    }
};
