<?php

namespace Database\Seeders;

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\MatchCategory;
use App\Models\MatchDivision;
use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\TargetSet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * PPRC Centerfire Club Match — Saturday, 1 August 2026 — Legends Adventure
 * Farm, Rayton.
 *
 * PRS scoring: 1 point per impact, no partial points, match total 42.
 * Par time 105 s on every stage. Stage 4 ("The Piano") is the timed
 * tie-breaker — equal impact totals are ranked by lower Stage 4 time, so it
 * is the only stage flagged is_timed_stage / is_tiebreaker.
 *
 * The DeadCenter PRS model treats each Gong as a single shot slot in the
 * stage's shot sequence, so a stage's total_shots always equals its gong
 * count. That maps the course of fire exactly:
 *   Stage 1 ladder (1+2+3+4)         = 10 shots  → 10 gongs
 *   Stages 2–5                       =  8 shots  →  8 gongs each
 *   Match total                      = 42 shots
 * Each gong carries the physical target it represents (shape, mm size,
 * distance, mil) so the on-phone stage reference shows the real COF.
 *
 * No roster is seeded — the shooter list is imported from Impact squadding
 * later. Divisions (Open / Factory / Limited) and categories (Mens / Ladies
 * / Senior) are created ready for that import.
 *
 * Re-running wipes and rebuilds this match's target sets so the seeder is
 * idempotent. It never touches shooters, squads or scores.
 */
class PprcCenterfireLegends20260801Seeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'paul@charsley.co.za')->first()
            ?? User::where('role', 'owner')->first();

        if (! $owner) {
            $this->command?->error('No paul@charsley.co.za or owner user found; aborting.');
            return;
        }

        $org = Organization::firstOrCreate(
            ['slug' => 'pretoria-precision-rifle-club'],
            [
                'name' => 'Pretoria Precision Rifle Club',
                'type' => 'club',
                'status' => 'active',
                'created_by' => $owner->id,
                'province' => 'Gauteng',
            ]
        );

        $org->admins()->syncWithoutDetaching([
            $owner->id => [
                'is_owner' => true,
                'is_match_director' => true,
                'is_range_officer' => true,
                'is_shooter' => false,
            ],
        ]);

        $matchName = 'PPRC Centerfire Club Match — 1 August 2026';

        DB::transaction(function () use ($owner, $org, $matchName) {
            $match = ShootingMatch::firstOrNew([
                'organization_id' => $org->id,
                'name' => $matchName,
            ]);

            $isNew = ! $match->exists;

            $match->date = '2026-08-01';
            $match->location = 'Legends Adventure Farm, Rayton';
            $match->status = MatchStatus::Draft;
            $match->scoring_type = 'prs';
            $match->scores_published = false;
            $match->concurrent_relays = 1;
            $match->max_squad_size = 8;
            $match->entry_fee = 0;
            $match->self_squadding_enabled = false;
            $match->royal_flush_enabled = false;
            $match->side_bet_enabled = false;
            $match->notes = 'PPRC Centerfire Club Match at Legends Adventure Farm, Rayton. PRS scoring — 1 point per impact, no partial points, match total 42. Par time 105 s on all stages. Stage 4 "The Piano" is the timed tie-breaker: equal impact totals are ranked by lower Stage 4 time. Roster imported from Impact squadding.';
            $match->created_by = $owner->id;
            $match->save();

            $this->command?->info(($isNew ? 'Created' : 'Updated')." match [{$match->id}] {$match->name}.");

            // Divisions & categories, ready for the Impact roster import.
            foreach (['Open' => 1, 'Factory' => 2, 'Limited' => 3] as $name => $order) {
                MatchDivision::firstOrCreate(
                    ['match_id' => $match->id, 'name' => $name],
                    ['sort_order' => $order]
                );
            }
            foreach (['Mens' => 1, 'Ladies' => 2, 'Senior' => 3] as $name => $order) {
                MatchCategory::firstOrCreate(
                    ['match_id' => $match->id, 'slug' => strtolower($name)],
                    ['name' => $name, 'sort_order' => $order]
                );
            }

            // Wipe & rebuild stages so re-seeding is idempotent.
            foreach ($match->targetSets as $existing) {
                $existing->gongs()->delete();
                $existing->delete();
            }

            foreach ($this->stages() as $i => $stage) {
                $ts = TargetSet::create([
                    'match_id' => $match->id,
                    'label' => $stage['label'],
                    'distance_meters' => 0,
                    'distance_multiplier' => 1,
                    'sort_order' => $i + 1,
                    'stage_number' => $i + 1,
                    'total_shots' => count($stage['gongs']),
                    'par_time_seconds' => 105.0,
                    'is_timed_stage' => $stage['timed'] ?? false,
                    'is_tiebreaker' => $stage['tiebreaker'] ?? false,
                    'notes' => $stage['brief'],
                ]);

                foreach ($stage['gongs'] as $j => $g) {
                    Gong::create([
                        'target_set_id' => $ts->id,
                        'number' => $j + 1,
                        'label' => $g['label'],
                        'multiplier' => 1.00,
                        'distance_meters' => $g['dist'],
                        'target_size_mm' => $g['mm'],
                        'target_size' => "{$g['mm']} mm {$g['shape']} @ {$g['dist']} m · {$g['mil']} mil",
                    ]);
                }
            }

            $total = $match->targetSets()->sum('total_shots');
            $this->command?->info("5 stages seeded, {$total} shots total (Stage 4 = timed tie-breaker).");
            $this->command?->info("Scoreboard URL: /scoreboard/{$match->id}");
        });
    }

    /**
     * Course of fire. Each gong is one shot slot in the stage sequence.
     * shape/mm/dist/mil describe the physical target that slot is fired at.
     *
     * @return array<int,array{label:string,timed?:bool,tiebreaker?:bool,brief:string,gongs:array<int,array{label:string,shape:string,mm:int,dist:int,mil:float}>}>
     */
    private function stages(): array
    {
        // Stage 1 target catalogue (all Ref 3 @ 485 m).
        $s1 = fn (int $k) => [
            1 => ['label' => 'KYL1', 'shape' => 'square', 'mm' => 440, 'dist' => 485, 'mil' => 0.91],
            2 => ['label' => 'KYL2', 'shape' => 'square', 'mm' => 360, 'dist' => 485, 'mil' => 0.74],
            3 => ['label' => 'KYL3', 'shape' => 'square', 'mm' => 270, 'dist' => 485, 'mil' => 0.56],
            4 => ['label' => 'KYL4', 'shape' => 'square', 'mm' => 180, 'dist' => 485, 'mil' => 0.37],
        ][$k];

        // Build the Stage 1 ladder: P1=K1, P2=K1-2, P3=K1-3, P4=K1-4 (10 shots).
        $s1Gongs = [];
        foreach ([1, 2, 3, 4] as $position) {
            foreach (range(1, $position) as $k) {
                $t = $s1($k);
                $s1Gongs[] = ['label' => "P{$position} · {$t['label']}"] + $t;
            }
        }

        $ref11 = ['label' => 'Ref 11', 'shape' => 'circle', 'mm' => 200, 'dist' => 398, 'mil' => 0.50];
        $s4Gongs = [];
        foreach (range(1, 8) as $p) {
            $s4Gongs[] = ['label' => "P{$p} · Ref 11"] + $ref11;
        }

        return [
            [
                'label' => 'Stage 1 — Climbing the Ladder',
                'brief' => "A-Frame & X-Barricade — 10 rounds. Positions: any 4, min 2 per prop, none repeated. Round ladder: P1=1rd (KYL1), P2=2rds (KYL1-2), P3=3rds (KYL1-3), P4=4rds (KYL1-4). Targets all Ref 3 @ 485 m: KYL1 square 440 mm (0.91), KYL2 square 360 mm (0.74), KYL3 square 270 mm (0.56), KYL4 square 180 mm (0.37). Par 105 s.",
                'gongs' => $s1Gongs,
            ],
            [
                'label' => 'Stage 2 — Heavy Rotation',
                'brief' => "Earthmover Tyres — 8 rounds, single position. Sequence: Ref5, Ref5, 19Big, 19Small, 21Big, 21Small, 12Big, 12Small. Par 105 s.",
                'gongs' => [
                    ['label' => 'Ref 5',        'shape' => 'square', 'mm' => 400, 'dist' => 687, 'mil' => 0.58],
                    ['label' => 'Ref 5',        'shape' => 'square', 'mm' => 400, 'dist' => 687, 'mil' => 0.58],
                    ['label' => 'Ref 19 Big',   'shape' => 'square', 'mm' => 400, 'dist' => 576, 'mil' => 0.69],
                    ['label' => 'Ref 19 Small', 'shape' => 'square', 'mm' => 240, 'dist' => 576, 'mil' => 0.42],
                    ['label' => 'Ref 21 Big',   'shape' => 'square', 'mm' => 300, 'dist' => 543, 'mil' => 0.55],
                    ['label' => 'Ref 21 Small', 'shape' => 'square', 'mm' => 150, 'dist' => 543, 'mil' => 0.28],
                    ['label' => 'Ref 12 Big',   'shape' => 'circle', 'mm' => 250, 'dist' => 575, 'mil' => 0.43],
                    ['label' => 'Ref 12 Small', 'shape' => 'circle', 'mm' => 150, 'dist' => 575, 'mil' => 0.26],
                ],
            ],
            [
                'label' => 'Stage 3 — Three Legs Good',
                'brief' => "Stepped Rail Frame — 8 rounds. Any 4 positions, none repeated; TRIPOD COMPULSORY at the rear position (P4). Targets Ref 8 @ 394 m: Big square 250 mm (0.63), Small diamond 150 mm (0.38). Sequence per position: Big, Small × 4 positions. Par 105 s.",
                'gongs' => $this->stage3Gongs(),
            ],
            [
                'label' => 'Stage 4 — The Piano',
                'timed' => true,
                'tiebreaker' => true,
                'brief' => "Staggered Post Barricade — 8 rounds — TIMED TIE-BREAKER. Any 8 positions, none repeated, 1 rd per position. Target Ref 11 circle 200 mm @ 398 m (0.50). Sequence: T1 × 8. Par 105 s; time defaults to 105.00 on a time-out.",
                'gongs' => $s4Gongs,
            ],
            [
                'label' => 'Stage 5 — Raise the Roof',
                'brief' => "Rooftop Ramp — 8 rounds, single position fully on the roof (rifle may be staged; climb on start signal). Sequence: 17K1, 17K2, 17K3, 17K1, NK1, NK2, NK3, NK4. Par 105 s.",
                'gongs' => [
                    ['label' => 'Ref 17 KYL1', 'shape' => 'square', 'mm' => 400, 'dist' => 510, 'mil' => 0.78],
                    ['label' => 'Ref 17 KYL2', 'shape' => 'square', 'mm' => 300, 'dist' => 510, 'mil' => 0.59],
                    ['label' => 'Ref 17 KYL3', 'shape' => 'square', 'mm' => 150, 'dist' => 510, 'mil' => 0.29],
                    ['label' => 'Ref 17 KYL1', 'shape' => 'square', 'mm' => 400, 'dist' => 510, 'mil' => 0.78],
                    ['label' => 'NoRef KYL1',  'shape' => 'square', 'mm' => 350, 'dist' => 504, 'mil' => 0.69],
                    ['label' => 'NoRef KYL2',  'shape' => 'square', 'mm' => 220, 'dist' => 504, 'mil' => 0.44],
                    ['label' => 'NoRef KYL3',  'shape' => 'circle', 'mm' => 250, 'dist' => 504, 'mil' => 0.50],
                    ['label' => 'NoRef KYL4',  'shape' => 'circle', 'mm' => 150, 'dist' => 504, 'mil' => 0.30],
                ],
            ],
        ];
    }

    /** Stage 3: Big/Small pair fired at each of 4 positions (P4 = tripod rear). */
    private function stage3Gongs(): array
    {
        $big   = ['shape' => 'square',  'mm' => 250, 'dist' => 394, 'mil' => 0.63];
        $small = ['shape' => 'diamond', 'mm' => 150, 'dist' => 394, 'mil' => 0.38];
        $gongs = [];
        foreach (range(1, 4) as $p) {
            $suffix = $p === 4 ? ' (tripod)' : '';
            $gongs[] = ['label' => "P{$p} · Big{$suffix}"] + $big;
            $gongs[] = ['label' => "P{$p} · Small{$suffix}"] + $small;
        }
        return $gongs;
    }
}
