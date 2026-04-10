<?php

namespace Database\Seeders;

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\MatchCategory;
use App\Models\MatchDivision;
use App\Models\Organization;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use Illuminate\Database\Seeder;

class LiveDemoMatchSeeder extends Seeder
{
    public function run(): void
    {
        $paul = User::where('email', 'paul@charsley.co.za')->first()
            ?? User::where('role', 'owner')->firstOrFail();

        $org = Organization::where('slug', 'royal-flush')->first();

        $match = ShootingMatch::updateOrCreate(
            ['name' => 'Royal Flush — Live Demo (10 Shooters)', 'organization_id' => $org?->id],
            [
                'date' => now()->toDateString(),
                'location' => 'Dullstroom Range',
                'status' => MatchStatus::Active,
                'scoring_type' => 'standard',
                'scores_published' => true,
                'concurrent_relays' => 2,
                'max_squad_size' => 5,
                'notes' => 'Live demo seed: 10 shooters, 2 relays, 3 distances.',
                'created_by' => $paul->id,
                'entry_fee' => 0,
            ]
        );

        $divOpen = MatchDivision::firstOrCreate(
            ['match_id' => $match->id, 'name' => 'Open'],
            ['sort_order' => 1]
        );
        $divFactory = MatchDivision::firstOrCreate(
            ['match_id' => $match->id, 'name' => 'Factory'],
            ['sort_order' => 2]
        );
        $catOverall = MatchCategory::firstOrCreate(
            ['match_id' => $match->id, 'slug' => 'overall'],
            ['name' => 'Overall', 'sort_order' => 1]
        );

        $distanceBanks = [
            ['label' => '400m Bank', 'distance' => 400],
            ['label' => '500m Bank', 'distance' => 500],
            ['label' => '600m Bank', 'distance' => 600],
        ];

        $gongsByTargetSet = [];
        foreach ($distanceBanks as $i => $bank) {
            $targetSet = TargetSet::updateOrCreate(
                ['match_id' => $match->id, 'label' => $bank['label']],
                [
                    'distance_meters' => $bank['distance'],
                    'distance_multiplier' => $bank['distance'] / 100,
                    'sort_order' => $i + 1,
                    'is_tiebreaker' => false,
                ]
            );

            $gongConfig = [
                ['number' => 1, 'label' => '2.5 MOA', 'multiplier' => 1.00],
                ['number' => 2, 'label' => '2.0 MOA', 'multiplier' => 1.25],
                ['number' => 3, 'label' => '1.5 MOA', 'multiplier' => 1.50],
                ['number' => 4, 'label' => '1.0 MOA', 'multiplier' => 2.00],
                ['number' => 5, 'label' => '0.5 MOA', 'multiplier' => 3.00],
            ];

            foreach ($gongConfig as $gongData) {
                Gong::updateOrCreate(
                    ['target_set_id' => $targetSet->id, 'number' => $gongData['number']],
                    ['label' => $gongData['label'], 'multiplier' => $gongData['multiplier']]
                );
            }

            $gongsByTargetSet[$targetSet->id] = Gong::where('target_set_id', $targetSet->id)
                ->orderBy('number')
                ->get()
                ->values();
        }

        $shooters = [
            ['name' => 'Paul Charsley', 'relay' => 1, 'hits' => [[1,1,1,1,1], [1,1,1,1,0], [1,1,1,0,0]]],
            ['name' => 'Johan Botha', 'relay' => 1, 'hits' => [[1,1,1,1,0], [1,1,1,0,0], [1,1,0,0,0]]],
            ['name' => 'Pieter van Zyl', 'relay' => 1, 'hits' => [[1,1,1,0,0], [1,1,1,1,0], [1,1,1,0,0]]],
            ['name' => 'Riaan de Villiers', 'relay' => 1, 'hits' => [[1,1,1,0,0], [1,1,0,0,0], [1,0,0,0,0]]],
            ['name' => 'Stephan Louw', 'relay' => 1, 'hits' => [[1,1,0,0,0], [1,1,1,0,0], [1,1,0,0,0]]],
            ['name' => 'Andre Joubert', 'relay' => 2, 'hits' => [[1,1,1,1,0], [1,1,1,0,0], [1,1,1,0,0]]],
            ['name' => 'Francois Nel', 'relay' => 2, 'hits' => [[1,1,1,0,0], [1,1,1,0,0], [1,1,0,0,0]]],
            ['name' => 'Danie Erasmus', 'relay' => 2, 'hits' => [[1,1,0,0,0], [1,1,0,0,0], [1,0,0,0,0]]],
            ['name' => 'Marius Venter', 'relay' => 2, 'hits' => [[1,1,1,0,0], [1,1,0,0,0], [1,1,0,0,0]]],
            ['name' => 'Charl du Plessis', 'relay' => 2, 'hits' => [[1,0,0,0,0], [1,1,0,0,0], [1,0,0,0,0]]],
        ];

        $relayOne = Squad::updateOrCreate(
            ['match_id' => $match->id, 'name' => 'Relay 1'],
            ['sort_order' => 1, 'max_capacity' => 5]
        );
        $relayTwo = Squad::updateOrCreate(
            ['match_id' => $match->id, 'name' => 'Relay 2'],
            ['sort_order' => 2, 'max_capacity' => 5]
        );

        $allGongIds = collect($gongsByTargetSet)->flatten()->pluck('id')->all();
        $now = now()->toDateTimeString();

        foreach ($shooters as $i => $row) {
            $squad = $row['relay'] === 1 ? $relayOne : $relayTwo;
            $userId = $row['name'] === 'Paul Charsley' ? $paul->id : null;

            $shooter = Shooter::updateOrCreate(
                ['squad_id' => $squad->id, 'name' => $row['name']],
                [
                    'bib_number' => 'DEMO-' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                    'user_id' => $userId,
                    'match_division_id' => $i % 2 === 0 ? $divOpen->id : $divFactory->id,
                    'sort_order' => ($i % 5) + 1,
                    'status' => 'active',
                ]
            );

            $shooter->categories()->syncWithoutDetaching([$catOverall->id]);

            Score::where('shooter_id', $shooter->id)
                ->whereIn('gong_id', $allGongIds)
                ->delete();

            $targetSetIds = array_keys($gongsByTargetSet);
            foreach ($targetSetIds as $setIndex => $targetSetId) {
                $hitPattern = $row['hits'][$setIndex] ?? [0, 0, 0, 0, 0];
                foreach ($gongsByTargetSet[$targetSetId] as $gongIndex => $gong) {
                    Score::create([
                        'shooter_id' => $shooter->id,
                        'gong_id' => $gong->id,
                        'is_hit' => (bool) ($hitPattern[$gongIndex] ?? 0),
                        'device_id' => 'live-demo-seeder',
                        'recorded_at' => $now,
                        'synced_at' => $now,
                    ]);
                }
            }
        }

        $this->command->info("Live demo match seeded: {$match->name} (ID: {$match->id})");
        $this->command->info('Config: 10 shooters, 2 relays, 3 distances, active + scoreboard-ready.');
    }
}
