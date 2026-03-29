<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@deadcenter.co.za'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => 'owner',
            ]
        );

        User::firstOrCreate(
            ['email' => 'paul@charsley.co.za'],
            [
                'name' => 'Paul Charsley',
                'password' => Hash::make('password'),
                'role' => 'owner',
            ]
        );

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'role' => 'shooter',
            ]
        );

        $this->call(SettingsSeeder::class);

        $royalFlush = Organization::firstOrCreate(
            ['slug' => 'royal-flush'],
            [
                'name' => 'Royal Flush',
                'description' => 'Year-long precision shooting competition. Compete across multiple matches to claim the top spot on the leaderboard.',
                'type' => 'competition',
                'status' => 'approved',
                'created_by' => $admin->id,
                'primary_color' => '#b91c1c',
                'secondary_color' => '#0f172a',
                'hero_text' => 'Royal Flush 2026',
                'hero_description' => 'The ultimate year-long precision shooting competition. Register for matches, submit your scores, and climb the leaderboard.',
                'portal_enabled' => true,
                'best_of' => 5,
            ]
        );

        $royalFlush->admins()->syncWithoutDetaching([
            $admin->id => ['role' => 'owner'],
        ]);
    }
}
