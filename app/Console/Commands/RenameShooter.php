<?php

namespace App\Console\Commands;

use App\Models\Shooter;
use Illuminate\Console\Command;

/**
 * Rename a shooter (fix typos without touching any scores or squad links).
 *
 * Usage:
 *   php artisan shooter:rename --search="Koopper" --replace="Klopper"
 *   php artisan shooter:rename --id=576 --replace="Henri Klopper — 6x46"
 *   php artisan shooter:rename --search="Koopper" --replace="Klopper" --match=42
 *   php artisan shooter:rename --search="Koopper" --replace="Klopper" --dry-run
 */
class RenameShooter extends Command
{
    protected $signature = 'shooter:rename
        {--id= : Exact shooter id to rename (takes precedence over --search)}
        {--search= : Substring to find in shooter.name (case-insensitive)}
        {--replace= : Replacement string. If --id is used, replaces the full name; otherwise replaces the matched substring.}
        {--match= : Restrict --search to shooters in this match id}
        {--dry-run : Show what would change without saving}';

    protected $description = 'Rename a shooter by id, or by substring, preserving their squad and scores.';

    public function handle(): int
    {
        $id = $this->option('id');
        $search = $this->option('search');
        $replace = $this->option('replace');
        $matchId = $this->option('match');
        $dryRun = (bool) $this->option('dry-run');

        if ($replace === null) {
            $this->error('--replace is required.');
            return self::FAILURE;
        }

        if ($id === null && ($search === null || $search === '')) {
            $this->error('Provide either --id or --search.');
            return self::FAILURE;
        }

        if ($id !== null) {
            $shooter = Shooter::find((int) $id);
            if (! $shooter) {
                $this->error("Shooter #{$id} not found.");
                return self::FAILURE;
            }
            return $this->applyFullRename($shooter, (string) $replace, $dryRun);
        }

        $query = Shooter::query()->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower((string) $search).'%']);
        if ($matchId !== null) {
            $query->whereHas('squad', fn ($q) => $q->where('match_id', (int) $matchId));
        }
        $matches = $query->get();

        if ($matches->isEmpty()) {
            $this->warn("No shooters matched \"{$search}\".");
            return self::SUCCESS;
        }

        $changed = 0;
        foreach ($matches as $shooter) {
            $new = str_ireplace((string) $search, (string) $replace, $shooter->name);
            if ($new === $shooter->name) {
                $this->line("  (unchanged) #{$shooter->id}  {$shooter->name}");
                continue;
            }
            $this->line(sprintf(
                '  %s #%d  "%s"  ->  "%s"',
                $dryRun ? '[dry-run]' : 'rename',
                $shooter->id,
                $shooter->name,
                $new
            ));
            if (! $dryRun) {
                $shooter->name = $new;
                $shooter->save();
                $changed++;
            } else {
                $changed++;
            }
        }

        $this->info(sprintf(
            '%s complete. %d shooter(s) affected (of %d matched).',
            $dryRun ? 'Dry run' : 'Rename',
            $changed,
            $matches->count()
        ));

        return self::SUCCESS;
    }

    private function applyFullRename(Shooter $shooter, string $newName, bool $dryRun): int
    {
        if ($shooter->name === $newName) {
            $this->info("#{$shooter->id} already named \"{$newName}\".");
            return self::SUCCESS;
        }
        $this->line(sprintf(
            '  %s #%d  "%s"  ->  "%s"',
            $dryRun ? '[dry-run]' : 'rename',
            $shooter->id,
            $shooter->name,
            $newName
        ));
        if (! $dryRun) {
            $shooter->name = $newName;
            $shooter->save();
        }
        return self::SUCCESS;
    }
}
