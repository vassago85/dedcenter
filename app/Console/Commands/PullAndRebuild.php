<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Local dev helper: pull latest master and rebuild frontend + backend caches.
 *
 * For PRODUCTION server deploys, use ./deploy.sh on the Ubuntu host instead —
 * it rebuilds the Docker image with --no-cache so Vite runs fresh in the
 * Dockerfile and the entrypoint picks it up.
 *
 *   php artisan app:pull-rebuild
 *   php artisan app:pull-rebuild --branch=master
 *   php artisan app:pull-rebuild --no-assets      # skip `npm run build`
 *   php artisan app:pull-rebuild --no-pull        # skip git pull
 *   php artisan app:pull-rebuild --dry-run
 */
class PullAndRebuild extends Command
{
    protected $signature = 'app:pull-rebuild
        {--branch=master : Branch to pull from origin}
        {--no-pull : Skip git pull}
        {--no-assets : Skip npm run build}
        {--no-cache-clear : Skip clearing Laravel caches}
        {--dry-run : Print commands only}';

    protected $description = 'Pull latest from origin, rebuild frontend assets, and clear Laravel caches. Local dev helper — use ./deploy.sh on the server.';

    public function handle(): int
    {
        $branch = (string) $this->option('branch');
        $dry = (bool) $this->option('dry-run');

        if (! $this->option('no-pull')) {
            $this->section('Pulling origin/'.$branch);
            $this->runCmd(['git', 'fetch', '--prune', 'origin'], $dry);
            $this->runCmd(['git', 'checkout', $branch], $dry);
            $this->runCmd(['git', 'pull', '--ff-only', 'origin', $branch], $dry);
        } else {
            $this->line('Skipping git pull.');
        }

        if (! $this->option('no-cache-clear')) {
            $this->section('Clearing caches');
            $this->runArtisan('config:clear', $dry);
            $this->runArtisan('route:clear', $dry);
            $this->runArtisan('view:clear', $dry);
            $this->runArtisan('event:clear', $dry);
        }

        if (! $this->option('no-assets')) {
            $this->section('Building frontend assets');
            $this->runCmd(['npm', 'run', 'build'], $dry, timeout: 600);
        } else {
            $this->line('Skipping npm run build.');
        }

        $this->info('Pull + rebuild complete.');

        return self::SUCCESS;
    }

    private function section(string $label): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('<comment>'.$label.'</>', '');
    }

    /**
     * @param  array<int, string>  $cmd
     */
    private function runCmd(array $cmd, bool $dryRun, int $timeout = 300): void
    {
        $pretty = implode(' ', array_map(fn ($s) => preg_match('/\s/', $s) ? "\"{$s}\"" : $s, $cmd));
        $this->line("+ {$pretty}");
        if ($dryRun) {
            return;
        }

        $process = new Process($cmd, base_path(), null, null, $timeout);
        $process->run(function ($type, $buffer) {
            $this->getOutput()->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->error("Command failed (exit {$process->getExitCode()}): {$pretty}");
            exit($process->getExitCode() ?: 1);
        }
    }

    private function runArtisan(string $cmd, bool $dryRun): void
    {
        $this->line("+ artisan {$cmd}");
        if ($dryRun) {
            return;
        }
        $this->call($cmd);
    }
}
