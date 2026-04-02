<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_equipment_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('caliber')->nullable();
            $table->string('action_brand')->nullable();
            $table->string('bullet_brand_type')->nullable();
            $table->string('bullet_weight')->nullable();
            $table->string('barrel_brand_length')->nullable();
            $table->string('trigger_brand')->nullable();
            $table->string('stock_chassis_brand')->nullable();
            $table->string('muzzle_brake_silencer_brand')->nullable();
            $table->string('scope_brand_type')->nullable();
            $table->string('scope_mount_brand')->nullable();
            $table->string('bipod_brand')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::table('match_registrations', function (Blueprint $table) {
            $table->foreignId('equipment_profile_id')->nullable()->after('contact_number')
                ->constrained('user_equipment_profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('match_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_profile_id');
        });

        Schema::dropIfExists('user_equipment_profiles');
    }
};
