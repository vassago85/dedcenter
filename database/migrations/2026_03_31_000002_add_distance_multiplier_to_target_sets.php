<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('target_sets', function (Blueprint $table) {
            $table->decimal('distance_multiplier', 5, 2)->default(1.00)->after('distance_meters');
        });
    }

    public function down(): void
    {
        Schema::table('target_sets', function (Blueprint $table) {
            $table->dropColumn('distance_multiplier');
        });
    }
};
