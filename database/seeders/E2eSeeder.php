<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data set for the Playwright suite in tests/e2e. Run against a throwaway
 * database only: php artisan migrate:fresh --seed --seeder=E2eSeeder
 */
class E2eSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DatabaseSeeder::class,
            AlrhaDwandzani20260815Seeder::class,
            RoyalFlushCompletedSeeder::class,
            LiveDemoMatchSeeder::class,
        ]);

        User::query()->whereNull('email_verified_at')->update(['email_verified_at' => now()]);

        $orgAdmin = User::firstOrCreate(
            ['email' => 'orgadmin@e2e.test'],
            ['name' => 'Org Admin', 'password' => Hash::make('password'), 'role' => 'shooter'],
        );
        $orgAdmin->forceFill(['email_verified_at' => now()])->save();
        Organization::where('slug', 'royal-flush')->firstOrFail()
            ->admins()->syncWithoutDetaching([$orgAdmin->id => ['is_owner' => true]]);

        User::where('email', 'rf-demo-1@deadcenter.test')->firstOrFail()
            ->forceFill(['password' => Hash::make('password')])->save();

        User::firstOrCreate(
            ['email' => 'reset@e2e.test'],
            ['name' => 'Reset Shooter', 'password' => Hash::make('password'), 'role' => 'shooter'],
        )->forceFill(['email_verified_at' => now()])->save();

        Setting::set('sponsor_info_access_token', 'e2e-sponsor-token');
    }
}
