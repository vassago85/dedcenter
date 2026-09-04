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
 * Seeds the Royal Flush match for 5 Sept 2026 (Saturday) with final squadding
 * from Shooters_5Sept2026.xlsx (6 relays, 59 shooters).
 *
 * Match is created Ready (tablets can download it; scoring still locked until
 * the MD starts it) with the Side Bet enabled. Standard RF layout: 400/500/600/700 m,
 * 5 gongs each at 1.00–2.00x.
 *
 * Safe to re-run: finds the existing match/org, wipes only this match's shooters,
 * and rewrites the layout. Users are found by case-insensitive name match,
 * otherwise created with a synthetic rf.<slug>@import.invalid email.
 *
 * Note: Jose Alves cartridge was Excel-coerced to 0.26 in the sheet → restored to 260 Rem.
 */
class RoyalFlush5Sept2026Seeder extends Seeder
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

        $matchName = 'Royal Flush — 5 Sept 2026';
        $matchDate = '2026-09-05';
        $concurrentRelays = 2;
        $maxSquadSize = 10;

        // Shooter layout: [relay][position] => [name, cartridge]
        // Source: Shooters_5Sept2026.xlsx (as supplied by MD).
        $relays = [
            1 => [
                ['Franco Wiid', '7 PRC'],
                ['Maritza Brummer', '6.5 Creedmoor'],
                ['Jordan De-Caris', '308 win'],
                ['Abdul Aziz Amod 6.5 CM', '6.5 Creedmoor'],
                ['Dirkie Spies', '6mm Creedmoor'],
                ['Johannes Thomas', '284Win'],
                ['Ruan Benadie', '6.5 Creedmoor'],
                ['Daniel Bonthuys', '7PRC'],
                ['Erwin Potgieter', '7 RSAUM'],
                ['Andre Brummer', '6.5 creedmoor'],
            ],
            2 => [
                ['Symington', '6.5prc'],
                ['Donovan Dauth', '6.5 Creedmoor'],
                ['Christo Els', '284 Shehane'],
                ['Siebert Noeth', '6SLR'],
                ['Zander Els', '6.5mm Creermoor'],
                ['Louwrens Verwoerd', '243Win'],
                ['Fred vd Westhuizen', '6 Dasher'],
                ['Werner Bonthuys', '7 RSAUM'],
                ['Rudi Viljien', '7 PRC'],
                ['Michael Coutinho', '6.5 Creedmoor'],
            ],
            3 => [
                ['Lee Thompson', '6 Dasher'],
                ['Mohamed Daya', '308 Win'],
                ['Alan Searle', '6.5 Creedmoor'],
                ['JD Els', '300 WSM'],
                ['Danie du Preez', '6.5 Creedmoor'],
                ['Wilfred Robson', '6.5CM'],
                ['Jaco Brummer', '6.5 Creedmoor'],
                ['Danè Uys', '7mm Dakota'],
                ['Gerhardu Odendaal', '300WSM'],
                ['Danie Koch', '7 PRCW'],
            ],
            4 => [
                ['Kobus Verwoerd', '6.5 prc'],
                ['Werner Marx', '243 win'],
                ['Harry Wassermann', '6.5 CM'],
                ['Christo Louw', '7mm Saum'],
                ['Simon Steyn', '7mm RSAUM'],
                ['Jose Alves', '260 Rem'],
                ['Mohamed Ayob', '308 Win'],
                ['Shaun Snyman', '300wsm'],
                ['Julius Hartmann', '308win'],
                ['Dirk Pio', '7PRCW'],
            ],
            5 => [
                ['John Pauls', '6.5 Creedmoor'],
                ['Steve Dyke', '6XC'],
                ['Juandre Stroebel', '6mm Creedmoor'],
                ['Gerrit van Rooyen', '300 Norma'],
                ['Willie van Aardt', '308 Win'],
                ['Morton Mynhardt', '6.5 PRC'],
                ['Konrad Grabe', '6 GT'],
                ['Schalk van der Merwe', '6.5 Creedmoor'],
                ['Kobie Nel', '300 Norma'],
                ['Steven Coombs', '7mm prc'],
            ],
            6 => [
                ['Xavier Badenhorst', '7mm prc'],
                ['Diedrik Pretorius', '300 PRC'],
                ['Ruan du Plessis', '6.5 PRC'],
                ['Abdul Aziz Amod 6.5 PRC', '6.5 PRC'],
                ['Francois Van Wyk', '300wm'],
                ['Francois van der Walt', '7mm Dakota'],
                ['Ismail Arbee', '6.5 Creedmoor'],
                ['Louis Raubenheimer', '6.5CM'],
                ['Wouter Louw', '7 RSaum'],
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
            $this->command?->info('Source: Shooters_5Sept2026.xlsx — Jose Alves cartridge restored from Excel 0.26 → 260 Rem.');
        });
    }
}
