<?php

namespace Database\Seeders;

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\MatchCategory;
use App\Models\MatchDivision;
use App\Models\MatchRegistration;
use App\Models\Organization;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PPRC PRS test match seeder.
 *
 * Config:
 *  - Hosted by Pretoria Precision Rifle Club (PPRC)
 *  - PRS scoring, 2 squads × 6 shooters = 12 placeholder shooters (Paul is
 *    owner / created_by, NOT a shooter)
 *  - 5 stages, Stage 1 is the tiebreaker with a 105-second par time; other
 *    stages are untimed (no per-shooter time recorded)
 *  - 6 shots per stage
 *
 * Re-running wipes and rebuilds this match's target sets, squads, and
 * shooters so the seeder is idempotent. Use the admin "Delete Forever"
 * button to purge the match entirely afterwards.
 */
class PaulPprcPrsTestSeeder extends Seeder
{
    public function run(): void
    {
        $paul = User::where('email', 'paul@charsley.co.za')->first()
            ?? User::where('role', 'owner')->first();

        if (! $paul) {
            $this->command->error('No user paul@charsley.co.za or owner found; aborting.');
            return;
        }

        $org = Organization::firstOrCreate(
            ['slug' => 'pretoria-precision-rifle-club'],
            [
                'name' => 'Pretoria Precision Rifle Club',
                'type' => 'club',
                'status' => 'active',
                'created_by' => $paul->id,
            ]
        );

        // Ensure Paul is an admin on PPRC so he can manage this test match.
        $org->admins()->syncWithoutDetaching([
            $paul->id => [
                'is_owner' => true,
                'is_match_director' => true,
                'is_range_officer' => true,
                'is_shooter' => false,
            ],
        ]);

        $matchName = 'PPRC PRS Test — 5 stages × 6 shots (105s Tiebreaker)';

        DB::transaction(function () use ($paul, $org, $matchName) {
            $match = ShootingMatch::firstOrNew([
                'organization_id' => $org->id,
                'name' => $matchName,
            ]);

            $isNew = ! $match->exists;

            $match->date = now()->toDateString();
            $match->location = 'Pretoria Precision Rifle Club Range';
            $match->status = MatchStatus::Active;
            $match->scoring_type = 'prs';
            $match->scores_published = true;
            $match->concurrent_relays = 2;
            $match->max_squad_size = 6;
            $match->entry_fee = 0;
            $match->self_squadding_enabled = false;
            $match->royal_flush_enabled = false;
            $match->side_bet_enabled = false;
            $match->notes = 'PPRC PRS test match — 5 stages × 6 shots. Stage 1 is the tiebreaker with a 105-second par time and per-shooter time recorded. Stages 2–5 are untimed (no per-shooter time). 2 squads × 6 shooters. Paul is owner, not a shooter.';
            $match->created_by = $paul->id;
            $match->save();

            $this->command?->info(($isNew ? 'Created' : 'Updated')." match [{$match->id}] {$match->name}.");

            $divOpen    = MatchDivision::firstOrCreate(['match_id' => $match->id, 'name' => 'Open'],    ['sort_order' => 1]);
            $divFactory = MatchDivision::firstOrCreate(['match_id' => $match->id, 'name' => 'Factory'], ['sort_order' => 2]);
            $divLimited = MatchDivision::firstOrCreate(['match_id' => $match->id, 'name' => 'Limited'], ['sort_order' => 3]);

            $catOverall = MatchCategory::firstOrCreate(
                ['match_id' => $match->id, 'slug' => 'overall'],
                ['name' => 'Overall', 'sort_order' => 1]
            );

            // Wipe and rebuild stages so re-seeding is idempotent.
            foreach ($match->targetSets as $existing) {
                $existing->gongs()->delete();
                $existing->delete();
            }

            $stages = [
                [
                    'label' => 'Stage 1 — Tiebreaker',
                    'par' => 105.0,
                    'timed' => true,
                    'tiebreaker' => true,
                    'targets' => [
                        ['label' => 'T1', 'distance' => 300, 'size' => '1 MOA'],
                        ['label' => 'T2', 'distance' => 400, 'size' => '1 MOA'],
                        ['label' => 'T3', 'distance' => 500, 'size' => '1 MOA'],
                        ['label' => 'T4', 'distance' => 600, 'size' => '1 MOA'],
                        ['label' => 'T5', 'distance' => 700, 'size' => '1 MOA'],
                        ['label' => 'T6', 'distance' => 800, 'size' => '1 MOA'],
                    ],
                ],
                [
                    'label' => 'Stage 2 — Prone',
                    'par' => null,
                    'timed' => false,
                    'tiebreaker' => false,
                    'targets' => [
                        ['label' => 'T1', 'distance' => 350, 'size' => '2 MOA'],
                        ['label' => 'T2', 'distance' => 400, 'size' => '2 MOA'],
                        ['label' => 'T3', 'distance' => 450, 'size' => '1.5 MOA'],
                        ['label' => 'T4', 'distance' => 500, 'size' => '1.5 MOA'],
                        ['label' => 'T5', 'distance' => 550, 'size' => '1.5 MOA'],
                        ['label' => 'T6', 'distance' => 600, 'size' => '1.5 MOA'],
                    ],
                ],
                [
                    'label' => 'Stage 3 — Barricade',
                    'par' => null,
                    'timed' => false,
                    'tiebreaker' => false,
                    'targets' => [
                        ['label' => 'T1', 'distance' => 400, 'size' => '2 MOA'],
                        ['label' => 'T2', 'distance' => 450, 'size' => '1.5 MOA'],
                        ['label' => 'T3', 'distance' => 500, 'size' => '1.5 MOA'],
                        ['label' => 'T4', 'distance' => 550, 'size' => '1.5 MOA'],
                        ['label' => 'T5', 'distance' => 600, 'size' => '1 MOA'],
                        ['label' => 'T6', 'distance' => 650, 'size' => '1 MOA'],
                    ],
                ],
                [
                    'label' => 'Stage 4 — Rooftop',
                    'par' => null,
                    'timed' => false,
                    'tiebreaker' => false,
                    'targets' => [
                        ['label' => 'T1', 'distance' => 500, 'size' => '2 MOA'],
                        ['label' => 'T2', 'distance' => 550, 'size' => '1.5 MOA'],
                        ['label' => 'T3', 'distance' => 600, 'size' => '1.5 MOA'],
                        ['label' => 'T4', 'distance' => 650, 'size' => '1.5 MOA'],
                        ['label' => 'T5', 'distance' => 700, 'size' => '1 MOA'],
                        ['label' => 'T6', 'distance' => 750, 'size' => '1 MOA'],
                    ],
                ],
                [
                    'label' => 'Stage 5 — Positional',
                    'par' => null,
                    'timed' => false,
                    'tiebreaker' => false,
                    'targets' => [
                        ['label' => 'T1', 'distance' => 600, 'size' => '2 MOA'],
                        ['label' => 'T2', 'distance' => 650, 'size' => '1.5 MOA'],
                        ['label' => 'T3', 'distance' => 700, 'size' => '1.5 MOA'],
                        ['label' => 'T4', 'distance' => 750, 'size' => '1.5 MOA'],
                        ['label' => 'T5', 'distance' => 800, 'size' => '1 MOA'],
                        ['label' => 'T6', 'distance' => 850, 'size' => '1 MOA'],
                    ],
                ],
            ];

            foreach ($stages as $i => $stage) {
                $ts = TargetSet::create([
                    'match_id' => $match->id,
                    'label' => $stage['label'],
                    'distance_meters' => 0,
                    'distance_multiplier' => 1,
                    'sort_order' => $i + 1,
                    'is_tiebreaker' => $stage['tiebreaker'],
                    'par_time_seconds' => $stage['par'],
                    'total_shots' => count($stage['targets']),
                    'stage_number' => $i + 1,
                    'is_timed_stage' => $stage['timed'],
                ]);

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

            // Wipe and rebuild squads/shooters so re-seeding is idempotent.
            Squad::where('match_id', $match->id)->get()->each(function (Squad $squad) {
                $squad->shooters()->delete();
                $squad->delete();
            });

            $squadA = Squad::create([
                'match_id' => $match->id,
                'name' => 'Squad A',
                'sort_order' => 1,
                'max_capacity' => 6,
            ]);
            $squadB = Squad::create([
                'match_id' => $match->id,
                'name' => 'Squad B',
                'sort_order' => 2,
                'max_capacity' => 6,
            ]);

            $roster = [
                ['squad' => $squadA, 'pos' => 1, 'name' => 'Test Shooter A1', 'caliber' => '6.5 Creedmoor', 'division' => $divOpen],
                ['squad' => $squadA, 'pos' => 2, 'name' => 'Test Shooter A2', 'caliber' => '6 Dasher',      'division' => $divOpen],
                ['squad' => $squadA, 'pos' => 3, 'name' => 'Test Shooter A3', 'caliber' => '308 Win',       'division' => $divFactory],
                ['squad' => $squadA, 'pos' => 4, 'name' => 'Test Shooter A4', 'caliber' => '6 GT',          'division' => $divLimited],
                ['squad' => $squadA, 'pos' => 5, 'name' => 'Test Shooter A5', 'caliber' => '6.5 PRC',       'division' => $divOpen],
                ['squad' => $squadA, 'pos' => 6, 'name' => 'Test Shooter A6', 'caliber' => '284 Shehane',   'division' => $divOpen],
                ['squad' => $squadB, 'pos' => 1, 'name' => 'Test Shooter B1', 'caliber' => '6.5 Creedmoor', 'division' => $divOpen],
                ['squad' => $squadB, 'pos' => 2, 'name' => 'Test Shooter B2', 'caliber' => '6 Dasher',      'division' => $divOpen],
                ['squad' => $squadB, 'pos' => 3, 'name' => 'Test Shooter B3', 'caliber' => '308 Win',       'division' => $divFactory],
                ['squad' => $squadB, 'pos' => 4, 'name' => 'Test Shooter B4', 'caliber' => '6 GT',          'division' => $divLimited],
                ['squad' => $squadB, 'pos' => 5, 'name' => 'Test Shooter B5', 'caliber' => '7 SAUM',        'division' => $divOpen],
                ['squad' => $squadB, 'pos' => 6, 'name' => 'Test Shooter B6', 'caliber' => '25x47',         'division' => $divOpen],
            ];

            $bibNumber = 1;

            foreach ($roster as $entry) {
                $slug = Str::slug($entry['name'], '.');
                $email = "test.pprc.{$slug}@import.invalid";
                $user = User::where('email', $email)->first();
                if (! $user) {
                    $user = User::create([
                        'name' => $entry['name'],
                        'email' => $email,
                        'password' => bcrypt(Str::random(32)),
                    ]);
                }

                $reg = MatchRegistration::firstOrCreate(
                    ['match_id' => $match->id, 'user_id' => $user->id],
                    [
                        'payment_status' => 'confirmed',
                        'payment_reference' => MatchRegistration::generatePaymentReference($user),
                        'amount' => 0,
                        'is_free_entry' => true,
                    ]
                );
                if (empty($reg->caliber)) {
                    $reg->caliber = $entry['caliber'];
                    $reg->save();
                }

                $shooter = Shooter::create([
                    'squad_id' => $entry['squad']->id,
                    'name' => "{$entry['name']} — {$entry['caliber']}",
                    'user_id' => $user->id,
                    'bib_number' => 'PP-' . str_pad((string) $bibNumber, 2, '0', STR_PAD_LEFT),
                    'match_division_id' => $entry['division']->id,
                    'sort_order' => $entry['pos'],
                    'status' => 'active',
                ]);
                $shooter->categories()->syncWithoutDetaching([$catOverall->id]);

                $bibNumber++;
            }

            $this->command?->info('Organization: Pretoria Precision Rifle Club (PPRC).');
            $this->command?->info('Squads: Squad A (A1–A6), Squad B (B1–B6).');
            $this->command?->info('Stages: 5 × 6 shots; Stage 1 tiebreaker with 105s par; Stages 2–5 untimed.');
            $this->command?->info("Scoreboard URL: /scoreboard/{$match->id}");
        });
    }
}
