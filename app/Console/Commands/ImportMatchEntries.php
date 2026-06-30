<?php

namespace App\Console\Commands;

use App\Models\ShootingMatch;
use App\Services\MatchEntriesImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports a club entries CSV (e.g. PretoriaPRC entries export) into a match's
 * registrations + creates user accounts as needed.
 *
 * Usage:
 *   php artisan match:import-entries 42 --file=storage/app/entries.csv
 *   php artisan match:import-entries 42 --file=... --free   # zero entry fee
 *   php artisan match:import-entries 42 --file=... --dry-run
 */
class ImportMatchEntries extends Command
{
    protected $signature = 'match:import-entries
        {match : The match ID to import entries into}
        {--file= : Absolute or relative path to the CSV file}
        {--free : Mark all imported registrations as free entry (amount=0, is_free_entry=true)}
        {--dry-run : Run inside a rolled-back transaction; print the summary only}';

    protected $description = 'Import an entries CSV into a match as confirmed registrations + user accounts.';

    public function handle(MatchEntriesImportService $service): int
    {
        $matchId = (int) $this->argument('match');
        $match = ShootingMatch::find($matchId);
        if (! $match) {
            $this->error("Match #{$matchId} not found.");

            return self::FAILURE;
        }

        $file = (string) $this->option('file');
        if ($file === '') {
            $this->error('--file is required.');

            return self::FAILURE;
        }

        $resolved = $this->resolvePath($file);
        if ($resolved === null) {
            $this->error("File not readable: {$file}");

            return self::FAILURE;
        }

        $csv = file_get_contents($resolved);
        if ($csv === false) {
            $this->error("Failed to read {$resolved}");

            return self::FAILURE;
        }

        $freeEntry = (bool) $this->option('free');
        $dryRun = (bool) $this->option('dry-run');

        $this->line("Match:   [{$match->id}] {$match->name}".($match->date ? "  ({$match->date->format('Y-m-d')})" : ''));
        $this->line("File:    {$resolved}");
        $this->line('Mode:    '.($dryRun ? 'DRY RUN (rolled back)' : 'LIVE').($freeEntry ? '  ·  FREE ENTRY' : ''));
        $this->newLine();

        $result = null;

        if ($dryRun) {
            try {
                DB::transaction(function () use (&$result, $service, $match, $csv, $freeEntry) {
                    $result = $service->import($match, $csv, $freeEntry);
                    throw new \RuntimeException('__DRY_RUN_ROLLBACK__');
                });
            } catch (\RuntimeException $e) {
                if ($e->getMessage() !== '__DRY_RUN_ROLLBACK__') {
                    throw $e;
                }
            }
        } else {
            $result = $service->import($match, $csv, $freeEntry);
        }

        $this->printSummary($result, $dryRun);

        return empty($result['errors']) ? self::SUCCESS : self::FAILURE;
    }

    private function resolvePath(string $path): ?string
    {
        $candidates = [$path, base_path($path)];
        foreach ($candidates as $c) {
            if (is_readable($c) && is_file($c)) {
                return realpath($c) ?: $c;
            }
        }

        return null;
    }

    private function printSummary(array $result, bool $dryRun): void
    {
        $this->line('────────── Summary ──────────');
        $this->line('Users:           '.$result['created_users'].' new, '.$result['existing_users'].' existing');
        $this->line('Registrations:   '.$result['created_registrations'].' created, '.$result['updated_registrations'].' updated');
        $this->line('Divisions:       '.$result['created_divisions'].' created');
        $this->line('Categories:      '.$result['created_categories'].' created');
        $this->line('Skipped rows:    '.$result['skipped_rows']);

        if (! empty($result['warnings'])) {
            $this->newLine();
            $this->warn('Warnings ('.count($result['warnings']).')');
            foreach ($result['warnings'] as $w) {
                $this->line('  · '.$w);
            }
        }

        if (! empty($result['errors'])) {
            $this->newLine();
            $this->error('Errors ('.count($result['errors']).')');
            foreach ($result['errors'] as $e) {
                $this->line('  · '.$e);
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('Dry run complete — no rows were persisted.');
        } elseif (empty($result['errors'])) {
            $this->newLine();
            $this->info('Import complete.');
            $this->line('Next step: run `php artisan match:invite-entries '.((int) $this->argument('match')).' --only=new` to email new accounts a set-password link,');
            $this->line('  then `--only=all` (or just plain `match:invite-entries` after testing) to invite everyone to confirm + self-squad.');
        }
    }
}
