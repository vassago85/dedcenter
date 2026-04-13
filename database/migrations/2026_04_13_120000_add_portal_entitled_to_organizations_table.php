<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('portal_entitled')->default(false)->after('portal_enabled');
        });

        // Grandfather orgs that already turned the portal on so production keeps working.
        DB::table('organizations')->where('portal_enabled', true)->update(['portal_entitled' => true]);
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('portal_entitled');
        });
    }
};
