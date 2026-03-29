<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'admin')->update(['role' => 'owner']);
        DB::table('users')->where('role', 'member')->update(['role' => 'shooter']);
        DB::table('organization_admins')->where('role', 'admin')->update(['role' => 'match_director']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'owner')->update(['role' => 'admin']);
        DB::table('users')->where('role', 'shooter')->update(['role' => 'member']);
        DB::table('organization_admins')->where('role', 'match_director')->update(['role' => 'admin']);
    }
};
