<?php

namespace Database\Seeders;

use App\Enums\MatchStatus;
use App\Models\ElrScoringProfile;
use App\Models\ElrStage;
use App\Models\ElrTarget;
use App\Models\ShootingMatch;
use App\Models\Shooter;
use App\Models\Squad;
use App\Models\User;
use Illuminate\Database\Seeder;

class ElrDemoMatchSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'paul@charsley.co.za')->first()
            ?? User::where('role', 'owner')->first();

        if (! $owner) {
            $this->command->warn('No owner user found – skipping ELR seeder.');
            return;
        }

        $match = ShootingMatch::updateOrCreate(
            ['name' => 'ELR Invitational — Ladder Demo'],
            [
                'date' => now()->toDateString(),
                'location' => 'Dullstroom ELR Range',
                'status' => MatchStatus::Active,
                'scoring_type' => 'elr',
                'notes' => 'Seeded ELR demo — 2 stages, 4 targets each, 2 squads × 5 shooters.',
                'created_by' => $owner->id,
            ]
        );

        $profile = ElrScoringProfile::firstOrCreate(
            ['match_id' => $match->id, 'name' => 'Default 3-shot'],
            ['multipliers' => [1.00, 0.70, 0.50]]
        );

        $match->update(['elr_scoring_profile_id' => $profile->id]);

        // Stage 1 — Ladder
        $stage1 = ElrStage::firstOrCreate(
            ['match_id' => $match->id, 'label' => 'Stage 1 — Ladder'],
            ['stage_type' => 'ladder', 'elr_scoring_profile_id' => $profile->id, 'sort_order' => 1]
        );

        $ladderTargets = [
            ['name' => 'T1', 'distance_m' => 1000, 'base_points' => 10, 'must_hit_to_advance' => true],
            ['name' => 'T2', 'distance_m' => 1200, 'base_points' => 15, 'must_hit_to_advance' => true],
            ['name' => 'T3', 'distance_m' => 1500, 'base_points' => 20, 'must_hit_to_advance' => true],
            ['name' => 'T4', 'distance_m' => 1800, 'base_points' => 25, 'must_hit_to_advance' => true],
        ];

        foreach ($ladderTargets as $i => $t) {
            ElrTarget::firstOrCreate(
                ['elr_stage_id' => $stage1->id, 'name' => $t['name']],
                [...$t, 'max_shots' => 3, 'sort_order' => $i + 1]
            );
        }

        // Stage 2 — Static
        $stage2 = ElrStage::firstOrCreate(
            ['match_id' => $match->id, 'label' => 'Stage 2 — Static'],
            ['stage_type' => 'static', 'elr_scoring_profile_id' => $profile->id, 'sort_order' => 2]
        );

        $staticTargets = [
            ['name' => 'S1', 'distance_m' => 800, 'base_points' => 8, 'must_hit_to_advance' => false],
            ['name' => 'S2', 'distance_m' => 1100, 'base_points' => 12, 'must_hit_to_advance' => false],
            ['name' => 'S3', 'distance_m' => 1400, 'base_points' => 18, 'must_hit_to_advance' => false],
            ['name' => 'S4', 'distance_m' => 2000, 'base_points' => 30, 'must_hit_to_advance' => false],
        ];

        foreach ($staticTargets as $i => $t) {
            ElrTarget::firstOrCreate(
                ['elr_stage_id' => $stage2->id, 'name' => $t['name']],
                [...$t, 'max_shots' => 3, 'sort_order' => $i + 1]
            );
        }

        // Squads + shooters
        $names = [
            'Johan van der Berg', 'Pieter Erasmus', 'Danie Steyn', 'Hendrik Venter', 'Willem Botha',
            'Francois Joubert', 'Andre Pretorius', 'Charl Swanepoel', 'Riaan Coetzee', 'Stefan du Plessis',
        ];

        $squadA = Squad::firstOrCreate(
            ['match_id' => $match->id, 'name' => 'Squad A'],
            ['sort_order' => 1]
        );
        $squadB = Squad::firstOrCreate(
            ['match_id' => $match->id, 'name' => 'Squad B'],
            ['sort_order' => 2]
        );

        foreach ($names as $i => $name) {
            $squad = $i < 5 ? $squadA : $squadB;
            Shooter::firstOrCreate(
                ['squad_id' => $squad->id, 'name' => $name],
                [
                    'bib_number' => 'ELR-' . str_pad($i + 1, 2, '0', STR_PAD_LEFT),
                    'sort_order' => ($i % 5) + 1,
                    'status' => 'active',
                ]
            );
        }
    }
}
