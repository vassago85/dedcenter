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
 * Seeds the Royal Flush match for 3 Oct 2026 (Saturday) with final squadding
 * from Shooters_3Oct2026_V2.xlsx (10 relays, 96 shooters).
 *
 * Match is created Ready (tablets can download it; scoring still locked until
 * the MD starts it) with the Side Bet enabled. Standard RF layout: 400/500/600/700 m,
 * 5 gongs each at 1.00–2.00x.
 *
 * Safe to re-run: finds the existing match/org, wipes only this match's shooters,
 * and rewrites the layout. Users are found by case-insensitive name match,
 * otherwise created with a synthetic rf.<slug>@import.invalid email.
 *
 * Second-rifle entries keep the cartridge suffix from the sheet (e.g. "JD Els 284Win")
 * so they get their own registration and scorecard.
 */
class RoyalFlush3Oct2026Seeder extends Seeder
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

        $matchName = 'Royal Flush — 3 Oct 2026';
        $matchDate = '2026-10-03';
        $concurrentRelays = 2;
        $maxSquadSize = 10;

        // Shooter layout: [relay][position] => [name, cartridge]
        // Source: Shooters_3Oct2026_V2.xlsx (as supplied by MD).
        $relays = [
            1 => [
                ['Jose Alves', '284 GAP'],
                ['Zander Els', '6.5 Creedmoor'],
                ['Stian de Witt', '25 Creedmoor'],
                ['Joel Joseph', '6.5 Creedmoor'],
                ['Richard Meissner', '7 PRC'],
                ['Claudio Correia', '6 Creedmoor'],
                ['Dylan Human', '6.5 Creedmoor'],
                ['Danny Abreu', '260 Remington'],
                ['Jean kleynhans', '6.5 Creedmoor'],
                ['Coenie van Tonder', '243 Win'],
            ],
            2 => [
                ['Pierre van der Merwe', '6.5 Creedmoor'],
                ['Andre Brummer', '6.5 Creedmoor'],
                ['Robert van der Merwe', '6.5 Creedmoor'],
                ['JD Els', '300 WSM'],
                ['Darryl van Smaalen', '7 RSAUM'],
                ['Ismail Arbee', '6.5 Creedmoor'],
                ['Mohamed Daya', '308 Win'],
                ['Ruan du Plessis', '6 Dasher'],
                ['Danie du Preez', '6.5 Creedmoor'],
                ['Robert Meintjes', '6 Dasher'],
            ],
            3 => [
                ['Rowann Hattingh', '6 Dasher'],
                ['Rayno van der Walt', '6.5 Creedmoor'],
                ['Elrine de Witt', '25 Creedmoor'],
                ['Reinier Kuschke', '308 Win'],
                ['Michael Coutinho', '6.5 Creedmoor'],
                ['JD Els 284Win', '284 Win'],
                ['Alex Pienaar', '6 Dasher'],
                ['Jaco de Wet', '308 Win'],
                ['Willie van Aardt', '308 Win'],
                ['Christo Els 6 Creedmoor', '6 Creedmoor'],
            ],
            4 => [
                ['Francois Van Wyk 6XC', '6 XC'],
                ['Desmond Brummer', '6.5 Creedmoor'],
                ['Francois Davel', '284 Win'],
                ['Chane Vorster', '260 Ackley'],
                ['Anton de Jager', '6.5 Creedmoor'],
                ['AIDEN BOSHOFF', '243 Win'],
                ['Jordan Joseph', '7 RSAUM'],
                ['Johannes Thomas', '284 Win'],
                ['Wernu Bekker', '7 PRC'],
                ['Jason Mclean', '6 Dasher'],
            ],
            5 => [
                ["Alex Terre'Blanche", '6.5 Creedmoor'],
                ['Louis Hennings', '6.5 Creedmoor'],
                ['Warren Britnell', '25 Creedmoor'],
                ['De Waal Uys', '6.5 Creedmoor'],
                ['Harry Wassermann', '6.5 Creedmoor'],
                ['Werner Marx', '243 Win'],
                ['Alan Searle', '6.5 Creedmoor'],
                ['Shaun Snyman', '300 WSM'],
                ["Jakes O'Neill", '7 RSAUM'],
                ['Konrad Grabe', '6 GT'],
            ],
            6 => [
                ['Karien Els', '300 WSM'],
                ['Stephan van der Merwe', '6.5 Creedmoor'],
                ['Brian Koen', '7mm PRC'],
                ['Dewald Hurn', '7 RSAUM'],
                ['Andre van der Westhuizen', '6 GT'],
                ['Schalk van der Merwe', '6.5 Creedmoor'],
                ['Paul Charsley', '6.5mm Creedmoor'],
                ['Clayton Human', '6.5 Creedmoor'],
                ['Wilfred Robson', '6.5 Creedmoor'],
                ['Brian Beeming', '7mm PRC'],
            ],
            7 => [
                ['Christo Els', '284 Shehane'],
                ['Jannie Jacobs', '300 Blaser'],
                ['Hendri van Jaarsveldt', '6.5 Creedmoor'],
                ['Fred vd Westhuizen', '6 Dasher'],
                ['Mohamed Ayob', '308 Win'],
                ['Simon Steyn', '7 RSAUM'],
                ['Zander Els 6 Creedmoor', '6 Creedmoor'],
                ['Renier Zietsman', '6.5 Creedmoor'],
                ['Gerhardu Odendaal', '300 WSM'],
                ['Rudi Viljoen', '7 PRC'],
            ],
            8 => [
                ['Louis Rademeyer', '6 Dasher'],
                ['Louis Raubenheimer', '6.5CM'],
                ['Abri Potgieter', '6.5 Creedmoor'],
                ['Gerrit van Rooyen', '300 Norma Magnum'],
                ['Andries de beer', '7 RSAUM'],
                ['Jaco Brummer', '6.5 Creedmoor'],
                ['Kobie nel', '300 Norma Magnum'],
                ['Trevor Rowe', '308 Win'],
                ['Plank van der merwe', '7 RSAUM'],
                ['Xavier Badenhorst', '7 PRC'],
            ],
            9 => [
                ['Steven Coombs', '7 PRC'],
                ['Werner Bonthuys', '7 RSAUM'],
                ['Reinhardt Swanepoel', '6.5 PRC'],
                ['Daniel Bonthuys', '7 PRC'],
                ['Wouter Louw', '7 RSAUM'],
                ['Morton Mynhardt', '6.5 PRC'],
                ['Christo Louw', '7 RSAUM'],
                ['Niel Symington', '6.5 PRC'],
                ['Lee Thompson', '6 Dasher'],
                ['Carl Louw', '7 RSAUM'],
            ],
            10 => [
                ['Franco Wiid', '7 PRC'],
                ['Petrus Wassermann', '6.5x55SM'],
                ['Jaco Venter', '6.5 Creedmoor'],
                ['Danie Viljoen', '6.5 Creedmoor'],
                ['Erwin Potgieter', '7 RSAUM'],
                ['Francois Van Wyk', '300 Win Mag'],
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
        });
    }
}
