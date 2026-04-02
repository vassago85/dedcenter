<?php

namespace App\Console\Commands;

use App\Enums\MatchStatus;
use App\Models\ShootingMatch;
use Illuminate\Console\Command;

class AutoCloseRegistration extends Command
{
    protected $signature = 'matches:auto-close-registration';

    protected $description = 'Close registration for matches past their registration deadline and remove incomplete pre-registrations';

    public function handle(): int
    {
        $now = now();
        $closed = 0;

        $matches = ShootingMatch::query()
            ->whereIn('status', [MatchStatus::PreRegistration, MatchStatus::RegistrationOpen])
            ->get();

        foreach ($matches as $match) {
            $deadline = $match->registration_closes_at ?? $match->defaultRegistrationCloseDate();

            if (! $deadline || $now->lt($deadline)) {
                continue;
            }

            $oldStatus = $match->status;
            $match->update(['status' => MatchStatus::RegistrationClosed]);

            $removed = $match->registrations()
                ->where('payment_status', 'pre_registered')
                ->delete();

            $closed++;
            $this->line("Closed registration: {$match->name} (ID {$match->id}, was {$oldStatus->value}, removed {$removed} pre-reg)");

            try {
                app(\App\Services\NotificationService::class)
                    ->onStatusChange($match, $oldStatus, MatchStatus::RegistrationClosed);
            } catch (\Throwable $e) {
                $this->warn("Notification failed for {$match->name}: {$e->getMessage()}");
            }
        }

        if ($closed === 0) {
            $this->info('No registrations needed auto-closing.');
        } else {
            $this->info("Auto-closed registration for {$closed} match(es).");
        }

        return self::SUCCESS;
    }
}
