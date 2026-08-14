<?php

namespace App\Console\Commands;

use App\Enums\AlrhaClass;
use App\Enums\MatchStatus;
use App\Enums\Province;
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
 * Seeds the 15 August 2026 ALRHA match at Dwandzani Shooting Range from
 * the 2nd-draft squading sheet. Dual-class (LR Varmint + LR Hunters) on
 * four shared relays. Tiaan Alberts is the match director.
 *
 * Usage:
 *   php artisan match:seed-alrha-dwandzani --list
 *   php artisan match:seed-alrha-dwandzani --dry-run
 *   php artisan match:seed-alrha-dwandzani
 *   php artisan match:seed-alrha-dwandzani --org=alrha --md=tiaan.alberts@deadcenter.co.za
 *   php artisan match:seed-alrha-dwandzani --fresh
 */
class SeedAlrhaDwandzaniMatch extends Command
{
    public const DEFAULT_ORG_SLUG = 'alrha';

    public const DEFAULT_MD_EMAIL = 'tiaan.alberts@deadcenter.co.za';

    public const DEFAULT_MD_NAME = 'Tiaan Alberts';

    public const DEFAULT_MATCH_NAME = 'ALRHA — Dwandzani (15 August 2026)';

    public const DEFAULT_DATE = '2026-08-15';

    public const LOCATION = 'Dwandzani Shooting Range';

    /** @var array<int, string> */
    private const RELAY_TIMES = [
        1 => '09:00–10:10',
        2 => '09:40–10:50',
        3 => '10:20–11:30',
        4 => '11:00–12:10',
    ];

    protected $signature = 'match:seed-alrha-dwandzani
        {--org= : Organization slug or id (defaults to creating/using alrha)}
        {--md= : Match director email or user id (defaults to Tiaan Alberts)}
        {--date=2026-08-15 : Match date (Y-m-d)}
        {--name= : Override the match name}
        {--fresh : Wipe this match\'s roster and re-import the draft sheet}
        {--list : List organizations (with owners) and exit}
        {--dry-run : Run inside a rolled-back transaction and print the summary only}';

    protected $description = 'Seed the 15 Aug 2026 ALRHA Dwandzani match (Varmint + Hunters) with Tiaan Alberts as match director.';

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listOrgs();
        }

        try {
            $org = $this->resolveOrg();
            $actor = $this->resolveActor($org);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $date = Carbon::parse((string) $this->option('date'));
        $dryRun = (bool) $this->option('dry-run');
        $fresh = (bool) $this->option('fresh');
        $name = (string) ($this->option('name') ?: self::DEFAULT_MATCH_NAME);

        $this->line('Org:     ['.$org->id.'] '.$org->name.'  ('.$org->slug.')');
        $this->line('MD:      ['.$actor->id.'] '.$actor->name.'  <'.$actor->email.'>');
        $this->line('Date:    '.$date->toDateString());
        $this->line('Match:   '.$name);
        $this->line('Mode:    '.($dryRun ? 'DRY RUN (rolled back)' : 'LIVE').($fresh ? ' + FRESH roster' : ''));
        $this->newLine();

        $run = function () use ($org, $actor, $date, $name, $fresh) {
            $this->ensureOrgAdmin($org, $actor);

            $match = $this->createMatch($org, $actor, $date, $name);
            app(AlrhaMatchBuilder::class)->apply($match, [AlrhaClass::Hunters, AlrhaClass::Varmint]);

            if ($fresh) {
                $this->wipeRoster($match);
            }

            $seeded = $this->seedRoster($match);

            $this->info('ALRHA Dwandzani  [#'.$match->id.'] '.$match->name);
            $this->line('  location: '.self::LOCATION);
            $this->line('  status:   '.$match->status->value);
            $this->line('  relays:   '.$seeded['relays'].' · shooters: '.$seeded['shooters'].' new / '.$seeded['updated'].' updated / '.$seeded['skipped'].' skipped');
            foreach ($seeded['per_class'] as $cls => $stats) {
                $this->line('  · '.$cls.': '.$stats['added'].' new, '.$stats['updated'].' updated');
            }
            if ($seeded['dual_class'] !== []) {
                $this->warn('  listed in both classes (as on the draft sheet): '.implode(', ', $seeded['dual_class']));
            }
            $this->line('  scoreboard: '.url('/scoreboard/'.$match->id));
            $this->line('  match hub:  '.url('/org/'.$org->slug.'/matches/'.$match->id));
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
            $this->info('Done. Match is in Squadding Open with scores hidden until you publish.');
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

    private function resolveOrg(): Organization
    {
        $ref = trim((string) $this->option('org'));
        if ($ref !== '') {
            $org = Organization::where('slug', $ref)->first()
                ?? (ctype_digit($ref) ? Organization::find((int) $ref) : null);
            if (! $org) {
                throw new \RuntimeException("Organization not found for '{$ref}'. Run with --list, or omit --org to create ALRHA.");
            }

            return $org;
        }

        $actorHint = $this->peekActor();

        return Organization::firstOrCreate(
            ['slug' => self::DEFAULT_ORG_SLUG],
            [
                'name' => 'ALRHA',
                'description' => 'African Long Range Hunting Association — LR Hunters and LR Varmint.',
                'type' => 'competition',
                'status' => 'active',
                'created_by' => $actorHint?->id,
                'primary_color' => '#0369a1',
                'secondary_color' => '#0f172a',
                'hero_text' => 'ALRHA',
                'hero_description' => 'Long-range hunters and varmint — one day, two classes, shared relays.',
                'portal_enabled' => true,
                'portal_entitled' => true,
                'province' => Province::Limpopo->value,
            ]
        );
    }

    private function peekActor(): ?User
    {
        $ref = trim((string) $this->option('md'));
        if ($ref !== '') {
            return User::where('email', $ref)->first()
                ?? (ctype_digit($ref) ? User::find((int) $ref) : null);
        }

        return User::where('email', self::DEFAULT_MD_EMAIL)->first()
            ?? User::where('name', self::DEFAULT_MD_NAME)->first();
    }

    private function resolveActor(Organization $org): User
    {
        $ref = trim((string) $this->option('md'));
        if ($ref !== '') {
            $user = User::where('email', $ref)->first()
                ?? (ctype_digit($ref) ? User::find((int) $ref) : null);
            if (! $user) {
                throw new \RuntimeException("Match director not found for '{$ref}'.");
            }

            return $user;
        }

        $existing = User::where('email', self::DEFAULT_MD_EMAIL)->first()
            ?? User::where('name', self::DEFAULT_MD_NAME)->first();
        if ($existing) {
            return $existing;
        }

        $user = User::create([
            'name' => self::DEFAULT_MD_NAME,
            'email' => self::DEFAULT_MD_EMAIL,
            'password' => Hash::make('password'),
            'role' => 'shooter',
            'accepted_terms_at' => now(),
            'onboarded_at' => now(),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->comment('Created Tiaan Alberts <'.self::DEFAULT_MD_EMAIL.'> (password: password).');

        if (! $org->created_by) {
            $org->update(['created_by' => $user->id]);
        }

        return $user;
    }

    private function ensureOrgAdmin(Organization $org, User $actor): void
    {
        $org->admins()->syncWithoutDetaching([
            $actor->id => [
                'is_owner' => true,
                'is_match_director' => true,
                'is_range_officer' => true,
                'is_shooter' => true,
            ],
        ]);
    }

    private function createMatch(Organization $org, User $actor, Carbon $date, string $name): ShootingMatch
    {
        $match = ShootingMatch::where('organization_id', $org->id)
            ->where('name', $name)
            ->whereDate('date', $date->toDateString())
            ->first();

        $payload = [
            'date' => $date->toDateString(),
            'location' => self::LOCATION,
            'province' => Province::Limpopo,
            'status' => MatchStatus::SquaddingOpen,
            'scoring_type' => 'alrha',
            'alrha_class' => null,
            'side_bet_enabled' => false,
            'royal_flush_enabled' => false,
            'elr_distance_based_scoring' => false,
            'elr_engagement_mode' => 'target_by_target',
            'scores_published' => false,
            'concurrent_relays' => 2,
            'max_squad_size' => 30,
            'team_event' => true,
            'team_size' => 2,
            'self_squadding_enabled' => false,
            'created_by' => $actor->id,
            'organization_id' => $org->id,
            'notes' => 'Seeded from Squading Dwandzani 15 August 2026 2nd draft. Relays: 1 '.$this->relayTime(1).', 2 '.$this->relayTime(2).', 3 '.$this->relayTime(3).', 4 '.$this->relayTime(4).'. Dual-class (LR Varmint + LR Hunters) on shared relays.',
            'public_bio' => 'ALRHA dual-class match at Dwandzani Shooting Range. LR Varmint (individual) and LR Hunters (two-person teams) run concurrently on four staggered relays.',
        ];

        if (! $match) {
            $match = ShootingMatch::create(['name' => $name, ...$payload]);
        } else {
            $match->update($payload);
        }

        $match->staff()->syncWithoutDetaching([
            $actor->id => ['role' => 'match_director'],
        ]);

        return $match;
    }

    private function wipeRoster(ShootingMatch $match): void
    {
        foreach ($match->squads()->get() as $squad) {
            $squad->shooters()->delete();
        }
        MatchRegistration::where('match_id', $match->id)->delete();
        $match->teams()->delete();
    }

    /**
     * @return array{
     *     relays:int,
     *     shooters:int,
     *     updated:int,
     *     skipped:int,
     *     dual_class:list<string>,
     *     per_class:array<string, array{added:int, updated:int}>
     * }
     */
    private function seedRoster(ShootingMatch $match): array
    {
        $roster = require database_path('data/alrha/dwandzani-2026-08-15.php');

        $added = 0;
        $updated = 0;
        $skipped = 0;
        $relaysTouched = [];
        $perClass = [
            AlrhaClass::Varmint->value => ['added' => 0, 'updated' => 0],
            AlrhaClass::Hunters->value => ['added' => 0, 'updated' => 0],
        ];
        $namesByClass = [AlrhaClass::Varmint->value => [], AlrhaClass::Hunters->value => []];

        $teamsByName = [];
        $hunterIndexInTeam = [];
        foreach ($roster['hunters'] as $row) {
            $teamName = $row['team'];
            if (! isset($teamsByName[$teamName])) {
                $team = Team::firstOrCreate(
                    ['match_id' => $match->id, 'name' => $teamName],
                    ['max_size' => 2, 'sort_order' => (int) $row['team_no']],
                );
                $teamsByName[$teamName] = $team->id;
            }
        }

        $rows = [];
        foreach ($roster['varmint'] as $row) {
            $row['_class'] = AlrhaClass::Varmint;
            $row['bib'] = 'V-'.str_pad((string) $row['pos'], 2, '0', STR_PAD_LEFT);
            $rows[] = $row;
        }
        foreach ($roster['hunters'] as $row) {
            $row['_class'] = AlrhaClass::Hunters;
            $teamNo = (int) $row['team_no'];
            $hunterIndexInTeam[$teamNo] = ($hunterIndexInTeam[$teamNo] ?? 0) + 1;
            $suffix = $hunterIndexInTeam[$teamNo] === 1 ? 'A' : 'B';
            $row['bib'] = 'H-'.str_pad((string) $teamNo, 2, '0', STR_PAD_LEFT).$suffix;
            $rows[] = $row;
        }

        foreach ($rows as $row) {
            $name = trim((string) $row['name']);
            if ($name === '') {
                $skipped++;

                continue;
            }

            /** @var AlrhaClass $rowClass */
            $rowClass = $row['_class'];
            $relayNum = (int) $row['relay'];
            $squad = $match->squads()->firstOrCreate(
                ['name' => 'Relay '.$relayNum],
                ['sort_order' => $relayNum, 'max_capacity' => 30]
            );
            $relaysTouched[$squad->id] = true;

            $user = $this->placeholderUser($match, $name, $row['bib']);
            $reg = MatchRegistration::where('match_id', $match->id)
                ->where('user_id', $user->id)
                ->first();
            $regData = [
                'payment_status' => 'confirmed',
                'amount' => 0,
                'is_free_entry' => true,
                'admin_notes' => 'Seeded from Dwandzani 15 Aug 2026 2nd draft squading.',
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

            $shooter = Shooter::where('squad_id', $squad->id)
                ->where('bib_number', $row['bib'])
                ->first();

            $sortOrder = $rowClass === AlrhaClass::Hunters
                ? (((int) $row['team_no'] * 2) - (str_ends_with($row['bib'], 'A') ? 1 : 0))
                : (int) $row['pos'];

            $shooterData = [
                'squad_id' => $squad->id,
                'name' => $name,
                'user_id' => $user->id,
                'bib_number' => $row['bib'],
                'sort_order' => $sortOrder,
                'status' => 'active',
                'team_id' => isset($row['team']) ? ($teamsByName[$row['team']] ?? null) : null,
                'gong_position' => (int) $row['gong'],
                'alrha_class' => $rowClass->value,
            ];

            if ($shooter) {
                $shooter->update($shooterData);
                $updated++;
                $perClass[$rowClass->value]['updated']++;
            } else {
                $shooter = Shooter::create($shooterData);
                $added++;
                $perClass[$rowClass->value]['added']++;
            }

            $categorySlugs = $row['categories'] ?? ['open'];
            $categoryIds = MatchCategory::where('match_id', $match->id)
                ->whereIn('slug', $categorySlugs)
                ->pluck('id')
                ->all();
            if ($categoryIds) {
                $shooter->categories()->sync($categoryIds);
            }

            $namesByClass[$rowClass->value][] = mb_strtolower($name);
        }

        $dual = array_values(array_unique(array_intersect(
            $namesByClass[AlrhaClass::Varmint->value],
            $namesByClass[AlrhaClass::Hunters->value],
        )));
        $dual = array_map(fn (string $n) => Str::title($n), $dual);

        return [
            'relays' => count($relaysTouched),
            'shooters' => $added,
            'updated' => $updated,
            'skipped' => $skipped,
            'dual_class' => $dual,
            'per_class' => $perClass,
        ];
    }

    private function placeholderUser(ShootingMatch $match, string $name, string $bib): User
    {
        $hash = substr(hash('sha256', $match->id.'|'.$bib.'|'.$name), 0, 20);
        $email = sprintf('alrha.m%d.%s.%s%s', $match->id, Str::slug($bib), $hash, User::IMPORT_PLACEHOLDER_EMAIL_SUFFIX);

        $user = User::where('email', $email)->first();
        if ($user) {
            if ($user->name !== $name) {
                $user->update(['name' => $name]);
            }

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

    private function relayTime(int $relay): string
    {
        return self::RELAY_TIMES[$relay] ?? '';
    }
}
