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
 * Seeds the Royal Flush match for 22 Aug 2026 (Saturday) with final squadding
 * from Shooters_22Aug2026.xlsx, minus late drop-outs confirmed by JD:
 *   - Reynard van Deventer (Relay 3, Pos 1)
 *   - Diedrik Pretorius (Relay 6)
 *
 * Match is created Ready (tablets can download it; scoring still locked until
 * the MD starts it) with the Side Bet enabled. Standard RF layout: 400/500/600/700 m,
 * 5 gongs each at 1.00–2.00x.
 *
 * Safe to re-run: finds the existing match/org, wipes only this match's shooters,
 * and rewrites the layout. Users are found by case-insensitive name match,
 * otherwise created with a synthetic rf.<slug>@import.invalid email.
 */
class RoyalFlush22Aug2026Seeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'royal-flush')->first()
            ?? Organization::where('slug', 'like', 'royal-flush%')->orderBy('id')->first()
            ?? Organization::where('name', 'Royal Flush')->first();

        if (! $org) {
            $admin = User::where('role', 'owner')->orderBy('id')->first()
                ?? User::orderBy('id')->first();

            if (! $admin) {
                $this->command->error('No users exist; cannot create Royal Flush org. Run DatabaseSeeder first.');

                return;
            }

            $org = Organization::create([
                'slug' => 'royal-flush',
                'name' => 'Royal Flush',
                'description' => 'Year-long precision shooting competition. Compete across multiple matches to claim the top spot on the leaderboard.',
                'type' => 'competition',
                'status' => 'active',
                'created_by' => $admin->id,
                'primary_color' => '#b91c1c',
                'secondary_color' => '#0f172a',
                'hero_text' => 'Royal Flush 2026',
                'hero_description' => 'The ultimate year-long precision shooting competition. Register for matches, submit your scores, and climb the leaderboard.',
                'portal_enabled' => true,
                'portal_entitled' => true,
                'portal_ad_rights' => true,
                'best_of' => 5,
            ]);

            $org->admins()->syncWithoutDetaching([
                $admin->id => ['is_owner' => true],
            ]);

            $this->command?->info("Created Royal Flush organization [{$org->id}] with slug={$org->slug}.");
        } else {
            $this->command?->info("Using organization [{$org->id}] {$org->name} (slug={$org->slug}).");
        }

        $matchName = 'Royal Flush — 22 Aug 2026';
        $matchDate = '2026-08-22';
        $concurrentRelays = 2;
        $maxSquadSize = 10;

        // Shooter layout: [relay][position] => [name, cartridge]
        // Removals vs JD sheet: Reynard van Deventer (R3 P1), Diedrik Pretorius (R6).
        $relays = [
            1 => [
                ['Morne Steyn', '260 Remington'],
                ['Trevor Rowe', '308 Win'],
                ['Zander Els', '6.5 Creedmoor'],
                ['Mohammed Ahmed', '6.5 Creedmoor'],
                ['Gerhardu Odendaal', '300 WSM'],
                ['Werner Bonthuys', '7 SAUM'],
                ['Riaan van Bosch', '6.5 Creedmoor'],
                ['Werner Marx', '243 Win'],
                ['Konrad Grabe', '6mm GT'],
                ['Siebert Noeth', '6 SLR'],
            ],
            2 => [
                ['Andre van der Westhuizen', '6 GT'],
                ['Karien Els', '300 WSM'],
                ['Erwin Potgieter', '7 RSAUM'],
                ['Muhammed Saad Alli', '6.5 Creedmoor'],
                ['Ruan du Plessis', '6.5 PRC'],
                ['Carl Louw', '7 SAUM'],
                ['Plank van der Merwe', '6.5 Creedmoor'],
                ['Abdul Aziz Amod', '6.5 PRC'],
                ['Dewald Hurn', '6.5 Creedmoor'],
                ['Robert van der Merwe', '7 RSAUM'],
            ],
            3 => [
                // Reynard van Deventer removed (can't make it)
                ['Jose Alves', '260 Rem'],
                ['Michael Coutinho', '6.5 Creedmoor'],
                ['Danie van Wyk', '308 Win'],
                ['Stephen de Wet', '300 WSM'],
                ['Riaz Moolla', '6.5 Creedmoor'],
                ['Harry Wassermann', '6.5 Creedmoor'],
                ['Donovan Dauth', '6.5 Creedmoor'],
                ['Alan Searle', '6.5 Creedmoor'],
                ['Cobus Loots', '7 Shehane'],
            ],
            4 => [
                ['Etienne de Waal', '6 GT'],
                ['Andries de Beer', '7 SAUM'],
                ['Jannie Jacobs', '300 Mag'],
                ['Coenie van Tonder', '243 Win'],
                ['Imanuel Coutinho', '308 Win'],
                ['Wilfred Robson', '6.5 Creedmoor'],
                ['Mohamed Daya', '308 Win'],
                ['Jaco van Tonder', '6mm GT'],
                ['Ettienne Hennop', '6.5 Creedmoor'],
                ['Paul Charsley', '6.5 Creedmoor'],
            ],
            5 => [
                ['Darryl van Smaalen', '7 RSAUM'],
                ['Rudi Viljoen', '7 PRC'],
                ['Pieter Grobler', '7 RSAUM'],
                ['Christo Els', '7 Shehane'],
                ['Theo Botha', '280 Ackley Improved'],
                ['Daniel Bonthuys', '7 RSAUM'],
                ['Christo Louw', '7 RSAUM'],
                ['Simon Steyn', '7 RSAUM'],
                ['Wouter Louw', '7 RSAUM'],
                ['Warren Britnell', '25 Creedmoor'],
            ],
            6 => [
                ['Maritza Brümmer', '6.5 Creedmoor'],
                ['John Pauls', '6.5 Creedmoor'],
                ['Louis Rademeyer', '6mm Dasher'],
                // Diedrik Pretorius removed (can't make it)
                ['Ismail Arbee', '6.5 Creedmoor'],
                ['Lee Thompson', '6.5x55 SE'],
                ['Jaco Venter', '6.5 Creedmoor'],
                ['JC Robertson', '308 Win'],
                ['Dirk Pio', '7-6.5 PRCW'],
                ['Steven Coombs', '7mm PRC'],
            ],
            7 => [
                ['Cameron De Wet', '308 Win'],
                ['Hendri van Jaarsveldt', '6.5 Creedmoor'],
                ['De Waal Uys', '6.5 Creedmoor'],
                ['Petrus Wassermann', '6.5x55 SM'],
                ['Reinier Kuschke', '308 Win'],
                ['Schalk vd Merwe', '6.5 Creedmoor'],
                ['Danie Koch', '7mm PRCW'],
                ['Donovan Cook', '25 XC'],
                ['Danie du Preez', '6.5 Creedmoor'],
                ['Jaco Brummer', '6.5 Creedmoor'],
            ],
            8 => [
                ['Joshua Joseph', '7 SAUM'],
                ['Desmond Brummer', '300 Win Mag'],
                ['JD Els', '300 WSM'],
                ['Abdul Aziz Amod 7 PRCW', '7 PRCW'],
                ['Gerrit van Rooyen', '7 SAUM'],
                ["Jakes O'Neill", '6.5 Creedmoor'],
                ['Muzzammil Hassim', '7mm PRC'],
                ['Stephan van der Merwe', '6.5 Creedmoor'],
                ['Johan Nel', '25x47'],
            ],
        ];

        DB::transaction(function () use (
            $org, $matchName, $matchDate, $concurrentRelays, $maxSquadSize, $relays
        ) {
            $match = ShootingMatch::withTrashed()->firstOrNew([
                'organization_id' => $org->id,
                'name' => $matchName,
                'date' => $matchDate,
            ]);
            if ($match->exists && $match->trashed()) {
                $match->restore();
                $this->command?->info("Restored archived match [{$match->id}].");
            }
            if (! $match->exists) {
                $match->status = MatchStatus::Ready;
            }
            $match->royal_flush_enabled = true;
            $match->side_bet_enabled = true;
            $match->concurrent_relays = $concurrentRelays;
            $match->max_squad_size = $maxSquadSize;
            $match->scoring_type = in_array($match->scoring_type, ['standard', 'prs', 'elr'], true)
                ? $match->scoring_type
                : 'standard';
            $match->self_squadding_enabled = false;
            $match->created_by = $match->created_by ?? User::query()->value('id');
            $match->save();

            $this->command?->info("Match [{$match->id}] {$match->name} ready.");

            $rfDistances = [400, 500, 600, 700];
            $gongMultipliers = ['1.00', '1.25', '1.50', '1.75', '2.00'];
            foreach ($rfDistances as $i => $distance) {
                $ts = TargetSet::firstOrCreate(
                    ['match_id' => $match->id, 'distance_meters' => $distance],
                    [
                        'label' => "{$distance}m",
                        'distance_multiplier' => $distance / 100,
                        'sort_order' => $i + 1,
                    ]
                );
                $ts->fill([
                    'label' => "{$distance}m",
                    'distance_multiplier' => $distance / 100,
                    'sort_order' => $i + 1,
                ])->save();

                $existing = Gong::where('target_set_id', $ts->id)->orderBy('number')->get();
                $byNumber = $existing->keyBy('number');
                for ($n = 1; $n <= 5; $n++) {
                    $mult = $gongMultipliers[$n - 1];
                    if ($byNumber->has($n)) {
                        $byNumber[$n]->fill(['label' => "G{$n}", 'multiplier' => $mult])->save();
                    } else {
                        Gong::create([
                            'target_set_id' => $ts->id,
                            'number' => $n,
                            'label' => "G{$n}",
                            'multiplier' => $mult,
                        ]);
                    }
                }
            }
            $this->command?->info('Target sets ensured: 400/500/600/700 m × 5 gongs with RF multipliers.');

            Shooter::whereIn('squad_id', Squad::where('match_id', $match->id)->pluck('id'))
                ->delete();

            $squadByNum = [];
            foreach ($relays as $num => $_) {
                $squad = Squad::firstOrCreate(
                    ['match_id' => $match->id, 'name' => "Relay {$num}"],
                    ['sort_order' => $num, 'max_capacity' => $maxSquadSize]
                );
                $squad->fill(['sort_order' => $num, 'max_capacity' => $maxSquadSize])->save();
                $squadByNum[$num] = $squad;
            }

            $stats = ['users_created' => 0, 'users_existing' => 0, 'shooters_placed' => 0];

            foreach ($relays as $num => $rows) {
                $squad = $squadByNum[$num];
                foreach ($rows as $pos => [$name, $caliber]) {
                    $user = User::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
                    if (! $user) {
                        $slug = Str::slug($name, '.');
                        $email = "rf.{$slug}@import.invalid";
                        if (User::where('email', $email)->exists()) {
                            $email = 'rf.'.$slug.'.'.substr(md5($name.$num.$pos), 0, 6).'@import.invalid';
                        }
                        $user = User::create([
                            'name' => $name,
                            'email' => $email,
                            'password' => bcrypt(Str::random(32)),
                        ]);
                        $stats['users_created']++;
                    } else {
                        $stats['users_existing']++;
                    }

                    $reg = MatchRegistration::firstOrCreate(
                        ['match_id' => $squad->match_id, 'user_id' => $user->id],
                        [
                            'payment_status' => 'confirmed',
                            'payment_reference' => MatchRegistration::generatePaymentReference($user),
                            'amount' => 0,
                            'is_free_entry' => true,
                        ]
                    );
                    if (empty($reg->caliber)) {
                        $reg->caliber = $caliber;
                        $reg->save();
                    }

                    Shooter::create([
                        'squad_id' => $squad->id,
                        'name' => "{$name} — {$caliber}",
                        'user_id' => $user->id,
                        'sort_order' => $pos + 1,
                        'status' => 'active',
                    ]);
                    $stats['shooters_placed']++;
                }
            }

            $this->command?->info("Users created: {$stats['users_created']}, reused: {$stats['users_existing']}, shooters placed: {$stats['shooters_placed']}");
            $this->command?->info('Drop-outs excluded: Reynard van Deventer (R3), Diedrik Pretorius (R6).');
        });
    }
}
