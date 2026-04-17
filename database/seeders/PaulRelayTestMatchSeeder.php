<?php

namespace Database\Seeders;

use App\Enums\MatchStatus;
use App\Models\Gong;
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
 * Seeds a small 1-stage relay-type test match for native-app testing.
 *
 * Config: 2 relays x 2 shooters (4 total) including paul@charsley.co.za,
 * one 400m target bank with 5 gongs (2.5/2.0/1.5/1.0/0.5 MOA).
 *
 * Safe to re-run: wipes this match's shooters/targets/gongs and rewrites them.
 */
class PaulRelayTestMatchSeeder extends Seeder
{
    public function run(): void
    {
        $paul = User::where('email', 'paul@charsley.co.za')->first()
            ?? User::where('role', 'owner')->first();

        if (! $paul) {
            $this->command->error('No user paul@charsley.co.za or owner found; aborting.');
            return;
        }

        // Prefer Royal Flush org; fall back to any active org; else any org.
        $org = Organization::where('slug', 'royal-flush')->first()
            ?? Organization::where('slug', 'like', 'royal-flush%')->orderBy('id')->first()
            ?? Organization::where('status', 'active')->orderBy('id')->first()
            ?? Organization::orderBy('id')->first();

        if (! $org) {
            $this->command->error('No organization found; aborting.');
            return;
        }

        $matchName = 'Paul Test — Relay';
        $matchDate = now()->toDateString();

        $this->command?->info("Using organization [{$org->id}] {$org->name}.");

        DB::transaction(function () use ($paul, $org, $matchName, $matchDate) {
            $match = ShootingMatch::firstOrNew([
                'organization_id' => $org->id,
                'name' => $matchName,
            ]);

            $isNew = ! $match->exists;

            $match->date = $matchDate;
            $match->location = 'Test Range';
            $match->status = MatchStatus::Active;
            $match->scoring_type = 'standard';
            $match->scores_published = true;
            $match->concurrent_relays = 2;
            $match->max_squad_size = 2;
            $match->entry_fee = 0;
            $match->self_squadding_enabled = false;
            $match->royal_flush_enabled = false;
            $match->notes = 'Small relay-type test match (4 shooters, 2 relays, 1 stage).';
            $match->created_by = $paul->id;
            $match->save();

            $this->command?->info(($isNew ? 'Created' : 'Updated')." match [{$match->id}] {$match->name}.");

            // ── 1 stage: 400m bank with 5 gongs ──
            // Wipe existing target sets (and their gongs via cascade / our explicit delete)
            foreach ($match->targetSets as $existing) {
                $existing->gongs()->delete();
                $existing->delete();
            }

            $targetSet = TargetSet::create([
                'match_id' => $match->id,
                'label' => '400m Bank',
                'distance_meters' => 400,
                'distance_multiplier' => 4.00,
                'sort_order' => 1,
                'is_tiebreaker' => false,
            ]);

            $gongConfig = [
                ['number' => 1, 'label' => '2.5 MOA', 'multiplier' => 1.00],
                ['number' => 2, 'label' => '2.0 MOA', 'multiplier' => 1.25],
                ['number' => 3, 'label' => '1.5 MOA', 'multiplier' => 1.50],
                ['number' => 4, 'label' => '1.0 MOA', 'multiplier' => 2.00],
                ['number' => 5, 'label' => '0.5 MOA', 'multiplier' => 3.00],
            ];
            foreach ($gongConfig as $g) {
                Gong::create([
                    'target_set_id' => $targetSet->id,
                    'number' => $g['number'],
                    'label' => $g['label'],
                    'multiplier' => $g['multiplier'],
                    'distance_meters' => 400,
                ]);
            }

            // ── 2 relays x 2 shooters ──
            $relayOne = Squad::firstOrCreate(
                ['match_id' => $match->id, 'name' => 'Relay 1'],
                ['sort_order' => 1, 'max_capacity' => 2]
            );
            $relayOne->fill(['sort_order' => 1, 'max_capacity' => 2])->save();

            $relayTwo = Squad::firstOrCreate(
                ['match_id' => $match->id, 'name' => 'Relay 2'],
                ['sort_order' => 2, 'max_capacity' => 2]
            );
            $relayTwo->fill(['sort_order' => 2, 'max_capacity' => 2])->save();

            // Reset shooters for idempotency
            Shooter::whereIn('squad_id', [$relayOne->id, $relayTwo->id])->delete();

            $roster = [
                ['relay' => $relayOne, 'pos' => 1, 'name' => 'Paul Charsley', 'caliber' => '6.5 Creedmoor', 'user' => $paul],
                ['relay' => $relayOne, 'pos' => 2, 'name' => 'Test Shooter Bravo', 'caliber' => '308 Win', 'user' => null],
                ['relay' => $relayTwo, 'pos' => 1, 'name' => 'Test Shooter Charlie', 'caliber' => '6.5 Creedmoor', 'user' => null],
                ['relay' => $relayTwo, 'pos' => 2, 'name' => 'Test Shooter Delta', 'caliber' => '6 Dasher', 'user' => null],
            ];

            foreach ($roster as $entry) {
                $user = $entry['user'];

                if (! $user) {
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

                Shooter::create([
                    'squad_id' => $entry['relay']->id,
                    'name' => "{$entry['name']} — {$entry['caliber']}",
                    'user_id' => $user->id,
                    'sort_order' => $entry['pos'],
                    'status' => 'active',
                ]);
            }

            $this->command?->info('Roster placed: Relay 1 (Paul + Bravo), Relay 2 (Charlie + Delta).');
            $this->command?->info("Scoreboard URL: /scoreboard/{$match->id}");
        });
    }
}
