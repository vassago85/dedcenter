<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('primary_color', 7)->default('#dc2626')->after('logo_path');
            $table->string('secondary_color', 7)->default('#1e293b')->after('primary_color');
            $table->string('hero_text')->nullable()->after('secondary_color');
            $table->text('hero_description')->nullable()->after('hero_text');
            $table->boolean('portal_enabled')->default(false)->after('hero_description');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['primary_color', 'secondary_color', 'hero_text', 'hero_description', 'portal_enabled']);
        });
    }
};
