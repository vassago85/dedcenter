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
 * Seeds a small test match for native-app / scoring testing.
 *
 * Config: 8 shooters across 2 relays (4 each), including paul@charsley.co.za,
 * 2 target banks (400m and 500m) with 3 gongs per bank.
 *
 * Safe to re-run: wipes this match's shooters/targets/gongs and rewrites them.
 */
class PaulSmallTestMatchSeeder extends Seeder
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

        $matchName = 'Paul Test — Small (8×2×3)';
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
            $match->max_squad_size = 4;
            $match->entry_fee = 0;
            $match->self_squadding_enabled = false;
            $match->royal_flush_enabled = false;
            $match->notes = 'Small test match (8 shooters, 2 relays, 2 distances × 3 gongs).';
            $match->created_by = $paul->id;
            $match->save();

            $this->command?->info(($isNew ? 'Created' : 'Updated')." match [{$match->id}] {$match->name}.");

            foreach ($match->targetSets as $existing) {
                $existing->gongs()->delete();
                $existing->delete();
            }

            $stages = [
                [
                    'label' => '400m Bank',
                    'distance' => 400,
                    'distance_multiplier' => 4.00,
                    'sort_order' => 1,
                    'gongs' => [
                        ['number' => 1, 'label' => '2.0 MOA', 'multiplier' => 1.00],
                        ['number' => 2, 'label' => '1.5 MOA', 'multiplier' => 1.25],
                        ['number' => 3, 'label' => '1.0 MOA', 'multiplier' => 1.50],
                    ],
                ],
                [
                    'label' => '500m Bank',
                    'distance' => 500,
                    'distance_multiplier' => 5.00,
                    'sort_order' => 2,
                    'gongs' => [
                        ['number' => 1, 'label' => '2.0 MOA', 'multiplier' => 1.25],
                        ['number' => 2, 'label' => '1.5 MOA', 'multiplier' => 1.50],
                        ['number' => 3, 'label' => '1.0 MOA', 'multiplier' => 2.00],
                    ],
                ],
            ];

            foreach ($stages as $stage) {
                $targetSet = TargetSet::create([
                    'match_id' => $match->id,
                    'label' => $stage['label'],
                    'distance_meters' => $stage['distance'],
                    'distance_multiplier' => $stage['distance_multiplier'],
                    'sort_order' => $stage['sort_order'],
                    'is_tiebreaker' => false,
                ]);
                foreach ($stage['gongs'] as $g) {
                    Gong::create([
                        'target_set_id' => $targetSet->id,
                        'number' => $g['number'],
                        'label' => $g['label'],
                        'multiplier' => $g['multiplier'],
                        'distance_meters' => $stage['distance'],
                    ]);
                }
            }

            $relayOne = Squad::firstOrCreate(
                ['match_id' => $match->id, 'name' => 'Relay 1'],
                ['sort_order' => 1, 'max_capacity' => 4]
            );
            $relayOne->fill(['sort_order' => 1, 'max_capacity' => 4])->save();

            $relayTwo = Squad::firstOrCreate(
                ['match_id' => $match->id, 'name' => 'Relay 2'],
                ['sort_order' => 2, 'max_capacity' => 4]
            );
            $relayTwo->fill(['sort_order' => 2, 'max_capacity' => 4])->save();

            Shooter::whereIn('squad_id', [$relayOne->id, $relayTwo->id])->delete();

            $roster = [
                ['relay' => $relayOne, 'pos' => 1, 'name' => 'Paul Charsley',        'caliber' => '6.5 Creedmoor', 'user' => $paul],
                ['relay' => $relayOne, 'pos' => 2, 'name' => 'Test Shooter Bravo',   'caliber' => '308 Win',       'user' => null],
                ['relay' => $relayOne, 'pos' => 3, 'name' => 'Test Shooter Charlie', 'caliber' => '6 Dasher',      'user' => null],
                ['relay' => $relayOne, 'pos' => 4, 'name' => 'Test Shooter Delta',   'caliber' => '6.5 PRC',       'user' => null],
                ['relay' => $relayTwo, 'pos' => 1, 'name' => 'Test Shooter Echo',    'caliber' => '6.5 Creedmoor', 'user' => null],
                ['relay' => $relayTwo, 'pos' => 2, 'name' => 'Test Shooter Foxtrot', 'caliber' => '308 Win',       'user' => null],
                ['relay' => $relayTwo, 'pos' => 3, 'name' => 'Test Shooter Golf',    'caliber' => '300 WSM',       'user' => null],
                ['relay' => $relayTwo, 'pos' => 4, 'name' => 'Test Shooter Hotel',   'caliber' => '223 Rem',       'user' => null],
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

            $this->command?->info('Roster placed: Relay 1 (Paul, Bravo, Charlie, Delta), Relay 2 (Echo, Foxtrot, Golf, Hotel).');
            $this->command?->info('Stages: 400m × 3 gongs, 500m × 3 gongs.');
            $this->command?->info("Scoreboard URL: /scoreboard/{$match->id}");
        });
    }
}
