<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->json('registration_fields_config')->nullable();
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->json('default_registration_fields_config')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('registration_fields_config');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('default_registration_fields_config');
        });
    }
};
