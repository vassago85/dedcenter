<?php

namespace App\Console\Commands;

use App\Enums\MatchStatus;
use App\Models\MatchRegistration;
use App\Models\Organization;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\User;
use App\Services\RoyalFlushMatchBuilder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * One-shot seeder for the 18 July 2026 Royal Flush shoot.
 *
 * The shooter squad sheet is embedded below (Name + Cartridge, grouped into
 * squads 1..10) so the data ships with the code and can be seeded straight on
 * the server — no file upload needed. Creates a Royal Flush match (side bet +
 * RF enabled) for the given org/MD, seeds every shooter as a claimable
 * placeholder entry in the right squad, and optionally spins up a small
 * throwaway test match with the same features.
 *
 * Usage on the server:
 *   php artisan match:seed-legends --list                 # find the org slug/id + owner
 *   php artisan match:seed-legends --org=SLUG --dry-run   # preview, rolls back
 *   php artisan match:seed-legends --org=SLUG --test      # seed for real + test match
 */
class SeedLegendsRoyalFlush extends Command
{
    protected $signature = 'match:seed-legends
        {--org= : Organization slug or id that owns the match}
        {--md= : Match director email or user id (defaults to the org owner)}
        {--date=2026-07-18 : Match date (Y-m-d)}
        {--name= : Override the main match name}
        {--test : Also create a small throwaway test match with the same features}
        {--only-test : Create ONLY the test match (skip the real one)}
        {--list : List organizations (with owners) and exit — use this to find --org}
        {--dry-run : Run inside a rolled-back transaction and print the summary only}';

    protected $description = 'Seed the 18 Jul 2026 Royal Flush match (squads + shooters) and an optional test match.';

    public function handle(RoyalFlushMatchBuilder $builder): int
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

        $date = Carbon::parse((string) $this->option('date'));
        $dryRun = (bool) $this->option('dry-run');
        $onlyTest = (bool) $this->option('only-test');
        $withTest = (bool) $this->option('test') || $onlyTest;

        $this->line('Org:     ['.$org->id.'] '.$org->name.'  ('.$org->slug.')');
        $this->line('MD:      ['.$actor->id.'] '.$actor->name.'  <'.$actor->email.'>');
        $this->line('Date:    '.$date->toDateString());
        $this->line('Mode:    '.($dryRun ? 'DRY RUN (rolled back)' : 'LIVE'));
        $this->newLine();

        $run = function () use ($builder, $org, $actor, $date, $onlyTest, $withTest) {
            if (! $onlyTest) {
                $name = (string) ($this->option('name') ?: 'Royal Flush — '.$date->format('j F Y'));
                $match = $this->createRfMatch($builder, $org, $actor, $date, $name);
                $seeded = $this->seedSquads($match, self::shooters());
                $this->info('Main match  [#'.$match->id.'] '.$match->name);
                $this->line('  squads: '.$seeded['squads'].'  ·  shooters: '.$seeded['shooters'].' new / '.$seeded['skipped'].' already present');
                $this->line('  scoreboard: '.url('/scoreboard/'.$match->id));
            }

            if ($withTest) {
                $testName = 'TEST — Royal Flush (safe to delete) — '.$date->format('j M Y');
                $test = $this->createRfMatch($builder, $org, $actor, $date, $testName);
                $seeded = $this->seedSquads($test, self::testShooters());
                $this->info('Test match  [#'.$test->id.'] '.$test->name);
                $this->line('  squads: '.$seeded['squads'].'  ·  shooters: '.$seeded['shooters'].' new / '.$seeded['skipped'].' already present');
                $this->line('  scoreboard: '.url('/scoreboard/'.$test->id));
            }
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
            $this->info('Done. Both matches are in SquaddingOpen with scores hidden until you publish.');
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
            $this->error('--org is required (slug or id). Run `php artisan match:seed-legends --list` to find it.');

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

    /** Idempotently create (or reuse) an RF match with side bet enabled. */
    private function createRfMatch(RoyalFlushMatchBuilder $builder, Organization $org, User $actor, Carbon $date, string $name): ShootingMatch
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
                'scoring_type' => 'standard',
                'side_bet_enabled' => true,
                'royal_flush_enabled' => true,
                'scores_published' => false,
                'concurrent_relays' => 2,
                'max_squad_size' => 10,
                'created_by' => $actor->id,
                'organization_id' => $org->id,
            ]);
        }

        // Safe to re-run — applies the canonical 400/500/600/700m × 5-gong preset.
        $builder->applyPresetTo($match);

        return $match;
    }

    /**
     * Seed a squad sheet into a match. Each shooter becomes a claimable
     * placeholder entry (user + confirmed free registration carrying the
     * cartridge) in a "Squad N" squad. Idempotent on shooter name per match.
     *
     * @param array<int, array{squad:string, name:string, cartridge:string}> $rows
     * @return array{squads:int, shooters:int, skipped:int}
     */
    private function seedSquads(ShootingMatch $match, array $rows): array
    {
        $squadsTouched = [];
        $added = 0;
        $skipped = 0;

        $existingNames = $match->shooters()->pluck('shooters.name')->map(fn ($n) => mb_strtolower(trim($n)))->all();
        $existingNames = array_flip($existingNames);

        foreach ($rows as $i => $row) {
            $name = trim($row['name']);
            if ($name === '') {
                continue;
            }
            $squadName = 'Squad '.$row['squad'];
            $sortOrder = (int) $row['squad'];

            $squad = $match->squads()->firstOrCreate(
                ['name' => $squadName],
                ['sort_order' => $sortOrder, 'max_capacity' => 10]
            );
            $squadsTouched[$squad->id] = true;

            if (isset($existingNames[mb_strtolower($name)])) {
                $skipped++;

                continue;
            }

            $user = $this->placeholderUser($match, $name, $i);

            $reg = MatchRegistration::where('match_id', $match->id)
                ->where('user_id', $user->id)
                ->first();
            $regData = [
                'payment_status' => 'confirmed',
                'amount' => 0,
                'is_free_entry' => true,
                'caliber' => $row['cartridge'] !== '' ? Str::limit($row['cartridge'], 60, '') : null,
                'admin_notes' => 'Seeded from 18 Jul 2026 squad sheet.',
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
            Shooter::create([
                'squad_id' => $squad->id,
                'name' => $name,
                'user_id' => $user->id,
                'sort_order' => $maxSort + 1,
                'status' => 'active',
            ]);
            $existingNames[mb_strtolower($name)] = true;
            $added++;
        }

        return ['squads' => count($squadsTouched), 'shooters' => $added, 'skipped' => $skipped];
    }

    private function placeholderUser(ShootingMatch $match, string $name, int $index): User
    {
        $hash = substr(hash('sha256', $match->id.'|'.$name.'|'.$index), 0, 20);
        $email = sprintf('rf.m%d.n%s%s', $match->id, $hash, User::IMPORT_PLACEHOLDER_EMAIL_SUFFIX);

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
     * The 18 Jul 2026 squad sheet (Name + Cartridge), grouped into squads 1..10.
     *
     * @return array<int, array{squad:string, name:string, cartridge:string}>
     */
    private static function shooters(): array
    {
        $raw = [
            ['1', 'Chris Badenhorst', '6 GT'],
            ['1', 'Franco Wiid', '7PRC'],
            ['1', 'Jaco van der Merwe', '260REM'],
            ['1', 'Andre Brummer', '6.5 Creedmoor'],
            ['1', 'Brendon Bieldt', '6.5 PRC'],
            ['1', 'Lee Thompson', '6.5 Creedmoor'],
            ['1', 'Danie du Preez', '6.5 Creedmoor'],
            ['1', 'Louis Raubenheimer', '6.5 Creedmoor'],
            ['1', 'Carl Louw', '7SAUM'],
            ['1', 'Andries de beer', '7 RSAUM'],
            ['2', 'Reynard van Deventer', '6.5 PRC'],
            ['2', 'Braam Bester', '6.5 Creedmoor'],
            ['2', 'Konrad Grabe', '6mm GT'],
            ['2', 'Steven Coombs', '7 PRC'],
            ['2', 'Vollas Volschenk', '6.5 Creedmoor'],
            ['2', 'Alex Pienaar', '6 Dasher'],
            ['2', 'Alan Searle', '6.5 Creedmoor'],
            ['2', 'Morne van der Merwe', '7mm prcw'],
            ['2', 'Richard Meissner', '7 PRC'],
            ['2', 'Christo Louw', '7 saum'],
            ['3', 'Abdul Aziz Amod 6.5CM', '6.5 CM'],
            ['3', 'Gerrie Lotter', '308 Win'],
            ['3', 'Werick venter', '7prc'],
            ['3', 'Karien Els', '300 WSM'],
            ['3', 'Henk Rykaart', '7PRC'],
            ['3', 'Brian Koen', '7 PRC'],
            ['3', 'Johan Nel', '25x47'],
            ['3', 'Phillip Oosthuizen', '300 Win Mag'],
            ['3', 'Jaco Brummer', '6.5 Creedmoor'],
            ['3', 'MORNE VAN WYK', '6x47 Lapua'],
            ['4', 'Rudi Barnard', '270 Win'],
            ['4', 'Drikus Moolman', '300 Win Mag'],
            ['4', 'Morton Mynhardt', '6.5 PRC'],
            ['4', 'Johann Yssel', '260 Rem'],
            ['4', 'Maurits Pretorius', '6.5x55 SM'],
            ['4', 'Erwin Potgieter', '7 RSAUM'],
            ['4', 'Zander Swart', '6.5 PRC'],
            ['4', 'Simon Steyn', '7 RSAUM'],
            ['4', 'Stephan van der Merwe', '6.5 Creedmoor'],
            ['4', 'Riaan van Bosch', '6.5 Creedmoor'],
            ['5', 'Ettienne Hennop', '6.5 Creedmoor'],
            ['5', 'Chris Snyman', '6.5 Creed'],
            ['5', 'Theo Botha', '7 RSAUM'],
            ['5', 'Ruan du Plessis', '6 Creedmoor'],
            ['5', 'Deon de Villiers', '6 Dasher'],
            ['5', 'Schalk van der Merwe', '6.5 Creedmoor'],
            ['5', 'Kyle Van Rooyen', '7 PRC'],
            ['5', 'Johannes Thomas', '284 Win'],
            ['5', 'Lizette Els', '6 GT'],
            ['5', 'Shaun Flink', '284 Shehane'],
            ['6', 'Wayne van Rooyen', '7 PRC'],
            ['6', 'Gerrit van Rooyen', '7 RSUAM'],
            ['6', 'Jannie Jacobs', '300 Blaser Mag'],
            ['6', 'QUEN VAN WYK', '6x47 Lapua'],
            ['6', 'Alan Hewetson', '30 SMF'],
            ['6', 'JC Robertson', '308win'],
            ['6', 'Martin Erasmus', '7 RSAUM'],
            ['6', 'Handre Truter', '6.5 Creedmoor'],
            ['6', 'Petrus Wassermann', '6.5x55 SM'],
            ['6', 'Brian Beeming', '7 PRC'],
            ['7', 'JD Els', '300 WSM'],
            ['7', 'Johan Smith', '308 Win'],
            ['7', 'Pieter Meyer', '260 Rem'],
            ['7', 'Ockie van Schalkwyk', '6,5 Creedmoor'],
            ['7', 'Francois Davel', '284 Win'],
            ['7', 'Kenny Smit', '6.5 Creedmoor'],
            ['7', 'Werner Deyzel', '6mm'],
            ['7', 'Warren Britnell', '6mm Dasher'],
            ['7', 'Diedrik Pretorius', '300 PRC'],
            ['7', 'Philip Venter', '300 WSM'],
            ['8', 'Pieter Grobler', '7mm RSAUM'],
            ['8', 'Juandre Stroebel', '6mm Creedmoor'],
            ['8', 'Kobus Verwoerd', '6.5 PRC'],
            ['8', 'Duan Viljoen', '6.5 Creedmoor'],
            ['8', 'Donovan Dauth', '6.5 Creedmoor'],
            ['8', 'Harry Wassermann', '6.5 Creedmoor'],
            ['8', 'Rudi Viljoen', '7mm prc'],
            ['8', 'Gerhardu Odendaal', '300 WSM'],
            ['8', 'Daniel Bonthuys', '7 mm RSAUM'],
            ['8', 'Lihan Bester', '6.5 Creedmoor'],
            ['9', 'Dewald Hurn', '7mm SAUM'],
            ['9', 'Danie Viljoen', '300 WSM'],
            ['9', 'Johan Steenkamp', '7mm RSAUM'],
            ['9', 'Karli van der Merwe', '260REM'],
            ['9', 'Liandi van der Merwe', '260REM'],
            ['9', 'Francois Van Wyk', '6xc'],
            ['9', 'Danie Koch', '7mm PRCW'],
            ['9', 'Abdul Aziz Amod', '6.5 PRC'],
            ['9', 'Gert Loots', '7 PRC'],
            ['9', 'Pieter Ferriera', '7mm RSAUM'],
            ['10', 'Reinier Kuschke', '308 Win'],
            ['10', 'Werner Bonthuys', '7 RSAUM'],
            ['10', 'De Waal Uys', '6.5 creed'],
            ['10', 'Cameron De Wet', '308 Win'],
            ['10', 'Emil Engelbrecht', '7mm RSAUM'],
            ['10', 'Mike Nel', '6.5 Creedmoor'],
            ['10', 'Wikus Viviers', '6XC'],
            ['10', 'Siebert Noeth', '6 SLR'],
            ['10', 'Fred vd Westhuizen', '6 Dasher'],
            ['10', 'Kean Botha', '6 XC'],
        ];

        return array_map(fn ($r) => ['squad' => $r[0], 'name' => $r[1], 'cartridge' => $r[2]], $raw);
    }

    /**
     * A tiny disposable squad sheet for the test match.
     *
     * @return array<int, array{squad:string, name:string, cartridge:string}>
     */
    private static function testShooters(): array
    {
        $raw = [
            ['1', 'Test Shooter A1', '6.5 Creedmoor'],
            ['1', 'Test Shooter A2', '6 Dasher'],
            ['1', 'Test Shooter A3', '7 PRC'],
            ['2', 'Test Shooter B1', '308 Win'],
            ['2', 'Test Shooter B2', '6.5 PRC'],
            ['2', 'Test Shooter B3', '300 WSM'],
        ];

        return array_map(fn ($r) => ['squad' => $r[0], 'name' => $r[1], 'cartridge' => $r[2]], $raw);
    }
}
