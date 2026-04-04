<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_registrations', function (Blueprint $table) {
            $table->foreignId('division_id')->nullable()->constrained('match_divisions')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('match_categories')->nullOnDelete();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_number')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('match_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('division_id');
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['emergency_contact_name', 'emergency_contact_number']);
        });
    }
};
