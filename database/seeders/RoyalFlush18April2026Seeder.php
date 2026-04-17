<?php

namespace Database\Seeders;

use App\Enums\MatchStatus;
use App\Models\MatchRegistration;
use App\Models\Organization;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds the Royal Flush match for 18 April 2026 with the final squadding
 * (8 relays, 73 shooters) and per-shooter caliber as supplied in the MD brief.
 *
 * Safe to re-run: finds the existing match/org, wipes only this match's shooters,
 * and rewrites the layout. Users are found by case-insensitive name match,
 * otherwise created with a synthetic rf.<slug>@import.invalid email.
 */
class RoyalFlush18April2026Seeder extends Seeder
{
    public function run(): void
    {
        // Prefer exact slug; fall back to any slug starting with "royal-flush"
        // (e.g. "royal-flush-1" on servers where the slug was auto-incremented),
        // and finally match by name.
        $org = Organization::where('slug', 'royal-flush')->first()
            ?? Organization::where('slug', 'like', 'royal-flush%')->orderBy('id')->first()
            ?? Organization::where('name', 'Royal Flush')->first();

        if (! $org) {
            $this->command->error('Royal Flush organization not found. Run DatabaseSeeder first.');
            return;
        }

        $this->command?->info("Using organization [{$org->id}] {$org->name} (slug={$org->slug}).");

        $matchName = 'Royal Flush — 18 April 2026';
        $matchDate = '2026-04-18';
        $concurrentRelays = 2;
        $maxSquadSize = 10;

        // Shooter layout: [relay][position] => [name, caliber]
        $relays = [
            1 => [
                ['Sarel Van Der Merwe', '6.5 Creedmoor'],
                ['Will Grobler', '300 WSM'],
                ['Trevor Graham', '6.5 Creedmoor'],
                ['Bruce Godfrey', '308 Win'],
                ['Petrus Wassermann', '6.5x55SM'],
                ['Jakes O\'Neill', '7 RSAUM'],
                ['Brandon Ulrich', '6.5 Creedmoor'],
                ['Ismail Arbee', '6.5 Creedmoor'],
                ['Stephan Van Der Merwe', '6.5 Creedmoor'],
            ],
            2 => [
                ['Jannie Jacobs', '6 SLR'],
                ['Wilfred Robson', '6.5 Creedmoor'],
                ['Imanuel Coutinho', '243 Win'],
                ['Jose Alves', '260 Rem'],
                ['Herbert Schmitz 2', '308 Win'],
                ['Jaco Venter', '6.5 Creedmoor'],
                ['Werner Deyzel', '6.5 Creedmoor'],
                ['Erwin Potgieter', '7 RSAUM'],
                ['Danie Kruger', '308 Win'],
            ],
            3 => [
                ['Danie Koch', '7 PRCW'],
                ['Emil Engelbrecht', '7 RSAUM'],
                ['JD Els', '300 WSM'],
                ['Francois Davel', '308 Win'],
                ['Jacques Kriek', '6.5 Creedmoor'],
                ['Adam Levin', '6.5 Creedmoor'],
                ['Louis Raubenheimer', '6.5 Creedmoor'],
                ['Daniel Bonthuys', '6.5 Creedmoor'],
                ['Diedrik Pretorius', '300 PRC'],
            ],
            4 => [
                ['Reinier Kuschke', '308 Win'],
                ['Jeane Van Der Merwe', '6.5 Creedmoor'],
                ['AJ Snyman', '6 Dasher'],
                ['Morton Mynhardt', '6.5 PRC'],
                ['Rudolph Louw', '300 WSM'],
                ['Andre Combrink', '308 Win'],
                ['Dries Bekker', '6.5 Creedmoor'],
                ['Herbert Schmitz 1', '223 Rem'],
                ['Andries De Beer', '7 RSAUM'],
            ],
            5 => [
                ['Kobie Nel', '300 Norma Mag'],
                ['Morton Mynhardt', '6.5 PRC'],
                ['Morne Van Der Merwe', '7 PRCW'],
                ['Alex Pienaar', '6 Dasher'],
                ['Julius Hartmann', '308 Win'],
                ['Andre PJ Van Der Westhuizen', '6 GT'],
                ['Anton De Jager', '7 PRC'],
                ['Robert Meintjes', '7 RSAUM'],
                ['Harry Wassermann', '6.5 Creedmoor'],
            ],
            6 => [
                ['Steven Coombs', '7 PRC'],
                ['Deon De Villiers', '6.5 Creedmoor'],
                ['Ruan Benadie', '6.5 Creedmoor'],
                ['Rudi Viljoen', '7 PRC'],
                ['Kobus Verwoerd', '6.5 PRC'],
                ['Simon Steyn', '7 RSAUM'],
                ['Shaun Flink', '284 Shehane'],
                ['Carel Saayman', '6 Creedmoor'],
                ['Johan Lottering', '6 BRA'],
            ],
            7 => [
                ['Gerrit Van Rooyen', '30 Sherman Mag'],
                ['Johan Nel', '6 BR'],
                ['Mohamed Ayob', '308 Win'],
                ['Shaun Snyman', '300 WSM'],
                ['Henri Koopper', '6x46'],
                ['Ettienne Hennop', '6.5 Creedmoor'],
                ['Liesl Baben', '6.5 Creedmoor'],
                ['Francois Van Der Walt', '7 Dakota'],
                ['Estian Janse Van Rensburg', '300 WSM'],
            ],
            8 => [
                ['Johan Du Plessis', '6.5 Creedmoor'],
                ['Gerhardu Odendaal', '300 WSM'],
                ['Johan Smith', '308 Win'],
                ['Werner Bonthuys', '6.5 Creedmoor'],
                ['Thys De Beer', '300 Norma Mag'],
                ['Steve Dyke', '243 Win'],
                ['Dw De Klerk', '7 RSAUM'],
                ['Fred Vd Westhuizen', '6 Dasher'],
                ['Ruan Du Plessis', '6.5 PRC'],
                ['Chris Pretorius', '6.5 Creedmoor'],
            ],
        ];

        DB::transaction(function () use (
            $org, $matchName, $matchDate, $concurrentRelays, $maxSquadSize, $relays
        ) {
            $match = ShootingMatch::firstOrNew([
                'organization_id' => $org->id,
                'name' => $matchName,
                'date' => $matchDate,
            ]);
            if (! $match->exists) {
                $match->status = MatchStatus::SquaddingOpen;
            }
            $match->royal_flush_enabled = true;
            $match->concurrent_relays = $concurrentRelays;
            $match->max_squad_size = $maxSquadSize;
            $match->scoring_type = $match->scoring_type ?? 'royal_flush';
            $match->self_squadding_enabled = false;
            $match->created_by = $match->created_by ?? User::query()->value('id');
            $match->save();

            $this->command?->info("Match [{$match->id}] {$match->name} ready.");

            $squadByNum = [];
            foreach ($relays as $num => $_) {
                $squad = Squad::firstOrCreate(
                    ['match_id' => $match->id, 'name' => "Relay {$num}"],
                    ['sort_order' => $num, 'max_capacity' => $maxSquadSize]
                );
                $squad->fill(['sort_order' => $num, 'max_capacity' => $maxSquadSize])->save();
                Shooter::where('squad_id', $squad->id)->delete();
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
                            $email = "rf.".$slug.".".substr(md5($name.$num.$pos), 0, 6)."@import.invalid";
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
