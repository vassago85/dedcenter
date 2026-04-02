<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->string('competition_type', 20)->default('prs')->after('sort_order');
            $table->index('competition_type');
        });
    }

    public function down(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->dropIndex(['competition_type']);
            $table->dropColumn('competition_type');
        });
    }
};
