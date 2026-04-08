<?php

namespace App\Console\Commands;

use App\Models\ShootingMatch;
use App\Services\RoyalFlushEquipmentImportService;
use Illuminate\Console\Command;

class ImportMatchEquipmentSheet extends Command
{
    protected $signature = 'match:import-equipment
        {match : Match ID}
        {--file= : Path to a UTF-8 text/TSV file}
        {--no-shooters : Do not add shooters to Default squad}
        {--paid : Use match entry fee instead of free entry}';

    protected $description = 'Import Royal Flush style tab-separated equipment registrations into a match';

    public function handle(RoyalFlushEquipmentImportService $importService): int
    {
        $matchId = (int) $this->argument('match');
        $path = $this->option('file');
        if (! $path || ! is_readable($path)) {
            $this->error('Provide a readable --file= path to TSV/UTF-8 text.');

            return self::FAILURE;
        }

        $match = ShootingMatch::find($matchId);
        if (! $match) {
            $this->error("Match {$matchId} not found.");

            return self::FAILURE;
        }

        $tsv = file_get_contents($path);
        if ($tsv === false || trim($tsv) === '') {
            $this->error('File is empty or unreadable.');

            return self::FAILURE;
        }

        $freeEntry = ! (bool) $this->option('paid');
        $addShooters = ! (bool) $this->option('no-shooters');

        $r = $importService->import($match, $tsv, $freeEntry, $addShooters);

        $this->info('Users created: '.$r['created_users']);
        $this->info('Registrations created: '.$r['created_registrations']);
        $this->info('Registrations updated: '.$r['updated_registrations']);
        $this->info('Shooters added: '.$r['shooters_added']);
        $this->info('Rows skipped: '.$r['skipped_rows']);
        foreach ($r['warnings'] as $w) {
            $this->warn($w);
        }

        return self::SUCCESS;
    }
}
