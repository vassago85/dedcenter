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

class DemoMatchSeeder extends Seeder
{
    public function run(): void
    {
        $paul = User::where('email', 'paul@charsley.co.za')->firstOrFail();
        $org = Organization::where('slug', 'royal-flush')->firstOrFail();

        $match = ShootingMatch::updateOrCreate(
            ['name' => 'Royal Flush — Relay Match', 'organization_id' => $org->id],
            [
                'date' => now()->toDateString(),
                'location' => 'Dullstroom Range',
                'status' => MatchStatus::Active,
                'scoring_type' => 'standard',
                'side_bet_enabled' => true,
                'notes' => 'Seeded relay-based match — 4 relays × 10 shooters, gong scoring at 400–700 m.',
                'created_by' => $paul->id,
                'entry_fee' => 150.00,
            ]
        );

        // ── Divisions ──
        $divOpen    = MatchDivision::firstOrCreate(['match_id' => $match->id, 'name' => 'Open'],    ['sort_order' => 1]);
        $divFactory = MatchDivision::firstOrCreate(['match_id' => $match->id, 'name' => 'Factory'], ['sort_order' => 2]);

        // ── Categories ──
        $catOverall = MatchCategory::firstOrCreate(['match_id' => $match->id, 'name' => 'Overall', 'slug' => 'overall'], ['sort_order' => 1]);
        $catSenior  = MatchCategory::firstOrCreate(['match_id' => $match->id, 'name' => 'Senior',  'slug' => 'senior'],  ['sort_order' => 2]);
        $catJunior  = MatchCategory::firstOrCreate(['match_id' => $match->id, 'name' => 'Junior',  'slug' => 'junior'],  ['sort_order' => 3]);
        $catLadies  = MatchCategory::firstOrCreate(['match_id' => $match->id, 'name' => 'Ladies',  'slug' => 'ladies'],  ['sort_order' => 4]);

        // ── Target sets: 400 m, 500 m, 600 m, 700 m ──
        // Each bank has 5 gongs sized by MOA with increasing multipliers.
        // Distance multiplier = distance / 100 (e.g. 400 m → 4×).
        $distances = [
            ['label' => '400m Bank',  'distance' => 400, 'gongs' => [
                ['label' => '2.5 MOA', 'multiplier' => 1.00],
                ['label' => '2.0 MOA', 'multiplier' => 1.25],
                ['label' => '1.5 MOA', 'multiplier' => 1.50],
                ['label' => '1.0 MOA', 'multiplier' => 2.00],
                ['label' => '0.5 MOA', 'multiplier' => 3.00],
            ]],
            ['label' => '500m Bank',  'distance' => 500, 'gongs' => [
                ['label' => '2.5 MOA', 'multiplier' => 1.00],
                ['label' => '2.0 MOA', 'multiplier' => 1.25],
                ['label' => '1.5 MOA', 'multiplier' => 1.50],
                ['label' => '1.0 MOA', 'multiplier' => 2.00],
                ['label' => '0.5 MOA', 'multiplier' => 3.00],
            ]],
            ['label' => '600m Bank',  'distance' => 600, 'gongs' => [
                ['label' => '2.5 MOA', 'multiplier' => 1.00],
                ['label' => '2.0 MOA', 'multiplier' => 1.50],
                ['label' => '1.5 MOA', 'multiplier' => 2.00],
                ['label' => '1.0 MOA', 'multiplier' => 2.50],
                ['label' => '0.5 MOA', 'multiplier' => 4.00],
            ]],
            ['label' => '700m Bank',  'distance' => 700, 'gongs' => [
                ['label' => '2.5 MOA', 'multiplier' => 1.25],
                ['label' => '2.0 MOA', 'multiplier' => 1.75],
                ['label' => '1.5 MOA', 'multiplier' => 2.50],
                ['label' => '1.0 MOA', 'multiplier' => 3.50],
                ['label' => '0.5 MOA', 'multiplier' => 5.00],
            ]],
        ];

        foreach ($distances as $i => $bank) {
            $ts = TargetSet::firstOrCreate(
                ['match_id' => $match->id, 'label' => $bank['label']],
                ['distance_meters' => $bank['distance'], 'distance_multiplier' => $bank['distance'] / 100, 'sort_order' => $i + 1, 'is_tiebreaker' => false]
            );

            foreach ($bank['gongs'] as $j => $g) {
                Gong::firstOrCreate(
                    ['target_set_id' => $ts->id, 'number' => $j + 1],
                    ['label' => $g['label'], 'multiplier' => $g['multiplier']]
                );
            }
        }

        // ── 4 Relays × 10 Shooters ──
        $shooterNames = [
            // Relay Alpha
            'Pieter van Zyl', 'Johan Botha', 'Riaan de Villiers', 'Henk Swart', 'Willem Pretorius',
            'Kobus Prinsloo', 'Stephan Louw', 'Fanie Naude', 'Corné van der Merwe', 'Jannie Potgieter',
            // Relay Bravo
            'André Joubert', 'Francois Nel', 'Danie Erasmus', 'Marius Venter', 'Charl du Plessis',
            'Wynand Bosman', 'Gerhard Smit', 'Attie Greyling', 'Hugo Visagie', 'Frikkie Bester',
            // Relay Charlie
            'Thabo Molefe', 'Jacques Kruger', 'Stefan le Roux', 'Gert Coetzee', 'Nico Marais',
            'Christo Lombard', 'Jaco Rossouw', 'Petrus Snyman', 'Tienie Ferreira', 'Wessel van Wyk',
            // Relay Delta
            'Paul Charsley', 'Ben Fourie', 'Jan Harmse', 'Louis Steyn', 'Werner Britz',
            'Deon Cilliers', 'Rikus Janse van Rensburg', 'Anton Scheepers', 'Pieter-Steph Malan', 'Tjaart Lubbe',
        ];

        $relayNames = ['Alpha', 'Bravo', 'Charlie', 'Delta'];
        $divisions  = [$divOpen, $divFactory];
        $categories = [$catOverall, $catSenior, $catJunior, $catLadies];

        $bibNumber = 1;

        foreach ($relayNames as $si => $relayName) {
            $squad = Squad::firstOrCreate(
                ['match_id' => $match->id, 'name' => $relayName],
                ['sort_order' => $si + 1]
            );

            $sliceStart = $si * 10;
            $members    = array_slice($shooterNames, $sliceStart, 10);

            foreach ($members as $mi => $name) {
                $userId = null;
                if ($name === 'Paul Charsley') {
                    $userId = $paul->id;
                }

                $shooter = Shooter::firstOrCreate(
                    ['squad_id' => $squad->id, 'name' => $name],
                    [
                        'bib_number' => str_pad($bibNumber, 3, '0', STR_PAD_LEFT),
                        'user_id' => $userId,
                        'match_division_id' => $divisions[$mi % 2]->id,
                        'sort_order' => $mi + 1,
                    ]
                );

                $cat = $categories[array_rand($categories)];
                $shooter->categories()->syncWithoutDetaching([$cat->id]);

                $bibNumber++;
            }
        }

        $this->command->info("Demo match seeded: \"{$match->name}\" — {$match->date->format('d M Y')} — 4 relays, 40 shooters, 4 distances.");
    }
}
