<?php

namespace Database\Seeders;

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\MatchCategory;
use App\Models\MatchDivision;
use App\Models\Organization;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use Illuminate\Database\Seeder;

class LiveDemoBlankMatchSeeder extends Seeder
{
    public function run(): void
    {
        $paul = User::where('email', 'paul@charsley.co.za')->first()
            ?? User::where('role', 'owner')->firstOrFail();

        $org = Organization::where('slug', 'royal-flush')->first();

        $match = ShootingMatch::updateOrCreate(
            ['name' => 'Royal Flush — Live Demo (No Scores Yet)', 'organization_id' => $org?->id],
            [
                'date' => now()->toDateString(),
                'location' => 'Dullstroom Range',
                'status' => MatchStatus::Active,
                'scoring_type' => 'standard',
                'scores_published' => true,
                'concurrent_relays' => 2,
                'max_squad_size' => 5,
                'notes' => 'Live demo seed: 10 shooters, 2 relays, 3 distances, no scores yet.',
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
        }

        $relayOne = Squad::updateOrCreate(
            ['match_id' => $match->id, 'name' => 'Relay 1'],
            ['sort_order' => 1, 'max_capacity' => 5]
        );
        $relayTwo = Squad::updateOrCreate(
            ['match_id' => $match->id, 'name' => 'Relay 2'],
            ['sort_order' => 2, 'max_capacity' => 5]
        );

        $shooters = [
            'Paul Charsley',
            'Johan Botha',
            'Pieter van Zyl',
            'Riaan de Villiers',
            'Stephan Louw',
            'Andre Joubert',
            'Francois Nel',
            'Danie Erasmus',
            'Marius Venter',
            'Charl du Plessis',
        ];

        foreach ($shooters as $i => $name) {
            $relay = $i < 5 ? $relayOne : $relayTwo;
            $userId = $name === 'Paul Charsley' ? $paul->id : null;

            $shooter = Shooter::updateOrCreate(
                ['squad_id' => $relay->id, 'name' => $name],
                [
                    'bib_number' => 'BLANK-' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                    'user_id' => $userId,
                    'match_division_id' => $i % 2 === 0 ? $divOpen->id : $divFactory->id,
                    'sort_order' => ($i % 5) + 1,
                    'status' => 'active',
                ]
            );

            $shooter->categories()->syncWithoutDetaching([$catOverall->id]);
        }

        $this->command->info("Live blank demo match seeded: {$match->name} (ID: {$match->id})");
        $this->command->info('Config: 10 shooters, 2 relays, 3 distances, no scores recorded.');
    }
}
