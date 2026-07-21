<?php

namespace App\Console\Commands;

use App\Enums\AlrhaClass;
use App\Enums\MatchStatus;
use App\Models\ElrScoringProfile;
use App\Models\MatchCategory;
use App\Models\MatchRegistration;
use App\Models\Organization;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeder for a disposable ALRHA test match (default Wed 22 Jul 2026).
 *
 * Purpose: exercise every ALRHA feature end-to-end on the tablet & web —
 * shot-index scoring, CBC-exclusion from match totals, categories, coached-
 * shooter prize exclusion, and both an adjacent-relay shared-rifle conflict
 * (must warn on the squadding page) and a non-adjacent one (must NOT warn).
 *
 * Usage on the server:
 *   php artisan match:seed-alrha-test --list                 # find org
 *   php artisan match:seed-alrha-test --org=SLUG --dry-run   # preview
 *   php artisan match:seed-alrha-test --org=SLUG             # seed for real
 *   php artisan match:seed-alrha-test --org=SLUG --class=varmint
 */
class SeedAlrhaTestMatch extends Command
{
    protected $signature = 'match:seed-alrha-test
        {--org= : Organization slug or id that owns the match}
        {--md= : Match director email or user id (defaults to the org owner)}
        {--date=2026-07-22 : Match date (Y-m-d) — defaults to Wed 22 Jul 2026}
        {--class=hunters : ALRHA class — "hunters" (teams of 2) or "varmint" (individual)}
        {--name= : Override the match name}
        {--list : List organizations (with owners) and exit}
        {--dry-run : Run inside a rolled-back transaction and print the summary only}';

    protected $description = 'Seed a disposable ALRHA test match (Hunters or Varmint) with 3 relays and realistic squad, rifle-sharing, coached, and category data.';

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listOrgs();
        }

        $org = $this->resolveOrg();
        if (! $org) {
            return self::FAILURE;
        }

        $actor = $this->resolveActor($org);
        if (! $actor) {
            $this->error('Could not resolve a match director. Pass --md=email (or --md=id).');

            return self::FAILURE;
        }

        $class = AlrhaClass::tryFrom((string) $this->option('class')) ?? AlrhaClass::Hunters;
        $date = Carbon::parse((string) $this->option('date'));
        $dryRun = (bool) $this->option('dry-run');

        $this->line('Org:     ['.$org->id.'] '.$org->name.'  ('.$org->slug.')');
        $this->line('MD:      ['.$actor->id.'] '.$actor->name.'  <'.$actor->email.'>');
        $this->line('Class:   '.$class->label().'  ('.$class->value.')');
        $this->line('Date:    '.$date->toDateString());
        $this->line('Mode:    '.($dryRun ? 'DRY RUN (rolled back)' : 'LIVE'));
        $this->newLine();

        $run = function () use ($org, $actor, $class, $date) {
            $name = (string) ($this->option('name')
                ?: 'TEST — ALRHA '.$class->label().' (safe to delete) — '.$date->format('j M Y'));

            $match = $this->createAlrhaMatch($org, $actor, $class, $date, $name);
            $this->applyAlrhaTemplate($match, $class);
            $seeded = $this->seedRelays($match, $class);

            $this->info('ALRHA test match  [#'.$match->id.'] '.$match->name);
            $this->line('  class: '.$class->label().' · relays: '.$seeded['relays'].' · shooters: '.$seeded['shooters'].' new / '.$seeded['skipped'].' already present');
            if ($seeded['coached'] > 0) {
                $this->line('  coached shooters: '.$seeded['coached'].' (excluded from prize tables)');
            }
            if ($seeded['conflicts']) {
                $this->line('  shared-rifle groups: '.implode(', ', $seeded['conflicts']));
                $this->line('  ↑ the "Adjacent" group should trigger the squadding page warning banner');
            }
            $this->line('  scoreboard: '.url('/scoreboard/'.$match->id));
            $this->line('  match edit: '.url('/org/'.$org->slug.'/matches/'.$match->id.'/edit'));
            $this->line('  squadding:  '.url('/org/'.$org->slug.'/matches/'.$match->id.'/squadding'));
        };

        if ($dryRun) {
            try {
                DB::transaction(function () use ($run) {
                    $run();
                    throw new \RuntimeException('__DRY_RUN_ROLLBACK__');
                });
            } catch (\RuntimeException $e) {
                if ($e->getMessage() !== '__DRY_RUN_ROLLBACK__') {
                    throw $e;
                }
                $this->newLine();
                $this->comment('Dry run complete — nothing was persisted.');
            }
        } else {
            DB::transaction($run);
            $this->newLine();
            $this->info('Done. Match is in SquaddingOpen with scores hidden until you publish.');
        }

        return self::SUCCESS;
    }

    private function listOrgs(): int
    {
        $orgs = Organization::orderBy('id')->get();
        if ($orgs->isEmpty()) {
            $this->warn('No organizations found.');

            return self::SUCCESS;
        }
        $this->line('id | slug | name | owner');
        foreach ($orgs as $o) {
            $owner = $o->admins()->wherePivot('is_owner', true)->first()
                ?? ($o->created_by ? User::find($o->created_by) : null);
            $this->line($o->id.' | '.$o->slug.' | '.$o->name.' | '.($owner ? $owner->name.' <'.$owner->email.'>' : '—'));
        }

        return self::SUCCESS;
    }

    private function resolveOrg(): ?Organization
    {
        $ref = (string) $this->option('org');
        if ($ref === '') {
            $this->error('--org is required (slug or id). Run `php artisan match:seed-alrha-test --list` to find it.');

            return null;
        }

        $org = Organization::where('slug', $ref)->first()
            ?? (ctype_digit($ref) ? Organization::find((int) $ref) : null);

        if (! $org) {
            $this->error("Organization not found for '{$ref}'. Run with --list to see options.");

            return null;
        }

        return $org;
    }

    private function resolveActor(Organization $org): ?User
    {
        $ref = (string) $this->option('md');
        if ($ref !== '') {
            return User::where('email', $ref)->first()
                ?? (ctype_digit($ref) ? User::find((int) $ref) : null);
        }

        return $org->admins()->wherePivot('is_owner', true)->first()
            ?? ($org->created_by ? User::find($org->created_by) : null)
            ?? $org->admins()->first();
    }

    /** Idempotently create (or reuse) an ALRHA match. */
    private function createAlrhaMatch(Organization $org, User $actor, AlrhaClass $class, Carbon $date, string $name): ShootingMatch
    {
        $match = ShootingMatch::where('organization_id', $org->id)
            ->where('name', $name)
            ->whereDate('date', $date->toDateString())
            ->first();

        if (! $match) {
            $match = ShootingMatch::create([
                'name' => $name,
                'date' => $date->toDateString(),
                'location' => $org->default_location ?? null,
                'status' => MatchStatus::SquaddingOpen,
                'scoring_type' => 'alrha',
                'alrha_class' => $class->value,
                'side_bet_enabled' => false,
                'royal_flush_enabled' => false,
                'elr_distance_based_scoring' => false,
                'elr_engagement_mode' => 'target_by_target',
                'scores_published' => false,
                'concurrent_relays' => 3,
                'max_squad_size' => 12,
                'team_size' => $class->hasTeamScoring() ? 2 : 1,
                'created_by' => $actor->id,
                'organization_id' => $org->id,
            ]);
        } else {
            $match->update([
                'scoring_type' => 'alrha',
                'alrha_class' => $class->value,
                'team_size' => $class->hasTeamScoring() ? 2 : 1,
            ]);
        }

        return $match;
    }

    /**
     * Mirrors the `applyAlrhaTemplate()` action in the org match edit Volt
     * component (see resources/views/pages/org/matches/edit.blade.php).
     * Kept in sync with that method — if you change the class layout, do it
     * in both places (or extract to a shared service in a follow-up PR).
     */
    private function applyAlrhaTemplate(ShootingMatch $match, AlrhaClass $class): void
    {
        $profile = ElrScoringProfile::updateOrCreate(
            ['match_id' => $match->id, 'name' => 'ALRHA 5-4-3-2-1'],
            ['multipliers' => [5, 4, 3, 2, 1]]
        );
        $match->update(['elr_scoring_profile_id' => $profile->id]);

        $match->elrStages()->delete();

        $cbcStage = $match->elrStages()->create([
            'label' => 'Cold Bore Challenge',
            'stage_type' => 'static',
            'elr_scoring_profile_id' => $profile->id,
            'sort_order' => 1,
        ]);
        $cbcStage->targets()->create([
            'name' => $class->coldBoreTargetName(),
            'distance_m' => $class->coldBoreDistance(),
            'base_points' => 1,
            'max_shots' => 1,
            'must_hit_to_advance' => false,
            'sort_order' => 1,
            'is_cold_bore' => true,
            'alrha_block' => 'cbc',
        ]);

        $farStage = $match->elrStages()->create([
            'label' => 'Far block',
            'stage_type' => 'static',
            'elr_scoring_profile_id' => $profile->id,
            'sort_order' => 2,
        ]);
        foreach ($class->farBlockDistances() as $i => $distance) {
            $farStage->targets()->create([
                'name' => "{$distance} m",
                'distance_m' => $distance,
                'base_points' => 1,
                'max_shots' => 5,
                'must_hit_to_advance' => false,
                'sort_order' => $i + 1,
                'alrha_block' => 'far',
            ]);
        }

        $nearStage = $match->elrStages()->create([
            'label' => 'Near block',
            'stage_type' => 'static',
            'elr_scoring_profile_id' => $profile->id,
            'sort_order' => 3,
        ]);
        foreach ($class->nearBlockDistances() as $i => $distance) {
            $nearStage->targets()->create([
                'name' => "{$distance} m",
                'distance_m' => $distance,
                'base_points' => 1,
                'max_shots' => 5,
                'must_hit_to_advance' => false,
                'sort_order' => $i + 1,
                'alrha_block' => 'near',
            ]);
        }

        foreach ($class->categorySlugs() as $sort => $slug) {
            MatchCategory::updateOrCreate(
                ['match_id' => $match->id, 'slug' => $slug],
                ['name' => ucfirst($slug), 'sort_order' => $sort],
            );
        }
    }

    /**
     * Seed 3 relays (matching the Parys 6 Jun 2026 squad-sheet layout).
     *
     * Layouts baked in:
     *  - Hunters:  3 relays × 2 teams × 2 shooters = 12 shooters, 6 teams,
     *              each team assigned a gong position (1..6, teams in
     *              Relay 1 use 1–2, Relay 2 uses 3–4, Relay 3 uses 5–6).
     *  - Varmint:  3 relays × 4 shooters (one peer group of 3 + one spare
     *              to test rounding) = 12 shooters, each with a unique
     *              gong position within their relay.
     *
     * Also plants:
     *  - one coached shooter (excluded from prize tables per §4 of the rules)
     *  - one shared-rifle pair in adjacent relays (should warn)
     *  - one shared-rifle pair in relays 1 & 3 (non-adjacent — should NOT warn)
     *
     * @return array{relays:int, shooters:int, skipped:int, coached:int, conflicts:list<string>}
     */
    private function seedRelays(ShootingMatch $match, AlrhaClass $class): array
    {
        $rows = $class === AlrhaClass::Hunters
            ? $this->hunterRoster()
            : $this->varmintRoster();

        $added = 0;
        $skipped = 0;
        $coached = 0;
        $relaysTouched = [];
        $conflicts = [];

        $existingNames = $match->shooters()->pluck('shooters.name')
            ->map(fn ($n) => mb_strtolower(trim($n)))->all();
        $existingNames = array_flip($existingNames);

        // Pre-create team records for Hunters so `team_id` can be looked up
        // by name below. Varmint rows have no team key so this stays empty.
        $teamsByName = [];
        if ($class->hasTeamScoring()) {
            $teamNames = collect($rows)
                ->pluck('team')
                ->filter()
                ->unique()
                ->values();
            foreach ($teamNames as $sort => $teamName) {
                $team = Team::firstOrCreate(
                    ['match_id' => $match->id, 'name' => $teamName],
                    ['max_size' => 2, 'sort_order' => $sort + 1],
                );
                $teamsByName[$teamName] = $team->id;
            }
        }

        foreach ($rows as $i => $row) {
            $name = trim($row['name']);
            if ($name === '') {
                continue;
            }
            $relayNum = (int) $row['relay'];
            $squadName = 'Relay '.$relayNum;

            $squad = $match->squads()->firstOrCreate(
                ['name' => $squadName],
                ['sort_order' => $relayNum, 'max_capacity' => 12]
            );
            $relaysTouched[$squad->id] = true;

            if (isset($existingNames[mb_strtolower($name)])) {
                $skipped++;

                continue;
            }

            $user = $this->placeholderUser($match, $name, $i);

            $reg = MatchRegistration::where('match_id', $match->id)
                ->where('user_id', $user->id)->first();
            $regData = [
                'payment_status' => 'confirmed',
                'amount' => 0,
                'is_free_entry' => true,
                'caliber' => isset($row['cartridge']) && $row['cartridge'] !== ''
                    ? Str::limit($row['cartridge'], 60, '')
                    : null,
                'admin_notes' => 'Seeded ALRHA test match ('.$class->value.').',
            ];
            if ($reg) {
                $reg->update($regData);
            } else {
                MatchRegistration::create([
                    'match_id' => $match->id,
                    'user_id' => $user->id,
                    'payment_reference' => MatchRegistration::generatePaymentReference($user),
                    ...$regData,
                ]);
            }

            $maxSort = Shooter::where('squad_id', $squad->id)->max('sort_order') ?? 0;
            $shooter = Shooter::create([
                'squad_id' => $squad->id,
                'name' => $name,
                'user_id' => $user->id,
                'sort_order' => $maxSort + 1,
                'status' => 'active',
                'team_id' => isset($row['team']) ? ($teamsByName[$row['team']] ?? null) : null,
                'gong_position' => $row['gong_position'] ?? null,
                'is_coached' => (bool) ($row['coached'] ?? false),
                'shared_rifle_key' => $row['rifle_key'] ?? null,
            ]);
            if ($shooter->is_coached) {
                $coached++;
            }

            // Attach categories so the prize-table filter has something to
            // filter on. Every shooter gets 'open'; ladies/junior are picked
            // per-row for whichever roster row asks for them.
            $categorySlugs = $row['categories'] ?? ['open'];
            $categoryIds = MatchCategory::where('match_id', $match->id)
                ->whereIn('slug', $categorySlugs)
                ->pluck('id')
                ->all();
            if ($categoryIds) {
                $shooter->categories()->sync($categoryIds);
            }

            $existingNames[mb_strtolower($name)] = true;
            $added++;
        }

        // Report which shared-rifle pairs we planted for the MD to sanity-
        // check the squadding page's warning banner.
        $byKey = collect($rows)
            ->filter(fn ($r) => ! empty($r['rifle_key']))
            ->groupBy('rifle_key');
        foreach ($byKey as $key => $group) {
            $relays = $group->pluck('relay')->unique()->sort()->values()->all();
            if (count($relays) < 2) {
                continue;
            }
            $adjacent = false;
            for ($j = 0; $j < count($relays) - 1; $j++) {
                if ((int) $relays[$j + 1] - (int) $relays[$j] === 1) {
                    $adjacent = true;
                    break;
                }
            }
            $conflicts[] = ($adjacent ? 'Adjacent(⚠)' : 'Non-adjacent(ok)')
                .' key='.$key.' relays='.implode('+', $relays);
        }

        return [
            'relays' => count($relaysTouched),
            'shooters' => $added,
            'skipped' => $skipped,
            'coached' => $coached,
            'conflicts' => $conflicts,
        ];
    }

    private function placeholderUser(ShootingMatch $match, string $name, int $index): User
    {
        $hash = substr(hash('sha256', $match->id.'|'.$name.'|'.$index), 0, 20);
        $email = sprintf('alrha.m%d.n%s%s', $match->id, $hash, User::IMPORT_PLACEHOLDER_EMAIL_SUFFIX);

        $user = User::where('email', $email)->first();
        if ($user) {
            return $user;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(Str::random(40)),
            'role' => 'shooter',
            'accepted_terms_at' => now(),
        ]);
        $user->forceFill(['email_verified_at' => null])->save();

        return $user;
    }

    /**
     * Hunters test roster: 3 relays × 2 teams × 2 shooters = 12 shooters,
     * 6 teams. Gong positions 1–2 in Relay 1, 3–4 in Relay 2, 5–6 in Relay 3
     * (matching the printed Parys sheet).
     *
     * Planted issues to exercise the engine:
     *  - "Test Junior H1" is a junior (extra category)
     *  - "Test Coach H1" is coached (excluded from prizes)
     *  - Rifle key "R1↔R2" links a shooter in Relay 1 to one in Relay 2
     *    (ADJACENT → shared-rifle warning MUST fire)
     *  - Rifle key "R1↔R3" links a shooter in Relay 1 to one in Relay 3
     *    (NON-adjacent → warning MUST NOT fire)
     *
     * @return list<array{relay:int, name:string, team:string, gong_position:int, cartridge?:string, coached?:bool, rifle_key?:string, categories?:list<string>}>
     */
    private function hunterRoster(): array
    {
        return [
            ['relay' => 1, 'name' => 'Test Hunter A1', 'team' => 'Team Alpha', 'gong_position' => 1, 'cartridge' => '7 PRC', 'rifle_key' => 'R1↔R2'],
            ['relay' => 1, 'name' => 'Test Hunter A2', 'team' => 'Team Alpha', 'gong_position' => 1, 'cartridge' => '7 PRC'],
            ['relay' => 1, 'name' => 'Test Hunter B1', 'team' => 'Team Bravo', 'gong_position' => 2, 'cartridge' => '6.5 Creedmoor', 'rifle_key' => 'R1↔R3'],
            ['relay' => 1, 'name' => 'Test Junior H1', 'team' => 'Team Bravo', 'gong_position' => 2, 'cartridge' => '6.5 Creedmoor', 'categories' => ['open', 'junior']],

            ['relay' => 2, 'name' => 'Test Hunter C1', 'team' => 'Team Charlie', 'gong_position' => 3, 'cartridge' => '7 RSAUM', 'rifle_key' => 'R1↔R2'],
            ['relay' => 2, 'name' => 'Test Hunter C2', 'team' => 'Team Charlie', 'gong_position' => 3, 'cartridge' => '7 RSAUM'],
            ['relay' => 2, 'name' => 'Test Hunter D1', 'team' => 'Team Delta', 'gong_position' => 4, 'cartridge' => '300 WSM'],
            ['relay' => 2, 'name' => 'Test Coach H1', 'team' => 'Team Delta', 'gong_position' => 4, 'cartridge' => '300 WSM', 'coached' => true],

            ['relay' => 3, 'name' => 'Test Hunter E1', 'team' => 'Team Echo', 'gong_position' => 5, 'cartridge' => '6.5 PRC'],
            ['relay' => 3, 'name' => 'Test Hunter E2', 'team' => 'Team Echo', 'gong_position' => 5, 'cartridge' => '6.5 PRC', 'rifle_key' => 'R1↔R3'],
            ['relay' => 3, 'name' => 'Test Hunter F1', 'team' => 'Team Foxtrot', 'gong_position' => 6, 'cartridge' => '284 Win'],
            ['relay' => 3, 'name' => 'Test Hunter F2', 'team' => 'Team Foxtrot', 'gong_position' => 6, 'cartridge' => '284 Win'],
        ];
    }

    /**
     * Varmint test roster: 3 relays × 4 shooters = 12 individual shooters,
     * unique gong positions per relay. Includes one lady, one junior, one
     * coached shooter, and the same two shared-rifle patterns as Hunters.
     *
     * @return list<array{relay:int, name:string, gong_position:int, cartridge?:string, coached?:bool, rifle_key?:string, categories?:list<string>}>
     */
    private function varmintRoster(): array
    {
        return [
            ['relay' => 1, 'name' => 'Test Varmint A1', 'gong_position' => 1, 'cartridge' => '6 Dasher', 'rifle_key' => 'R1↔R2'],
            ['relay' => 1, 'name' => 'Test Varmint A2', 'gong_position' => 2, 'cartridge' => '6 GT'],
            ['relay' => 1, 'name' => 'Test Lady V1', 'gong_position' => 3, 'cartridge' => '6.5 Creedmoor', 'categories' => ['open', 'ladies'], 'rifle_key' => 'R1↔R3'],
            ['relay' => 1, 'name' => 'Test Varmint A4', 'gong_position' => 4, 'cartridge' => '6 XC'],

            ['relay' => 2, 'name' => 'Test Varmint B1', 'gong_position' => 1, 'cartridge' => '6 Dasher', 'rifle_key' => 'R1↔R2'],
            ['relay' => 2, 'name' => 'Test Junior V1', 'gong_position' => 2, 'cartridge' => '6.5 Creedmoor', 'categories' => ['open', 'junior']],
            ['relay' => 2, 'name' => 'Test Coach V1', 'gong_position' => 3, 'cartridge' => '6 XC', 'coached' => true],
            ['relay' => 2, 'name' => 'Test Varmint B4', 'gong_position' => 4, 'cartridge' => '6 GT'],

            ['relay' => 3, 'name' => 'Test Varmint C1', 'gong_position' => 1, 'cartridge' => '6 SLR'],
            ['relay' => 3, 'name' => 'Test Varmint C2', 'gong_position' => 2, 'cartridge' => '6 GT'],
            ['relay' => 3, 'name' => 'Test Varmint C3', 'gong_position' => 3, 'cartridge' => '6.5 Creedmoor', 'rifle_key' => 'R1↔R3'],
            ['relay' => 3, 'name' => 'Test Varmint C4', 'gong_position' => 4, 'cartridge' => '6 Dasher'],
        ];
    }
}
