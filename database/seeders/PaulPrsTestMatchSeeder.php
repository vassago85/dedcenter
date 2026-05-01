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
 * Seeds a PRS test match for tomorrow's 2-tablet DeadCenter run.
 *
 * Config:
 *  - 5 stages, stage 1 is the tiebreaker
 *  - 8 shots (8 targets) per stage
 *  - 2 squads × 4 shooters = 8 placeholder shooters (Paul is NOT a shooter;
 *    he remains the match owner / created_by)
 *  - Scoring type: prs, status: active
 *  - Organization: Royal Flush (or first active org as fallback)
 *
 * Re-running wipes and re-creates this match's target sets, squads, and
 * shooters so you can iterate safely. Use the admin "Delete Forever" button
 * to purge the match entirely afterwards.
 */
class PaulPrsTestMatchSeeder extends Seeder
{
    public function run(): void
    {
        $paul = User::where('email', 'paul@charsley.co.za')->first()
            ?? User::where('role', 'owner')->first();

        if (! $paul) {
            $this->command->error('No user paul@charsley.co.za or owner found; aborting.');
            return;
        }

        $org = Organization::where('slug', 'royal-flush')->first()
            ?? Organization::where('slug', 'like', 'royal-flush%')->orderBy('id')->first()
            ?? Organization::where('status', 'active')->orderBy('id')->first()
            ?? Organization::orderBy('id')->first();

        if (! $org) {
            $this->command->error('No organization found; aborting.');
            return;
        }

        $matchName = 'Paul PRS Test — 5×8 / 2 squads';

        DB::transaction(function () use ($paul, $org, $matchName) {
            $match = ShootingMatch::firstOrNew([
                'organization_id' => $org->id,
                'name' => $matchName,
            ]);

            $isNew = ! $match->exists;

            $match->date = now()->toDateString();
            $match->location = 'Test Range';
            $match->status = MatchStatus::Active;
            $match->scoring_type = 'prs';
            $match->scores_published = true;
            $match->concurrent_relays = 2;
            $match->max_squad_size = 4;
            $match->entry_fee = 0;
            $match->self_squadding_enabled = false;
            $match->royal_flush_enabled = false;
            $match->side_bet_enabled = false;
            $match->notes = 'PRS test match — 5 stages (stage 1 tiebreaker), 2 squads × 4 shooters, 8 shots per stage. Paul is owner, not a shooter.';
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

            foreach ($match->targetSets as $existing) {
                $existing->gongs()->delete();
                $existing->delete();
            }

            $stages = [
                [
                    'label' => 'Stage 1 — Tiebreaker',
                    'par' => 60.0,
                    'timed' => true,
                    'tiebreaker' => true,
                    'targets' => [
                        ['label' => 'T1', 'distance' => 300, 'size' => '1 MOA'],
                        ['label' => 'T2', 'distance' => 300, 'size' => '1 MOA'],
                        ['label' => 'T3', 'distance' => 400, 'size' => '1 MOA'],
                        ['label' => 'T4', 'distance' => 400, 'size' => '1 MOA'],
                        ['label' => 'T5', 'distance' => 500, 'size' => '1 MOA'],
                        ['label' => 'T6', 'distance' => 500, 'size' => '1 MOA'],
                        ['label' => 'T7', 'distance' => 600, 'size' => '1 MOA'],
                        ['label' => 'T8', 'distance' => 600, 'size' => '1 MOA'],
                    ],
                ],
                [
                    'label' => 'Stage 2 — Prone',
                    'par' => 120.0,
                    'timed' => true,
                    'tiebreaker' => false,
                    'targets' => [
                        ['label' => 'T1', 'distance' => 300, 'size' => '2 MOA'],
                        ['label' => 'T2', 'distance' => 300, 'size' => '2 MOA'],
                        ['label' => 'T3', 'distance' => 350, 'size' => '2 MOA'],
                        ['label' => 'T4', 'distance' => 350, 'size' => '2 MOA'],
                        ['label' => 'T5', 'distance' => 400, 'size' => '1.5 MOA'],
                        ['label' => 'T6', 'distance' => 400, 'size' => '1.5 MOA'],
                        ['label' => 'T7', 'distance' => 450, 'size' => '1.5 MOA'],
                        ['label' => 'T8', 'distance' => 450, 'size' => '1.5 MOA'],
                    ],
                ],
                [
                    'label' => 'Stage 3 — Barricade',
                    'par' => 120.0,
                    'timed' => true,
                    'tiebreaker' => false,
                    'targets' => [
                        ['label' => 'T1', 'distance' => 400, 'size' => '2 MOA'],
                        ['label' => 'T2', 'distance' => 400, 'size' => '2 MOA'],
                        ['label' => 'T3', 'distance' => 450, 'size' => '1.5 MOA'],
                        ['label' => 'T4', 'distance' => 450, 'size' => '1.5 MOA'],
                        ['label' => 'T5', 'distance' => 500, 'size' => '1.5 MOA'],
                        ['label' => 'T6', 'distance' => 500, 'size' => '1.5 MOA'],
                        ['label' => 'T7', 'distance' => 550, 'size' => '1 MOA'],
                        ['label' => 'T8', 'distance' => 550, 'size' => '1 MOA'],
                    ],
                ],
                [
                    'label' => 'Stage 4 — Rooftop',
                    'par' => 150.0,
                    'timed' => true,
                    'tiebreaker' => false,
                    'targets' => [
                        ['label' => 'T1', 'distance' => 500, 'size' => '2 MOA'],
                        ['label' => 'T2', 'distance' => 500, 'size' => '2 MOA'],
                        ['label' => 'T3', 'distance' => 550, 'size' => '1.5 MOA'],
                        ['label' => 'T4', 'distance' => 550, 'size' => '1.5 MOA'],
                        ['label' => 'T5', 'distance' => 600, 'size' => '1.5 MOA'],
                        ['label' => 'T6', 'distance' => 600, 'size' => '1.5 MOA'],
                        ['label' => 'T7', 'distance' => 650, 'size' => '1 MOA'],
                        ['label' => 'T8', 'distance' => 650, 'size' => '1 MOA'],
                    ],
                ],
                [
                    'label' => 'Stage 5 — Positional',
                    'par' => 150.0,
                    'timed' => true,
                    'tiebreaker' => false,
                    'targets' => [
                        ['label' => 'T1', 'distance' => 600, 'size' => '2 MOA'],
                        ['label' => 'T2', 'distance' => 600, 'size' => '2 MOA'],
                        ['label' => 'T3', 'distance' => 650, 'size' => '1.5 MOA'],
                        ['label' => 'T4', 'distance' => 650, 'size' => '1.5 MOA'],
                        ['label' => 'T5', 'distance' => 700, 'size' => '1.5 MOA'],
                        ['label' => 'T6', 'distance' => 700, 'size' => '1.5 MOA'],
                        ['label' => 'T7', 'distance' => 750, 'size' => '1 MOA'],
                        ['label' => 'T8', 'distance' => 750, 'size' => '1 MOA'],
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
                'max_capacity' => 4,
            ]);
            $squadB = Squad::create([
                'match_id' => $match->id,
                'name' => 'Squad B',
                'sort_order' => 2,
                'max_capacity' => 4,
            ]);

            $roster = [
                ['squad' => $squadA, 'pos' => 1, 'name' => 'Test Shooter A1', 'caliber' => '6.5 Creedmoor', 'division' => $divOpen],
                ['squad' => $squadA, 'pos' => 2, 'name' => 'Test Shooter A2', 'caliber' => '6 Dasher',      'division' => $divOpen],
                ['squad' => $squadA, 'pos' => 3, 'name' => 'Test Shooter A3', 'caliber' => '308 Win',       'division' => $divFactory],
                ['squad' => $squadA, 'pos' => 4, 'name' => 'Test Shooter A4', 'caliber' => '6 GT',          'division' => $divLimited],
                ['squad' => $squadB, 'pos' => 1, 'name' => 'Test Shooter B1', 'caliber' => '6.5 Creedmoor', 'division' => $divOpen],
                ['squad' => $squadB, 'pos' => 2, 'name' => 'Test Shooter B2', 'caliber' => '6 Dasher',      'division' => $divOpen],
                ['squad' => $squadB, 'pos' => 3, 'name' => 'Test Shooter B3', 'caliber' => '308 Win',       'division' => $divFactory],
                ['squad' => $squadB, 'pos' => 4, 'name' => 'Test Shooter B4', 'caliber' => '6 GT',          'division' => $divLimited],
            ];

            $bibNumber = 1;

            foreach ($roster as $entry) {
                $slug = Str::slug($entry['name'], '.');
                $email = "test.{$slug}@import.invalid";
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
                    'bib_number' => 'PT-' . str_pad((string) $bibNumber, 2, '0', STR_PAD_LEFT),
                    'match_division_id' => $entry['division']->id,
                    'sort_order' => $entry['pos'],
                    'status' => 'active',
                ]);
                $shooter->categories()->syncWithoutDetaching([$catOverall->id]);

                $bibNumber++;
            }

            $this->command?->info('Squads: Squad A (A1–A4), Squad B (B1–B4). Paul is owner/created_by, not a shooter.');
            $this->command?->info('Stages: 5 × 8 targets; Stage 1 is the tiebreaker.');
            $this->command?->info("Scoreboard URL: /scoreboard/{$match->id}");
        });
    }
}
