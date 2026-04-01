<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedInteger('max_squad_size')->nullable()->after('concurrent_relays');
        });

        Schema::table('match_registrations', function (Blueprint $table) {
            $table->timestamp('pre_registered_at')->nullable()->after('is_free_entry');
        });

        Schema::table('squads', function (Blueprint $table) {
            $table->unsignedInteger('max_capacity')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('max_squad_size');
        });
        Schema::table('match_registrations', function (Blueprint $table) {
            $table->dropColumn('pre_registered_at');
        });
        Schema::table('squads', function (Blueprint $table) {
            $table->dropColumn('max_capacity');
        });
    }
};
