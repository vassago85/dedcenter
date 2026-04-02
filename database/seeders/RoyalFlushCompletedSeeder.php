<?php

namespace Database\Seeders;

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\Organization;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Database\Seeder;

class RoyalFlushCompletedSeeder extends Seeder
{
    public function run(): void
    {
        $paul = User::where('email', 'paul@charsley.co.za')->first()
            ?? User::where('role', 'owner')->firstOrFail();

        $org = Organization::where('slug', 'royal-flush')->first();
        if (! $org) {
            $this->command->error('Royal Flush organization not found. Run DatabaseSeeder first.');
            return;
        }

        $match = ShootingMatch::updateOrCreate(
            ['name' => 'Royal Flush — April Completed', 'organization_id' => $org->id],
            [
                'date' => now()->subDays(3)->toDateString(),
                'location' => 'Dullstroom Range',
                'status' => MatchStatus::Completed,
                'scoring_type' => 'standard',
                'royal_flush_enabled' => true,
                'side_bet_enabled' => true,
                'scores_published' => true,
                'notes' => 'Seeded completed Royal Flush match with scores and badges.',
                'created_by' => $paul->id,
                'entry_fee' => 200.00,
            ]
        );

        $distances = [
            ['label' => '400m Bank', 'distance' => 400, 'gongs' => [
                ['label' => '2.5 MOA', 'mult' => 1.00],
                ['label' => '2.0 MOA', 'mult' => 1.25],
                ['label' => '1.5 MOA', 'mult' => 1.50],
                ['label' => '1.0 MOA', 'mult' => 2.00],
                ['label' => '0.5 MOA', 'mult' => 3.00],
            ]],
            ['label' => '500m Bank', 'distance' => 500, 'gongs' => [
                ['label' => '2.5 MOA', 'mult' => 1.00],
                ['label' => '2.0 MOA', 'mult' => 1.25],
                ['label' => '1.5 MOA', 'mult' => 1.50],
                ['label' => '1.0 MOA', 'mult' => 2.00],
                ['label' => '0.5 MOA', 'mult' => 3.00],
            ]],
            ['label' => '600m Bank', 'distance' => 600, 'gongs' => [
                ['label' => '2.5 MOA', 'mult' => 1.00],
                ['label' => '2.0 MOA', 'mult' => 1.50],
                ['label' => '1.5 MOA', 'mult' => 2.00],
                ['label' => '1.0 MOA', 'mult' => 2.50],
                ['label' => '0.5 MOA', 'mult' => 4.00],
            ]],
            ['label' => '700m Bank', 'distance' => 700, 'gongs' => [
                ['label' => '2.5 MOA', 'mult' => 1.25],
                ['label' => '2.0 MOA', 'mult' => 1.75],
                ['label' => '1.5 MOA', 'mult' => 2.50],
                ['label' => '1.0 MOA', 'mult' => 3.50],
                ['label' => '0.5 MOA', 'mult' => 5.00],
            ]],
        ];

        $targetSets = [];
        $allGongs = [];
        foreach ($distances as $i => $bank) {
            $ts = TargetSet::firstOrCreate(
                ['match_id' => $match->id, 'label' => $bank['label']],
                ['distance_meters' => $bank['distance'], 'distance_multiplier' => $bank['distance'] / 100, 'sort_order' => $i + 1, 'is_tiebreaker' => false]
            );
            $targetSets[] = $ts;

            foreach ($bank['gongs'] as $j => $g) {
                $gong = Gong::firstOrCreate(
                    ['target_set_id' => $ts->id, 'number' => $j + 1],
                    ['label' => $g['label'], 'multiplier' => $g['mult']]
                );
                $allGongs[$ts->id][] = $gong;
            }
        }

        $shooterData = [
            ['name' => 'Gerrit Smit',        'hits' => [[1,1,1,1,1], [1,1,1,1,1], [1,1,1,1,0], [1,1,1,0,0]]],
            ['name' => 'Johan Havenga',       'hits' => [[1,1,1,1,1], [1,1,1,1,0], [1,1,1,0,0], [1,1,0,0,0]]],
            ['name' => 'Willie Pieterse',     'hits' => [[1,1,1,1,0], [1,1,1,1,1], [1,1,0,0,0], [1,1,1,0,0]]],
            ['name' => 'Bertus Naude',        'hits' => [[1,1,1,0,0], [1,1,1,1,0], [1,1,1,0,0], [1,0,0,0,0]]],
            ['name' => 'Etienne Gouws',       'hits' => [[1,1,1,1,0], [1,1,0,0,0], [1,1,1,0,0], [1,1,0,0,0]]],
            ['name' => 'Deon van Rensburg',   'hits' => [[1,1,1,0,0], [1,1,1,0,0], [1,1,0,0,0], [1,1,0,0,0]]],
            ['name' => 'Izak Delport',        'hits' => [[1,1,0,0,0], [1,1,1,1,0], [1,1,0,0,0], [1,1,0,0,0]]],
            ['name' => 'Nico Swanepoel',      'hits' => [[1,1,1,0,0], [1,1,0,0,0], [1,0,0,0,0], [1,1,0,0,0]]],
            ['name' => 'Bennie Klopper',      'hits' => [[1,1,0,0,0], [1,0,0,0,0], [1,1,0,0,0], [1,0,0,0,0]]],
            ['name' => 'Marthinus Louw',      'hits' => [[1,0,0,0,0], [1,1,0,0,0], [1,0,0,0,0], [1,1,0,0,0]]],
        ];

        $relay = Squad::firstOrCreate(
            ['match_id' => $match->id, 'name' => 'Relay 1'],
            ['sort_order' => 1]
        );

        $sideBetShooterIds = [];
        $now = now()->toDateTimeString();

        foreach ($shooterData as $si => $sd) {
            $userId = null;
            if ($sd['name'] === 'Paul Charsley' || ($si === 0 && $paul)) {
                $userId = ($sd['name'] === 'Paul Charsley') ? $paul->id : null;
            }

            $testUser = User::firstOrCreate(
                ['email' => 'rf-demo-' . ($si + 1) . '@deadcenter.test'],
                [
                    'name' => $sd['name'],
                    'password' => bcrypt('password'),
                    'role' => 'shooter',
                    'email_verified_at' => now(),
                ]
            );

            $shooter = Shooter::firstOrCreate(
                ['squad_id' => $relay->id, 'name' => $sd['name']],
                [
                    'bib_number' => str_pad($si + 1, 3, '0', STR_PAD_LEFT),
                    'user_id' => $testUser->id,
                    'sort_order' => $si + 1,
                ]
            );

            if ($si < 6) {
                $sideBetShooterIds[] = $shooter->id;
            }

            Score::where('shooter_id', $shooter->id)
                ->whereIn('gong_id', collect($allGongs)->flatten()->pluck('id'))
                ->delete();

            foreach ($targetSets as $tsIdx => $ts) {
                $hitPattern = $sd['hits'][$tsIdx] ?? [];
                foreach ($allGongs[$ts->id] as $gIdx => $gong) {
                    $isHit = $hitPattern[$gIdx] ?? 0;
                    Score::create([
                        'shooter_id' => $shooter->id,
                        'gong_id' => $gong->id,
                        'is_hit' => (bool) $isHit,
                        'device_id' => 'seeder',
                        'recorded_at' => $now,
                        'synced_at' => $now,
                    ]);
                }
            }
        }

        $match->sideBetShooters()->sync($sideBetShooterIds);

        try {
            AchievementService::evaluateRoyalFlushCompletion($match);
            $this->command->info('Royal Flush badges evaluated successfully.');
        } catch (\Throwable $e) {
            $this->command->warn('Badge evaluation failed: ' . $e->getMessage());
        }

        $this->command->info("Completed Royal Flush match seeded: \"{$match->name}\" — 10 shooters, 4 distances, scores + badges.");
    }
}
