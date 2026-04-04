<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_registrations', function (Blueprint $table) {
            $table->foreignId('rifle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ammo_load_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('match_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ammo_load_id');
            $table->dropConstrainedForeignId('rifle_id');
        });
    }
};
