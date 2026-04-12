<?php

namespace App\Console\Commands;

use App\Enums\MatchStatus;
use App\Models\ShootingMatch;
use App\Services\AchievementService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
            $oldStatus = $match->status;
            $match->update(['status' => MatchStatus::Completed]);
            $closed++;
            $this->line("Completed: {$match->name} (ID {$match->id}, was {$oldStatus->value})");

            try {
                app(NotificationService::class)->onStatusChange($match, $oldStatus, MatchStatus::Completed);
            } catch (\Throwable $e) {
                Log::warning('Auto-close notification failed', ['match_id' => $match->id, 'error' => $e->getMessage()]);
            }

            try {
                if ($match->isPrs()) {
                    AchievementService::evaluateMatchCompletion($match);
                }
                if ($match->royal_flush_enabled) {
                    AchievementService::evaluateRoyalFlushCompletion($match);
                }
            } catch (\Throwable $e) {
                Log::warning('Auto-close achievement evaluation failed', ['match_id' => $match->id, 'error' => $e->getMessage()]);
            }
        }

        if ($closed === 0) {
            $this->info('No matches needed auto-closing.');
        } else {
            $this->info("Auto-completed {$closed} match(es) whose event date has passed.");
        }

        return self::SUCCESS;
    }
}
