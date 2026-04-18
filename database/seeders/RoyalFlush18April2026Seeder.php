<?php

namespace Database\Seeders;

use App\Enums\MatchStatus;
use App\Models\MatchRegistration;
use App\Models\Gong;
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
        // Diagnostic: report DB connection + counts so we can tell if we're looking
        // in the wrong database (or if tables are empty on a fresh environment).
        $conn = DB::connection();
        $dbName = $conn->getDatabaseName();
        $orgCount = Organization::count();
        $userCount = User::count();
        $this->command?->info("DB connection: {$conn->getName()} / database={$dbName}");
        $this->command?->info("Organizations total: {$orgCount}, Users total: {$userCount}");

        if ($orgCount > 0) {
            $orgList = Organization::select('id', 'slug', 'name')->orderBy('id')->limit(20)->get();
            foreach ($orgList as $o) {
                $this->command?->info("  org[{$o->id}] slug={$o->slug} | name={$o->name}");
            }
        }

        // Prefer exact slug; fall back to any slug starting with "royal-flush"
        // (e.g. "royal-flush-1" on servers where the slug was auto-incremented),
        // and finally match by name.
        $org = Organization::where('slug', 'royal-flush')->first()
            ?? Organization::where('slug', 'like', 'royal-flush%')->orderBy('id')->first()
            ?? Organization::where('name', 'Royal Flush')->first();

        if (! $org) {
            // Auto-create the org if it doesn't exist yet (fresh environments).
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
                ['Paul Charsley', '6.5 Creedmoor'],
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
                ['Henri Klopper', '6x46'],
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
            // Include soft-deleted ("archived") rows so a previously archived
            // Royal Flush match gets restored instead of creating a duplicate.
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
            // scoring_type must be one of the edit-form whitelist (standard|prs|elr).
            // The Royal Flush feature is driven by the royal_flush_enabled flag above,
            // not by scoring_type. Using an unknown value leaves the Stages tab empty.
            $match->scoring_type = in_array($match->scoring_type, ['standard', 'prs', 'elr'], true)
                ? $match->scoring_type
                : 'standard';
            $match->self_squadding_enabled = false;
            $match->created_by = $match->created_by ?? User::query()->value('id');
            $match->save();

            $this->command?->info("Match [{$match->id}] {$match->name} ready.");

            // ── Target sets / gongs ───────────────────────────────────────────
            // Royal Flush is always 400/500/600/700 m, 5 gongs each with the
            // standard RF multiplier table:
            //
            //   DISTANCE → distance_multiplier = distance / 100 (4, 5, 6, 7)
            //   GONG     → 1:1.00, 2:1.30, 3:1.50, 4:1.80, 5:2.00 (G1 biggest, G5 smallest)
            //
            // Score per hit = distance_multiplier × gong.multiplier.
            // Idempotent: fills in missing rows, overwrites stored multipliers
            // on existing gong rows to match the canonical table.
            $rfDistances = [400, 500, 600, 700];
            $gongMultipliers = ['1.00', '1.30', '1.50', '1.80', '2.00'];
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

                // Ensure 5 gongs exist (numbered 1..5) with the RF multiplier
                // pattern. If fewer exist, create the missing ones; then snap
                // multipliers on all 5 to the canonical values.
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
            $this->command?->info('Target sets ensured: 400/500/600/700 m × 5 gongs with RF multipliers (1/1.3/1.5/1.8/2).');

            // Full wipe of any existing shooters on this match (any squad, including
            // stale duplicates manually created on prior runs). Otherwise idempotent
            // re-runs leave orphaned rows if a shooter was added in an earlier seed
            // and then removed from this roster.
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
