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
 * Seeds the Royal Flush match for 27 Jun 2026 (Saturday) with final squadding
 * (10 relays, 99 shooters) and per-shooter cartridge as supplied by the MD
 * (Shooters_27Jun2026.xlsx).
 *
 * Match is created Ready (tablets can download it; scoring still locked until
 * the MD starts it) with the Side Bet enabled. Standard RF layout: 400/500/600/700 m,
 * 5 gongs each at 1.00–2.00x.
 *
 * Safe to re-run: finds the existing match/org, wipes only this match's shooters,
 * and rewrites the layout. Users are found by case-insensitive name match,
 * otherwise created with a synthetic rf.<slug>@import.invalid email.
 */
class RoyalFlush27Jun2026Seeder extends Seeder
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

        $matchName = 'Royal Flush — 27 Jun 2026';
        $matchDate = '2026-06-27';
        $concurrentRelays = 2;
        $maxSquadSize = 10;

        // Shooter layout: [relay][position] => [name, cartridge]
        $relays = [
            1 => [
                ['Richard Meissner', '300 WSM'],
                ['Jacques van Aardt', '6.5 Creedmoor'],
                ['Johann Yssel', '260 Rem'],
                ['Philip Venter', '284 Win'],
                ['Petrus Wassermann', '6.5x55'],
                ['Handre Truter', '6.5 Creedmoor'],
                ['Jodé Mostert', '300 WSM'],
                ['Wilfred Robson', '6.5 Creedmoor'],
                ['Leo Liebenberg', '6.5 Creedmoor'],
                ['Michael Coutinho', '6.5 Creedmoor'],
            ],
            2 => [
                ['Reinier Kuschke', '308 Win'],
                ['Drikus Moolman', '308 Win'],
                ['Jose Alves', '260 Rem'],
                ['Gerrie Lotter', '284 Win'],
                ['Brian Koen', '7 PRC'],
                ['Julius Hartmann', '308 Win'],
                ['Dries Bekker', '6.5 Creedmoor'],
                ['Kobus Verwoerd', '6.5 PRC'],
                ['Andre PJ van der Westhuizen', '6 GT'],
                ['Trevor Graham', '6.5 Creedmoor'],
            ],
            3 => [
                ['Corinne Liebenberg', '6.5 Creedmoor'],
                ['Dewald Hurn', '7 RSAUM'],
                ['JD Els', '22 GT'],
                ['Pieter Grobler', '6 Dasher'],
                ['Erwin Potgieter', '7 RSAUM'],
                ['Dominic Kroezen', '300 Win Mag'],
                ['Danie Koch', '7 PRCW'],
                ['Johan Smith', '308 Win'],
                ['Estian Janse van Rensburg', '300 WSM'],
                ['Morton Mynhardt', '6.5 PRC'],
            ],
            4 => [
                ['Pieter Meyer', '260 Rem'],
                ['Kyle van Rooyen', '7 PRC'],
                ['Gerrit van Rooyen', '7 RSAUM'],
                ['Jonty Dobrowsky', '6.5 Creedmoor'],
                ['Andries de beer', '7 RSAUM'],
                ['Kenny Smit', '300 PRC'],
                ['Jp Liebenberg', '6.5 Creedmoor'],
                ['Brian Beeming', '7 PRC'],
                ['Jordan De-Caris', '308 Win'],
                ['Martin Erasmus(snr)', '7 RSAUM'],
            ],
            5 => [
                ['Quinten kok', '6.5 Creedmoor'],
                ['Steve Dyke', '6 XC'],
                ['Carl Louw', '7 PRC'],
                ['Werner Bonthuys', '7 RSAUM'],
                ['Gerhardu Odendaal', '300 WSM'],
                ['Donovan Dauth', '6.5 Creedmoor'],
                ['Reynard van Deventer', '6.5 PRC'],
                ['Christo Els', '284 Shehane'],
                ['Muhammad Dhudhat', '6.5 Creedmoor'],
                ['Johan Volschenk', '6.5 Creedmoor'],
            ],
            6 => [
                ['AJ Snyman', '6 Dasher'],
                ['Fred vd Westhuizen', '6 Dasher'],
                ['Aiden Boshoff', '243 Win'],
                ['Phillip Oosthuizen', '300 Win Mag'],
                ['Zander Els', '6.5 Creedmoor'],
                ['Simon Steyn', '7 RSAUM'],
                ['Juandre Stroebel', '6 Creedmoor'],
                ['Pieter Grobler 2', '7 RSAUM'],
                ['Rudi Viljoen', '7 PRC'],
                ['Warren Britnell', '7 PRC'],
            ],
            7 => [
                ['Alan Searle', '6.5 Creedmoor'],
                ['Coenie van Tonder', '6 GT'],
                ['Diedrik Pretorius', '300 PRC'],
                ['Theo Botha', '7 RSAUM'],
                ['Ruan du Plessis', '6 Creedmoor'],
                ['Franco Wiid', '7 PRC'],
                ['Gert Loots', '7 PRC'],
                ['Stefan van der linde', '6 XC'],
                ['Francois Davel', '284 Win'],
                ['Wayne van Rooyen', '7 PRC'],
            ],
            8 => [
                ['Shaun Flink', '300 Norma Magnum'],
                ['Mohamed Ayob', '308 Win'],
                ['Jason McLean', '260 Rem'],
                ['Werner Deyzel', '6 Creedmoor'],
                ['Plank van der merwe', '6.5 Creedmoor'],
                ['Harry Wassermann', '6.5 Creedmoor'],
                ['Alex Pienaar', '6 Dasher'],
                ['Skye Liebenberg', '6.5 Creedmoor'],
                ['Steven Coombs', '7 PRC'],
                ['Johannes Thomas', '0.284'],
            ],
            9 => [
                ['Lizette Els', '7 RSAUM'],
                ['Chris Badenhorst', '7 PRC'],
                ['Schalk van der Merwe', '6.5 Creedmoor'],
                ['Francois van der Walt', '7 Dakota'],
                ['Jaco van der Merwe', '260 Rem'],
                ['Deon de Villiers', '6.5 Creedmoor'],
                ['Martin Erasmus(jnr)', '284 Win'],
                ['Jason Odendaal', '6.5 Creedmoor'],
                ['Jannie Jacobs', '6 SLR'],
                ['Kobie Nel', '300 Norma Magnum'],
            ],
            10 => [
                ['Danie du Preez', '6.5 Creedmoor'],
                ['Imanuel Coutinho', '243 Win'],
                ['Alan Hewetson', '300 PRC'],
                ['Abdul Aziz Amod', '6.5 PRC'],
                ['MC van Tonder', '6 GT'],
                ['Cameron De Wet', '308 Win'],
                ['Anton de Jager', '7 RSAUM'],
                ['Ismail Arbee', '6.5 Creedmoor'],
                ['Daniel Bonthuys', '7 RSAUM'],
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
