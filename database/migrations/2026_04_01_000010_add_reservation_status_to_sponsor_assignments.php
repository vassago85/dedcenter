<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsor_assignments', function (Blueprint $table) {
            $table->string('reservation_status')->default('open')->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('sponsor_assignments', function (Blueprint $table) {
            $table->dropColumn('reservation_status');
        });
    }
};
