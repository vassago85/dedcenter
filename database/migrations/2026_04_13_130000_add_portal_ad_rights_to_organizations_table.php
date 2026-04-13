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
            $table->boolean('portal_ad_rights')->default(false)->after('portal_entitled');
        });

        // Preserve sponsor-control entitlement for orgs that previously had paid portal access.
        DB::table('organizations')->where('portal_entitled', true)->update(['portal_ad_rights' => true]);
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('portal_ad_rights');
        });
    }
};
