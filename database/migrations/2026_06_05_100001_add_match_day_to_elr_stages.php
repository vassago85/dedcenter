<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('elr_stages', function (Blueprint $table) {
            $table->unsignedTinyInteger('match_day')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('elr_stages', function (Blueprint $table) {
            $table->dropColumn('match_day');
        });
    }
};
