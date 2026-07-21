<?php

namespace App\Console\Commands;

use App\Enums\AlrhaClass;
use App\Enums\MatchStatus;
use App\Models\MatchCategory;
use App\Models\MatchRegistration;
use App\Models\Organization;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Team;
use App\Models\User;
use App\Services\Scoring\AlrhaMatchBuilder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeder for a disposable dual-class ALRHA test match (default
 * Wed 22 Jul 2026). One match, both classes running concurrently on
 * shared relays — mirrors how a real ALRHA event plays out.
 *
 * Exercises every ALRHA feature end-to-end on tablet + web:
 *  - Dual class stage trees (Hunters + Varmint) on one match
 *  - Shot-index scoring (5-4-3-2-1) via the ELR pipeline
 *  - CBC exclusion from class totals + separate CBC prize table
 *  - Categories (Open / Ladies / Junior)
 *  - Coached-shooter prize exclusion
 *  - Adjacent-relay shared-rifle conflict (MUST warn on squadding page)
 *  - Non-adjacent shared-rifle pairing (MUST NOT warn)
 *
 * Usage on the server:
 *   php artisan match:seed-alrha-test --list                  # find org
 *   php artisan match:seed-alrha-test --org=SLUG --dry-run    # preview
 *   php artisan match:seed-alrha-test --org=SLUG              # seed for real
 *   php artisan match:seed-alrha-test --org=SLUG --classes=hunters,varmint
 */
class SeedAlrhaTestMatch extends Command
{
    protected $signature = 'match:seed-alrha-test
        {--org= : Organization slug or id that owns the match}
        {--md= : Match director email or user id (defaults to the org owner)}
        {--date=2026-07-22 : Match date (Y-m-d) — defaults to Wed 22 Jul 2026}
        {--classes=hunters,varmint : Comma-separated ALRHA classes to seed (default both)}
        {--name= : Override the match name}
        {--list : List organizations (with owners) and exit}
        {--dry-run : Run inside a rolled-back transaction and print the summary only}';

    protected $description = 'Seed a disposable dual-class ALRHA test match (Hunters + Varmint on shared relays) with rifle-sharing, coached, and category data.';

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

        $classes = $this->resolveClasses();
        if (empty($classes)) {
            $this->error('At least one class must be picked. Try --classes=hunters,varmint.');

            return self::FAILURE;
        }

        $date = Carbon::parse((string) $this->option('date'));
        $dryRun = (bool) $this->option('dry-run');
        $classLabels = collect($classes)->map(fn (AlrhaClass $c) => $c->label())->implode(' + ');

        $this->line('Org:     ['.$org->id.'] '.$org->name.'  ('.$org->slug.')');
        $this->line('MD:      ['.$actor->id.'] '.$actor->name.'  <'.$actor->email.'>');
        $this->line('Classes: '.$classLabels);
        $this->line('Date:    '.$date->toDateString());
        $this->line('Mode:    '.($dryRun ? 'DRY RUN (rolled back)' : 'LIVE'));
        $this->newLine();

        $run = function () use ($org, $actor, $classes, $date) {
            $suffix = count($classes) === 1
                ? $classes[0]->label()
                : 'Dual-class';
            $name = (string) ($this->option('name')
                ?: 'TEST — ALRHA '.$suffix.' (safe to delete) — '.$date->format('j M Y'));

            $match = $this->createAlrhaMatch($org, $actor, $classes, $date, $name);
            app(AlrhaMatchBuilder::class)->apply($match, $classes);
            $seeded = $this->seedRelays($match, $classes);

            $this->info('ALRHA test match  [#'.$match->id.'] '.$match->name);
            $this->line('  classes: '.collect($classes)->map(fn ($c) => $c->label())->implode(' + '));
            $this->line('  relays: '.$seeded['relays'].' · shooters: '.$seeded['shooters'].' new / '.$seeded['skipped'].' already present');
            foreach ($seeded['per_class'] as $cls => $stats) {
                $this->line('  · '.$cls.': '.$stats['added'].' new shooters');
            }
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

    /**
     * @return array<int, AlrhaClass>
     */
    private function resolveClasses(): array
    {
        $raw = trim((string) $this->option('classes'));
        if ($raw === '') {
            return [AlrhaClass::Hunters, AlrhaClass::Varmint];
        }

        return collect(explode(',', $raw))
            ->map(fn ($v) => trim($v))
            ->filter()
            ->map(fn ($v) => AlrhaClass::tryFrom($v))
            ->filter()
            ->values()
            ->all();
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

    /**
     * Idempotently create (or reuse) an ALRHA match. For dual-class
     * matches we leave `alrha_class` NULL — the class list is derived
     * from stage tags. Single-class seeds still populate it for
     * back-compat with older UIs.
     *
     * @param  array<int, AlrhaClass>  $classes
     */
    private function createAlrhaMatch(Organization $org, User $actor, array $classes, Carbon $date, string $name): ShootingMatch
    {
        $legacyClass = count($classes) === 1 ? $classes[0]->value : null;
        $teamSize = collect($classes)->contains(fn (AlrhaClass $c) => $c->hasTeamScoring()) ? 2 : 1;

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
                'alrha_class' => $legacyClass,
                'side_bet_enabled' => false,
                'royal_flush_enabled' => false,
                'elr_distance_based_scoring' => false,
                'elr_engagement_mode' => 'target_by_target',
                'scores_published' => false,
                'concurrent_relays' => 3,
                'max_squad_size' => 12,
                'team_size' => $teamSize,
                'created_by' => $actor->id,
                'organization_id' => $org->id,
            ]);
        } else {
            $match->update([
                'scoring_type' => 'alrha',
                'alrha_class' => $legacyClass,
                'team_size' => $teamSize,
            ]);
        }

        return $match;
    }

    /**
     * Seed up to 3 shared relays with rows from both class rosters.
     * Every shooter carries `alrha_class` so the scoring app + scoreboard
     * partition them into per-class prize tables.
     *
     * Planted anomalies (shared across both rosters where possible):
     *  - one coached shooter per class (excluded from prize tables per §4)
     *  - one adjacent-relay shared-rifle pair (rifle_key "R1↔R2")
     *  - one non-adjacent shared-rifle pair (rifle_key "R1↔R3")
     *
     * @param  array<int, AlrhaClass>  $classes
     * @return array{relays:int, shooters:int, skipped:int, coached:int, conflicts:list<string>, per_class:array<string, array{added:int}>}
     */
    private function seedRelays(ShootingMatch $match, array $classes): array
    {
        $existingNames = $match->shooters()->pluck('shooters.name')
            ->map(fn ($n) => mb_strtolower(trim($n)))->all();
        $existingNames = array_flip($existingNames);

        $added = 0;
        $skipped = 0;
        $coached = 0;
        $relaysTouched = [];
        $perClass = [];
        $allRows = [];

        // Hunter rows carry `team` names; we upsert Team rows before
        // creating shooters so `team_id` can be looked up by team name.
        $teamsByName = [];
        if (in_array(AlrhaClass::Hunters, $classes, true)) {
            $hunterRows = $this->hunterRoster();
            $teamNames = collect($hunterRows)->pluck('team')->filter()->unique()->values();
            foreach ($teamNames as $sort => $teamName) {
                $team = Team::firstOrCreate(
                    ['match_id' => $match->id, 'name' => $teamName],
                    ['max_size' => 2, 'sort_order' => $sort + 1],
                );
                $teamsByName[$teamName] = $team->id;
            }
            foreach ($hunterRows as $row) {
                $row['_class'] = AlrhaClass::Hunters;
                $allRows[] = $row;
            }
        }

        if (in_array(AlrhaClass::Varmint, $classes, true)) {
            foreach ($this->varmintRoster() as $row) {
                $row['_class'] = AlrhaClass::Varmint;
                $allRows[] = $row;
            }
        }

        foreach ($allRows as $i => $row) {
            $name = trim($row['name']);
            if ($name === '') {
                continue;
            }
            /** @var AlrhaClass $rowClass */
            $rowClass = $row['_class'];
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
                'admin_notes' => 'Seeded ALRHA test match (dual-class).',
                'alrha_class' => $rowClass->value,
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
                'alrha_class' => $rowClass->value,
            ]);
            if ($shooter->is_coached) {
                $coached++;
            }

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
            $perClass[$rowClass->value] = ($perClass[$rowClass->value] ?? ['added' => 0]);
            $perClass[$rowClass->value]['added']++;
        }

        // Shared-rifle conflict summary (checks across classes too — the
        // ALRHA validator is class-agnostic; two shooters sharing a rifle
        // in adjacent relays must warn regardless of which class they
        // shoot).
        $conflicts = [];
        $byKey = collect($allRows)
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
            'per_class' => $perClass,
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
            ['relay' => 1, 'name' => 'Test Varmint A1', 'gong_position' => 1, 'cartridge' => '6 Dasher'],
            ['relay' => 1, 'name' => 'Test Varmint A2', 'gong_position' => 2, 'cartridge' => '6 GT'],
            ['relay' => 1, 'name' => 'Test Lady V1', 'gong_position' => 3, 'cartridge' => '6.5 Creedmoor', 'categories' => ['open', 'ladies']],
            ['relay' => 1, 'name' => 'Test Varmint A4', 'gong_position' => 4, 'cartridge' => '6 XC'],

            ['relay' => 2, 'name' => 'Test Varmint B1', 'gong_position' => 1, 'cartridge' => '6 Dasher'],
            ['relay' => 2, 'name' => 'Test Junior V1', 'gong_position' => 2, 'cartridge' => '6.5 Creedmoor', 'categories' => ['open', 'junior']],
            ['relay' => 2, 'name' => 'Test Coach V1', 'gong_position' => 3, 'cartridge' => '6 XC', 'coached' => true],
            ['relay' => 2, 'name' => 'Test Varmint B4', 'gong_position' => 4, 'cartridge' => '6 GT'],

            ['relay' => 3, 'name' => 'Test Varmint C1', 'gong_position' => 1, 'cartridge' => '6 SLR'],
            ['relay' => 3, 'name' => 'Test Varmint C2', 'gong_position' => 2, 'cartridge' => '6 GT'],
            ['relay' => 3, 'name' => 'Test Varmint C3', 'gong_position' => 3, 'cartridge' => '6.5 Creedmoor'],
            ['relay' => 3, 'name' => 'Test Varmint C4', 'gong_position' => 4, 'cartridge' => '6 Dasher'],
        ];
    }
}
