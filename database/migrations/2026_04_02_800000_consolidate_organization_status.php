<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Collapse 'approved' and 'active' into 'active', rename 'archived' to 'inactive'.
 * Final org statuses: pending, active, inactive.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('organizations')->where('status', 'approved')->update(['status' => 'active']);
        DB::table('organizations')->where('status', 'archived')->update(['status' => 'inactive']);
    }

    public function down(): void
    {
        DB::table('organizations')->where('status', 'inactive')->update(['status' => 'archived']);
    }
};
