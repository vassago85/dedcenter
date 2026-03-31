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

class ClbPretoriaMatchSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'paul@charsley.co.za')->first()
            ?? User::where('role', 'owner')->first();

        if (! $owner) {
            $this->command->warn('No owner user found – skipping CLB Pretoria seeder.');
            return;
        }

        $org = Organization::firstOrCreate(
            ['slug' => 'clb-pretoria-prc'],
            [
                'name' => 'CLB Pretoria Precision Rifle Club',
                'owner_id' => $owner->id,
            ]
        );

        $match = ShootingMatch::updateOrCreate(
            ['name' => 'CLB Pretoria — Club Match', 'organization_id' => $org->id],
            [
                'date' => now()->addDays(14)->toDateString(),
                'location' => 'Pretoria Rifle Range',
                'status' => MatchStatus::Active,
                'scoring_type' => 'prs',
                'side_bet_enabled' => false,
                'concurrent_relays' => 2,
                'notes' => 'CLB Pretoria PRS club match — 6 stages × 8 shots, 45 shooters across 6 squads.',
                'created_by' => $owner->id,
                'entry_fee' => 350.00,
            ]
        );

        // ── Divisions ──
        $divOpen = MatchDivision::firstOrCreate(['match_id' => $match->id, 'name' => 'Open'], ['sort_order' => 1]);
        $divFactory = MatchDivision::firstOrCreate(['match_id' => $match->id, 'name' => 'Factory'], ['sort_order' => 2]);
        $divLimited = MatchDivision::firstOrCreate(['match_id' => $match->id, 'name' => 'Limited'], ['sort_order' => 3]);

        // ── Categories ──
        $catOverall = MatchCategory::firstOrCreate(['match_id' => $match->id, 'slug' => 'overall'], ['name' => 'Overall', 'sort_order' => 1]);
        $catSenior = MatchCategory::firstOrCreate(['match_id' => $match->id, 'slug' => 'seniors-open'], ['name' => 'Seniors Open', 'sort_order' => 2]);
        $catLadies = MatchCategory::firstOrCreate(['match_id' => $match->id, 'slug' => 'ladies-open'], ['name' => 'Ladies Open', 'sort_order' => 3]);
        $catJunior = MatchCategory::firstOrCreate(['match_id' => $match->id, 'slug' => 'juniors-open'], ['name' => 'Juniors Open', 'sort_order' => 4]);

        // ── 6 Stages × 8 shots each ──
        $stages = [
            ['label' => 'Stage 1 — Prone', 'par' => 90.0, 'timed' => true, 'targets' => [
                ['label' => 'T1', 'distance' => 200, 'size' => '3 MOA'],
                ['label' => 'T2', 'distance' => 250, 'size' => '2.5 MOA'],
                ['label' => 'T3', 'distance' => 300, 'size' => '2 MOA'],
                ['label' => 'T4', 'distance' => 350, 'size' => '2 MOA'],
                ['label' => 'T5', 'distance' => 300, 'size' => '1.5 MOA'],
                ['label' => 'T6', 'distance' => 400, 'size' => '1.5 MOA'],
                ['label' => 'T7', 'distance' => 350, 'size' => '1 MOA'],
                ['label' => 'T8', 'distance' => 450, 'size' => '1 MOA'],
            ]],
            ['label' => 'Stage 2 — Barricade', 'par' => 120.0, 'timed' => true, 'targets' => [
                ['label' => 'T1', 'distance' => 250, 'size' => '3 MOA'],
                ['label' => 'T2', 'distance' => 300, 'size' => '2.5 MOA'],
                ['label' => 'T3', 'distance' => 350, 'size' => '2 MOA'],
                ['label' => 'T4', 'distance' => 400, 'size' => '2 MOA'],
                ['label' => 'T5', 'distance' => 450, 'size' => '1.5 MOA'],
                ['label' => 'T6', 'distance' => 350, 'size' => '1.5 MOA'],
                ['label' => 'T7', 'distance' => 500, 'size' => '1 MOA'],
                ['label' => 'T8', 'distance' => 400, 'size' => '1 MOA'],
            ]],
            ['label' => 'Stage 3 — Rooftop', 'par' => 120.0, 'timed' => true, 'targets' => [
                ['label' => 'T1', 'distance' => 300, 'size' => '2.5 MOA'],
                ['label' => 'T2', 'distance' => 400, 'size' => '2.5 MOA'],
                ['label' => 'T3', 'distance' => 500, 'size' => '2 MOA'],
                ['label' => 'T4', 'distance' => 450, 'size' => '2 MOA'],
                ['label' => 'T5', 'distance' => 550, 'size' => '1.5 MOA'],
                ['label' => 'T6', 'distance' => 600, 'size' => '1.5 MOA'],
                ['label' => 'T7', 'distance' => 500, 'size' => '1 MOA'],
                ['label' => 'T8', 'distance' => 650, 'size' => '1 MOA'],
            ]],
            ['label' => 'Stage 4 — Tripod', 'par' => 150.0, 'timed' => true, 'targets' => [
                ['label' => 'T1', 'distance' => 350, 'size' => '2.5 MOA'],
                ['label' => 'T2', 'distance' => 450, 'size' => '2 MOA'],
                ['label' => 'T3', 'distance' => 500, 'size' => '2 MOA'],
                ['label' => 'T4', 'distance' => 550, 'size' => '1.5 MOA'],
                ['label' => 'T5', 'distance' => 600, 'size' => '1.5 MOA'],
                ['label' => 'T6', 'distance' => 650, 'size' => '1 MOA'],
                ['label' => 'T7', 'distance' => 700, 'size' => '1 MOA'],
                ['label' => 'T8', 'distance' => 550, 'size' => '1 MOA'],
            ]],
            ['label' => 'Stage 5 — Positional', 'par' => 150.0, 'timed' => true, 'targets' => [
                ['label' => 'T1', 'distance' => 400, 'size' => '3 MOA'],
                ['label' => 'T2', 'distance' => 500, 'size' => '2 MOA'],
                ['label' => 'T3', 'distance' => 550, 'size' => '2 MOA'],
                ['label' => 'T4', 'distance' => 600, 'size' => '1.5 MOA'],
                ['label' => 'T5', 'distance' => 650, 'size' => '1.5 MOA'],
                ['label' => 'T6', 'distance' => 700, 'size' => '1 MOA'],
                ['label' => 'T7', 'distance' => 750, 'size' => '1 MOA'],
                ['label' => 'T8', 'distance' => 600, 'size' => '1 MOA'],
            ]],
            ['label' => 'Stage 6 — Tiebreaker', 'par' => 180.0, 'timed' => true, 'tiebreaker' => true, 'targets' => [
                ['label' => 'T1', 'distance' => 500, 'size' => '2 MOA'],
                ['label' => 'T2', 'distance' => 600, 'size' => '2 MOA'],
                ['label' => 'T3', 'distance' => 650, 'size' => '1.5 MOA'],
                ['label' => 'T4', 'distance' => 700, 'size' => '1.5 MOA'],
                ['label' => 'T5', 'distance' => 750, 'size' => '1 MOA'],
                ['label' => 'T6', 'distance' => 800, 'size' => '1 MOA'],
                ['label' => 'T7', 'distance' => 700, 'size' => '1 MOA'],
                ['label' => 'T8', 'distance' => 850, 'size' => '1 MOA'],
            ]],
        ];

        foreach ($stages as $i => $stage) {
            $ts = TargetSet::firstOrCreate(
                ['match_id' => $match->id, 'label' => $stage['label']],
                [
                    'distance_meters' => 0,
                    'distance_multiplier' => 1,
                    'sort_order' => $i + 1,
                    'is_tiebreaker' => $stage['tiebreaker'] ?? false,
                    'par_time_seconds' => $stage['par'],
                    'total_shots' => count($stage['targets']),
                    'stage_number' => $i + 1,
                    'is_timed_stage' => $stage['timed'],
                ]
            );

            $ts->update([
                'total_shots' => count($stage['targets']),
                'stage_number' => $i + 1,
                'is_timed_stage' => $stage['timed'],
            ]);

            $ts->gongs()->delete();

            foreach ($stage['targets'] as $j => $t) {
                Gong::create([
                    'target_set_id' => $ts->id,
                    'number' => $j + 1,
                    'label' => $t['label'],
                    'multiplier' => 1.00,
                    'distance_meters' => $t['distance'],
                    'target_size' => $t['size'],
                ]);
            }
        }

        // ── 6 Squads × 7-8 shooters = 45 total ──
        $shooterNames = [
            // Squad 1 (8)
            'Hennie Potgieter', 'Jaco Pretorius', 'Piet van der Merwe', 'Schalk Burger',
            'Thabo Mokoena', 'Johan Botha', 'Werner Kruger', 'Dirk Steenkamp',
            // Squad 2 (8)
            'Nico Swanepoel', 'Frikkie Marais', 'Leon Venter', 'Gert Visagie',
            'Chris Barnard', 'André Coetzee', 'Jan Willemse', 'Kallie Erasmus',
            // Squad 3 (8)
            'Wikus Jansen', 'Dawie Lombard', 'Bennie Klopper', 'Stefan Brits',
            'Marthinus Louw', 'Christo Nel', 'Hannes du Plessis', 'Jannie Prinsloo',
            // Squad 4 (7)
            'Pieter Rossouw', 'Francois Ferreira', 'Attie van Zyl', 'Kobus Bester',
            'Louis Jacobs', 'Riaan Cilliers', 'Paul Charsley',
            // Squad 5 (7)
            'Deon van Rensburg', 'Izak Delport', 'Bertus Naude', 'Etienne Gouws',
            'Gerrit Smit', 'Johan Havenga', 'Willie Pieterse',
            // Squad 6 (7)
            'Sarel Maritz', 'Pieter Liebenberg', 'Heinrich Mostert', 'Francois Scheepers',
            'Tinus Mouton', 'Adriaan Booysen', 'Corné du Preez',
        ];

        $squadConfig = [
            ['name' => 'Squad 1', 'count' => 8],
            ['name' => 'Squad 2', 'count' => 8],
            ['name' => 'Squad 3', 'count' => 8],
            ['name' => 'Squad 4', 'count' => 7],
            ['name' => 'Squad 5', 'count' => 7],
            ['name' => 'Squad 6', 'count' => 7],
        ];

        $divisions = [$divOpen, $divFactory, $divLimited];
        $bibNumber = 1;
        $nameOffset = 0;

        foreach ($squadConfig as $si => $cfg) {
            $squad = Squad::firstOrCreate(
                ['match_id' => $match->id, 'name' => $cfg['name']],
                ['sort_order' => $si + 1]
            );

            $members = array_slice($shooterNames, $nameOffset, $cfg['count']);
            $nameOffset += $cfg['count'];

            foreach ($members as $mi => $name) {
                $userId = null;
                if ($name === 'Paul Charsley') {
                    $paul = User::where('email', 'paul@charsley.co.za')->first();
                    $userId = $paul?->id;
                }

                $shooter = Shooter::firstOrCreate(
                    ['squad_id' => $squad->id, 'name' => $name],
                    [
                        'bib_number' => 'CLB-' . str_pad($bibNumber, 2, '0', STR_PAD_LEFT),
                        'user_id' => $userId,
                        'match_division_id' => $divisions[$mi % count($divisions)]->id,
                        'sort_order' => $mi + 1,
                    ]
                );

                $shooter->categories()->syncWithoutDetaching([$catOverall->id]);

                if ($mi >= 5 && $si < 3) {
                    $shooter->categories()->syncWithoutDetaching([$catSenior->id]);
                }

                $bibNumber++;
            }
        }

        $this->command->info("CLB Pretoria match seeded: \"{$match->name}\" — 6 stages × 8 shots, 45 shooters.");
    }
}
