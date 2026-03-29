<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('created_by')->constrained('organizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
