<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gongs', function (Blueprint $table) {
            $table->decimal('target_size_mm', 8, 2)->nullable()->after('target_size');
        });
    }

    public function down(): void
    {
        Schema::table('gongs', function (Blueprint $table) {
            $table->dropColumn('target_size_mm');
        });
    }
};
