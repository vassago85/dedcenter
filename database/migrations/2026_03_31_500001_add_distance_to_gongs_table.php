<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gongs', function (Blueprint $table) {
            $table->integer('distance_meters')->nullable()->after('label');
            $table->string('target_size')->nullable()->after('distance_meters');
        });
    }

    public function down(): void
    {
        Schema::table('gongs', function (Blueprint $table) {
            $table->dropColumn(['distance_meters', 'target_size']);
        });
    }
};
