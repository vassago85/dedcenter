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
 * Quick one-stage tiebreaker-only test match.
 *
 * Purpose: the smallest possible PRS match we can use to sanity-check the
 * full scoring flow end-to-end — squad → stage → scoring → tiebreaker ranking
 * → scoreboard → match report — without setting up a 5- or 6-stage match.
 *
 * Config:
 *  - 1 stage total, and that stage IS the tiebreaker (timed, par 60s)
 *  - 8 shots on the tiebreaker stage
 *  - 2 squads × 4 shooters = 8 placeholder shooters (Paul is owner, not a shooter)
 *  - Scoring type: prs, status: active
 *  - Organization: Royal Flush (or first active org as fallback) — same as the
 *    other Paul test seeders, so it doesn't touch the PPRC host org.
 *
 * Re-running is idempotent: target sets, squads, and shooters are wiped and
 * rebuilt. Safe to run repeatedly during testing.
 */
class PaulSingleStageTiebreakerTestSeeder extends Seeder
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

        $matchName = 'Paul PRS Test — 1× Tiebreaker Only';

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
            $match->notes = 'Single-stage PRS test. The ONLY stage is the tiebreaker (timed, 60s par). Used to verify the scoring flow, tiebreaker ranking, and report rendering on the smallest possible match.';
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

            // Wipe existing stages so re-seed is idempotent.
            foreach ($match->targetSets as $existing) {
                $existing->gongs()->delete();
                $existing->delete();
            }

            // The ONE stage is the tiebreaker. Timed, 60s par, 8 shots at
            // mixed distances so the flow exercises both near and far targets.
            $ts = TargetSet::create([
                'match_id' => $match->id,
                'label' => 'Stage 1 — Tiebreaker',
                'distance_meters' => 0,
                'distance_multiplier' => 1,
                'sort_order' => 1,
                'is_tiebreaker' => true,
                'par_time_seconds' => 60.0,
                'total_shots' => 8,
                'stage_number' => 1,
                'is_timed_stage' => true,
            ]);

            $targets = [
                ['label' => 'T1', 'distance' => 300, 'size' => '1 MOA'],
                ['label' => 'T2', 'distance' => 300, 'size' => '1 MOA'],
                ['label' => 'T3', 'distance' => 400, 'size' => '1 MOA'],
                ['label' => 'T4', 'distance' => 400, 'size' => '1 MOA'],
                ['label' => 'T5', 'distance' => 500, 'size' => '1 MOA'],
                ['label' => 'T6', 'distance' => 500, 'size' => '1 MOA'],
                ['label' => 'T7', 'distance' => 600, 'size' => '1 MOA'],
                ['label' => 'T8', 'distance' => 600, 'size' => '1 MOA'],
            ];
            foreach ($targets as $j => $t) {
                Gong::create([
                    'target_set_id' => $ts->id,
                    'number' => $j + 1,
                    'label' => $t['label'],
                    'multiplier' => 1.00,
                    'distance_meters' => $t['distance'],
                    'target_size' => $t['size'],
                ]);
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
                    'bib_number' => 'TB-' . str_pad((string) $bibNumber, 2, '0', STR_PAD_LEFT),
                    'match_division_id' => $entry['division']->id,
                    'sort_order' => $entry['pos'],
                    'status' => 'active',
                ]);
                $shooter->categories()->syncWithoutDetaching([$catOverall->id]);

                $bibNumber++;
            }

            $this->command?->info('Squads: Squad A (A1–A4), Squad B (B1–B4). Paul is owner/created_by, not a shooter.');
            $this->command?->info('Stages: 1 × 8 targets — the only stage IS the tiebreaker (60s par).');
            $this->command?->info("Scoreboard URL: /scoreboard/{$match->id}");
        });
    }
}
