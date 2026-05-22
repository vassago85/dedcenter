<?php

namespace Database\Seeders;

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\MatchRegistration;
use App\Models\Organization;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Minimal Royal Flush match for scoring-app testing with two phones.
 *
 * 2 concurrent relays × 2 shooters (4 total). Phone A scores Relay 1,
 * phone B scores Relay 2. Full RF distance banks (400–700 m).
 *
 * Safe to re-run: wipes scores/shooters and rebuilds roster + gongs.
 */
class RoyalFlushTwoPhoneTestSeeder extends Seeder
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
            ?? Organization::where('name', 'Royal Flush')->first();

        if (! $org) {
            $this->command->error('Royal Flush organization not found. Run DatabaseSeeder first.');

            return;
        }

        $matchName = 'Royal Flush — Two Phone Test';
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
            $match->royal_flush_enabled = true;
            $match->side_bet_enabled = true;
            $match->notes = 'RF two-phone test: Relay 1 (Paul + Alpha), Relay 2 (Bravo + Charlie). Re-seed clears scores.';
            $match->created_by = $paul->id;
            $match->save();

            $this->command?->info(($isNew ? 'Created' : 'Updated')." match [{$match->id}] {$match->name}.");

            $rfDistances = [400, 500, 600, 700];
            $gongMultipliers = ['1.00', '1.25', '1.50', '1.75', '2.00'];

            foreach ($match->targetSets as $existing) {
                $existing->gongs()->delete();
                $existing->delete();
            }

            foreach ($rfDistances as $i => $distance) {
                $ts = TargetSet::create([
                    'match_id' => $match->id,
                    'label' => "{$distance}m",
                    'distance_meters' => $distance,
                    'distance_multiplier' => $distance / 100,
                    'sort_order' => $i + 1,
                ]);

                for ($n = 1; $n <= 5; $n++) {
                    Gong::create([
                        'target_set_id' => $ts->id,
                        'number' => $n,
                        'label' => "G{$n}",
                        'multiplier' => $gongMultipliers[$n - 1],
                    ]);
                }
            }

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

            $shooterIds = Shooter::whereIn('squad_id', [$relayOne->id, $relayTwo->id])->pluck('id');
            Score::whereIn('shooter_id', $shooterIds)->delete();
            Shooter::whereIn('squad_id', [$relayOne->id, $relayTwo->id])->delete();

            $roster = [
                ['relay' => $relayOne, 'pos' => 1, 'name' => 'Paul Charsley', 'caliber' => '6.5 Creedmoor', 'user' => $paul],
                ['relay' => $relayOne, 'pos' => 2, 'name' => 'Test Alpha', 'caliber' => '308 Win', 'user' => null],
                ['relay' => $relayTwo, 'pos' => 1, 'name' => 'Test Bravo', 'caliber' => '6.5 Creedmoor', 'user' => null],
                ['relay' => $relayTwo, 'pos' => 2, 'name' => 'Test Charlie', 'caliber' => '6 Dasher', 'user' => null],
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

                MatchRegistration::firstOrCreate(
                    ['match_id' => $match->id, 'user_id' => $user->id],
                    [
                        'payment_status' => 'confirmed',
                        'payment_reference' => MatchRegistration::generatePaymentReference($user),
                        'amount' => 0,
                        'is_free_entry' => true,
                        'caliber' => $entry['caliber'],
                    ]
                );

                Shooter::create([
                    'squad_id' => $entry['relay']->id,
                    'name' => "{$entry['name']} — {$entry['caliber']}",
                    'user_id' => $user->id,
                    'sort_order' => $entry['pos'],
                    'status' => 'active',
                ]);
            }

            $this->command?->info('Relay 1: Paul Charsley + Test Alpha');
            $this->command?->info('Relay 2: Test Bravo + Test Charlie');
            $this->command?->info("Match hub: /org/{$org->slug}/matches/{$match->id}");
            $this->command?->info("Scoreboard: /scoreboard/{$match->id}");
            $this->command?->info('Log in on both phones as paul@charsley.co.za (or MD token), pick this match, assign relays.');
        });
    }
}
