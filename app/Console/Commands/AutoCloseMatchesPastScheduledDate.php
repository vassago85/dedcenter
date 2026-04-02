<?php

namespace App\Console\Commands;

use App\Enums\MatchStatus;
use App\Models\ShootingMatch;
use Illuminate\Console\Command;

class AutoCloseMatchesPastScheduledDate extends Command
{
    protected $signature = 'matches:auto-close-past-date';

    protected $description = 'Mark Active or Squadding Open matches as Completed once their scheduled date is before today (app timezone)';

    public function handle(): int
    {
        $today = today();

        $matches = ShootingMatch::query()
            ->whereDate('date', '<', $today)
            ->whereIn('status', [MatchStatus::Active, MatchStatus::SquaddingOpen])
            ->get();

        $closed = 0;
        foreach ($matches as $match) {
            $was = $match->status->value;
            $match->update(['status' => MatchStatus::Completed]);
            $closed++;
            $this->line("Completed: {$match->name} (ID {$match->id}, was {$was})");
        }

        if ($closed === 0) {
            $this->info('No matches needed auto-closing.');
        } else {
            $this->info("Auto-completed {$closed} match(es) whose event date has passed.");
        }

        return self::SUCCESS;
    }
}
