<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $profiles = DB::table('user_equipment_profiles')->get();

        foreach ($profiles as $profile) {
            $rifleId = DB::table('rifles')->insertGetId([
                'user_id' => $profile->user_id,
                'name' => $profile->name,
                'caliber' => $profile->caliber,
                'action_brand' => $profile->action_brand,
                'barrel_brand_length' => $profile->barrel_brand_length,
                'trigger_brand' => $profile->trigger_brand,
                'stock_chassis_brand' => $profile->stock_chassis_brand,
                'muzzle_brake_silencer_brand' => $profile->muzzle_brake_silencer_brand,
                'scope_brand_type' => $profile->scope_brand_type,
                'scope_mount_brand' => $profile->scope_mount_brand,
                'bipod_brand' => $profile->bipod_brand,
                'is_default' => $profile->is_default,
                'created_at' => $profile->created_at,
                'updated_at' => $profile->updated_at,
            ]);

            if ($profile->bullet_brand_type !== null || $profile->bullet_weight !== null) {
                DB::table('ammo_loads')->insert([
                    'rifle_id' => $rifleId,
                    'name' => 'Default Load',
                    'bullet_brand_type' => $profile->bullet_brand_type,
                    'bullet_weight' => $profile->bullet_weight,
                    'is_default' => true,
                    'created_at' => $profile->created_at,
                    'updated_at' => $profile->updated_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        // This migration is irreversible — equipment data was split across rifles and ammo_loads.
    }
};
