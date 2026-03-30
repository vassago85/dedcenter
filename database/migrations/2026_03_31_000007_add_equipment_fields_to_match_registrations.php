<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_registrations', function (Blueprint $table) {
            $table->string('sa_id_number')->nullable()->after('admin_notes');
            $table->string('caliber')->nullable()->after('sa_id_number');
            $table->string('bullet_brand_type')->nullable()->after('caliber');
            $table->string('bullet_weight')->nullable()->after('bullet_brand_type');
            $table->string('action_brand')->nullable()->after('bullet_weight');
            $table->string('barrel_brand_length')->nullable()->after('action_brand');
            $table->string('trigger_brand')->nullable()->after('barrel_brand_length');
            $table->string('stock_chassis_brand')->nullable()->after('trigger_brand');
            $table->string('muzzle_brake_silencer_brand')->nullable()->after('stock_chassis_brand');
            $table->string('scope_brand_type')->nullable()->after('muzzle_brake_silencer_brand');
            $table->string('scope_mount_brand')->nullable()->after('scope_brand_type');
            $table->string('bipod_brand')->nullable()->after('scope_mount_brand');
            $table->string('share_rifle_with')->nullable()->after('bipod_brand');
            $table->string('contact_number')->nullable()->after('share_rifle_with');
            $table->boolean('is_free_entry')->default(false)->after('contact_number');
        });
    }

    public function down(): void
    {
        Schema::table('match_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'sa_id_number', 'caliber', 'bullet_brand_type', 'bullet_weight',
                'action_brand', 'barrel_brand_length', 'trigger_brand',
                'stock_chassis_brand', 'muzzle_brake_silencer_brand',
                'scope_brand_type', 'scope_mount_brand', 'bipod_brand',
                'share_rifle_with', 'contact_number', 'is_free_entry',
            ]);
        });
    }
};
