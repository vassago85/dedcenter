<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_admins', function (Blueprint $table) {
            $table->boolean('is_owner')->default(false)->after('role');
            $table->boolean('is_match_director')->default(false)->after('is_owner');
            $table->boolean('is_range_officer')->default(false)->after('is_match_director');
            $table->boolean('is_shooter')->default(false)->after('is_range_officer');
        });

        DB::table('organization_admins')->where('role', 'owner')->update(['is_owner' => true]);
        DB::table('organization_admins')->where('role', 'match_director')->update(['is_match_director' => true]);
        DB::table('organization_admins')->where('role', 'range_officer')->update(['is_range_officer' => true]);

        Schema::table('organization_admins', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('organization_admins', function (Blueprint $table) {
            $table->string('role')->default('match_director')->after('user_id');
        });

        DB::table('organization_admins')->where('is_owner', true)->update(['role' => 'owner']);
        DB::table('organization_admins')->where('is_match_director', true)->where('is_owner', false)->update(['role' => 'match_director']);
        DB::table('organization_admins')->where('is_range_officer', true)->where('is_owner', false)->where('is_match_director', false)->update(['role' => 'range_officer']);
        DB::table('organization_admins')->where('is_owner', false)->where('is_match_director', false)->where('is_range_officer', false)->update(['role' => 'match_director']);

        Schema::table('organization_admins', function (Blueprint $table) {
            $table->dropColumn(['is_owner', 'is_match_director', 'is_range_officer', 'is_shooter']);
        });
    }
};
