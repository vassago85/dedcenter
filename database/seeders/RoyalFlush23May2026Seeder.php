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
 * Seeds the Royal Flush match for 23 May 2026 (Saturday) with final squadding
 * (10 relays, 89 shooters) and per-shooter cartridge as supplied by the MD.
 *
 * Safe to re-run: finds the existing match/org, wipes only this match's shooters,
 * and rewrites the layout. Users are found by case-insensitive name match,
 * otherwise created with a synthetic rf.<slug>@import.invalid email.
 */
class RoyalFlush23May2026Seeder extends Seeder
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

        $matchName = 'Royal Flush — 23 May 2026';
        $matchDate = '2026-05-23';
        $concurrentRelays = 2;
        $maxSquadSize = 10;

        // Shooter layout: [relay][position] => [name, cartridge]
        $relays = [
            1 => [
                ['Wilfred Robson', '6.5 Creedmoor'],
                ['Donovan Dauth', '6.5 Creedmoor'],
                ['Chané Jacobsohn', '7mm RSAUM'],
                ['Ronan Gamble', '22-250 Rem'],
                ['Steven Dyke', '6XC'],
                ['Erwin Potgieter', '7 RSAUM'],
                ['Francois Davel', '284 Win'],
                ['Leo Liebenberg', '6.5 Creedmoor'],
                ['Karien Els', '300 WSM'],
            ],
            2 => [
                ['Skye Liebenberg', '6.5 Creedmoor'],
                ['Carel Saayman', '6 Creedmoor'],
                ['Carl Louw', '7 RSAUM'],
                ['Eesaa Ellemdeen', '6.5 Creedmoor'],
                ['Schalk Van Der Merwe', '6.5 Creedmoor'],
                ['Mohamed Daya', '308 Win'],
                ['Chris Badenhorst', '7 PRC'],
                ['Theo Vermaak', '6.5 Creedmoor'],
                ['Ruan Benadie', '6.5 Creedmoor'],
            ],
            3 => [
                ['Anton De Jager', '7 PRC'],
                ['Paul Charsley', '6.5 Creedmoor'],
                ['Danie Koch', '7 PRCW'],
                ['Jacoben Swanepoel', '6.5 Creedmoor'],
                ['Diedrik Pretorius', '300 PRC'],
                ['Reinier Geel', '6.5 Creedmoor'],
                ['Alexi Viljoen', '300 WSM'],
                ['Tiaan Gomes', '6.5 PRC'],
                ['Werner Bonthuys', '6.5 Creedmoor'],
            ],
            4 => [
                ['Jordan De-Caris', '308 Win'],
                ['Rudolph Louw', '300 WSM'],
                ['Dw De Klerk', '7 RSAUM'],
                ['Brian Koen', '7 PRC'],
                ['Muzzammil Hassim', '7 PRC'],
                ['Norman Van Der Mescht', '6.5 PRC'],
                ['Connie De Toit', '6.5 Creedmoor'],
                ['Stef Le Roux', '6.5 PRC'],
                ['Alex Pienaar', '6 Dasher'],
            ],
            5 => [
                ['Werner Deyzel', '6.5 Creedmoor'],
                ['Johan Bam', '6.5 Creedmoor'],
                ['Jose Alves', '260 Rem'],
                ['Henri Klopper', '6x47 Lapua'],
                ['Handre Truter', '6.5 Creedmoor'],
                ['Jaco Venter', '6.5 Creedmoor'],
                ['JD Els', '300 WSM'],
                ['Emil Engelbrecht', '7 RSAUM'],
                ['Jakes O\'Neill', '7 RSAUM'],
            ],
            6 => [
                ['Craig Van Der Riet', '6.5 Creedmoor'],
                ['Danie Viljoen', '300 WSM'],
                ['Corinne Liebenberg', '6.5 Creedmoor'],
                ['Jannie Jacobs', '300 Blaser Magnum'],
                ['Jason McLean', '260 Rem'],
                ['Lizette Els', '7 RSAUM'],
                ['Dirk Oosthuizen', '308 Win'],
                ['Jacques Prinsloo', '300 WSM'],
                ['Rudi Viljoen', '7 PRC'],
            ],
            7 => [
                ['Shaun Snyman', '300 WSM'],
                ['Arno Oosthuizen', '243 Win'],
                ['Mohammed Ahmed', '6.5 Creedmoor'],
                ['Kobus Verwoerd', '6.5 PRC'],
                ['Brian Beeming', '7 PRC'],
                ['Steven Coombs', '7 PRC'],
                ['Alan Searle', '6.5 Creedmoor'],
                ['Robert Meintjes', '7 RSAUM'],
                ['Johan Du Plessis', '6.5 Creedmoor'],
            ],
            8 => [
                ['Gerhardu Odendaal', '300 WSM'],
                ['Ockie Van Schalkwyk', '6.5 Creedmoor'],
                ['Brendon Bieldt', '6.5 PRC'],
                ['Danie Wolmarans', '6.5 Creedmoor'],
                ['Philip Venter', '284 Win'],
                ['Alan Hewetson', '6.5 Creedmoor'],
                ['Jodé Mostert', '300 WSM'],
                ['Andries Fourie', '6.5 Creedmoor'],
                ['Brandon Ulrich', '6.5 Creedmoor'],
            ],
            9 => [
                ['Francois Van Der Walt', '7mm Dakota'],
                ['Morton Mynhardt', '6.5 PRC'],
                ['Fred Vd Westhuizen', '6 Dasher'],
                ['Shaun Flink', '300 SMF'],
                ['Zander Swart', '300 Win Mag'],
                ['Dominic Kroezen', '300 Win Mag'],
                ['Daniel Bonthuys', '6.5 Creedmoor'],
                ['Simon Steyn', '7 RSAUM'],
                ['Gwendie Drury', '6.5 Creedmoor'],
            ],
            10 => [
                ['Gerrit Van Rooyen', '30 Sherman Magnum'],
                ['Andries De Beer', '7 RSAUM'],
                ['Danie Du Preez', '6.5 Creedmoor'],
                ['Julius Hartmann', '308 Win'],
                ['Richard Meissner', '300 PRC'],
                ['Stephan Lambrecht', '300 PRC'],
                ['Siebert Noeth', '6 SLR'],
                ['Kobie Nel', '300 Norma Magnum'],
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
                $match->status = MatchStatus::SquaddingOpen;
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
