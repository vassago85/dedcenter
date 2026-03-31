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

class PrsDemoMatchSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'paul@charsley.co.za')->first()
            ?? User::where('role', 'owner')->first();

        if (! $owner) {
            $this->command->warn('No owner user found – skipping PRS seeder.');
            return;
        }

        $org = Organization::where('slug', 'royal-flush')->first();

        $match = ShootingMatch::updateOrCreate(
            ['name' => 'Royal Flush — PRS Match', 'organization_id' => $org?->id],
            [
                'date' => now()->addDays(7)->toDateString(),
                'location' => 'Dullstroom Range',
                'status' => MatchStatus::Active,
                'scoring_type' => 'prs',
                'side_bet_enabled' => false,
                'concurrent_relays' => 2,
                'notes' => 'Seeded PRS match — 6 stages with par times, 2 relays × 8 shooters.',
                'created_by' => $owner->id,
                'entry_fee' => 200.00,
            ]
        );

        // ── Divisions ──
        $divOpen = MatchDivision::firstOrCreate(['match_id' => $match->id, 'name' => 'Open'], ['sort_order' => 1]);
        $divProduction = MatchDivision::firstOrCreate(['match_id' => $match->id, 'name' => 'Production'], ['sort_order' => 2]);

        // ── Categories ──
        $catOverall = MatchCategory::firstOrCreate(['match_id' => $match->id, 'name' => 'Overall', 'slug' => 'overall'], ['sort_order' => 1]);
        $catSenior = MatchCategory::firstOrCreate(['match_id' => $match->id, 'name' => 'Senior', 'slug' => 'senior'], ['sort_order' => 2]);

        // ── 6 PRS Stages — each target has its own distance, 1 hit = 1 pt ──
        $stages = [
            ['label' => 'Stage 1 — Prone', 'par' => 90.0, 'timed' => true, 'targets' => [
                ['label' => 'T1', 'distance' => 300, 'size' => '2 MOA'],
                ['label' => 'T2', 'distance' => 350, 'size' => '2 MOA'],
                ['label' => 'T3', 'distance' => 300, 'size' => '1.5 MOA'],
                ['label' => 'T4', 'distance' => 400, 'size' => '1 MOA'],
            ]],
            ['label' => 'Stage 2 — Barricade', 'par' => 120.0, 'timed' => true, 'targets' => [
                ['label' => 'T1', 'distance' => 350, 'size' => '2 MOA'],
                ['label' => 'T2', 'distance' => 400, 'size' => '2 MOA'],
                ['label' => 'T3', 'distance' => 450, 'size' => '1.5 MOA'],
                ['label' => 'T4', 'distance' => 350, 'size' => '1.5 MOA'],
                ['label' => 'T5', 'distance' => 500, 'size' => '1 MOA'],
            ]],
            ['label' => 'Stage 3 — Rooftop', 'par' => 120.0, 'timed' => true, 'targets' => [
                ['label' => 'T1', 'distance' => 400, 'size' => '2.5 MOA'],
                ['label' => 'T2', 'distance' => 500, 'size' => '2 MOA'],
                ['label' => 'T3', 'distance' => 550, 'size' => '1.5 MOA'],
                ['label' => 'T4', 'distance' => 600, 'size' => '1 MOA'],
            ]],
            ['label' => 'Stage 4 — Tripod', 'par' => 150.0, 'timed' => true, 'targets' => [
                ['label' => 'T1', 'distance' => 500, 'size' => '2 MOA'],
                ['label' => 'T2', 'distance' => 600, 'size' => '2 MOA'],
                ['label' => 'T3', 'distance' => 550, 'size' => '1.5 MOA'],
                ['label' => 'T4', 'distance' => 700, 'size' => '1 MOA'],
            ]],
            ['label' => 'Stage 5 — Positional', 'par' => 150.0, 'timed' => true, 'targets' => [
                ['label' => 'T1', 'distance' => 500, 'size' => '3 MOA'],
                ['label' => 'T2', 'distance' => 600, 'size' => '2 MOA'],
                ['label' => 'T3', 'distance' => 700, 'size' => '2 MOA'],
                ['label' => 'T4', 'distance' => 650, 'size' => '1.5 MOA'],
                ['label' => 'T5', 'distance' => 750, 'size' => '1 MOA'],
            ]],
            ['label' => 'Stage 6 — Tiebreaker', 'par' => 180.0, 'timed' => true, 'tiebreaker' => true, 'targets' => [
                ['label' => 'T1', 'distance' => 600, 'size' => '2 MOA'],
                ['label' => 'T2', 'distance' => 700, 'size' => '1.5 MOA'],
                ['label' => 'T3', 'distance' => 800, 'size' => '1 MOA'],
                ['label' => 'T4', 'distance' => 750, 'size' => '1 MOA'],
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

        // ── 2 Relays × 8 Shooters ──
        $shooterNames = [
            'Gerhard van Niekerk', 'Pieter Jordaan', 'Johan Roux', 'Fanie Grobler',
            'Danie du Toit', 'Kobus Viljoen', 'Riaan Fouché', 'Andre van Heerden',
            'Willem Stander', 'Charl Engelbrecht', 'Stefan Vermaak', 'Hugo de Beer',
            'Francois Myburgh', 'Marius Kotze', 'Paul Charsley', 'Tinus van Schalkwyk',
        ];

        $relayNames = ['Relay 1', 'Relay 2'];
        $divisions = [$divOpen, $divProduction];

        $bibNumber = 1;

        foreach ($relayNames as $si => $relayName) {
            $squad = Squad::firstOrCreate(
                ['match_id' => $match->id, 'name' => $relayName],
                ['sort_order' => $si + 1]
            );

            $members = array_slice($shooterNames, $si * 8, 8);

            foreach ($members as $mi => $name) {
                $userId = null;
                if ($name === 'Paul Charsley') {
                    $paul = User::where('email', 'paul@charsley.co.za')->first();
                    $userId = $paul?->id;
                }

                $shooter = Shooter::firstOrCreate(
                    ['squad_id' => $squad->id, 'name' => $name],
                    [
                        'bib_number' => 'PRS-' . str_pad($bibNumber, 2, '0', STR_PAD_LEFT),
                        'user_id' => $userId,
                        'match_division_id' => $divisions[$mi % 2]->id,
                        'sort_order' => $mi + 1,
                    ]
                );

                $shooter->categories()->syncWithoutDetaching([$catOverall->id]);
                if ($mi >= 6) {
                    $shooter->categories()->syncWithoutDetaching([$catSenior->id]);
                }

                $bibNumber++;
            }
        }

        $this->command->info("PRS demo match seeded: \"{$match->name}\" — 6 stages, 16 shooters.");
    }
}
