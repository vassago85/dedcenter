<?php

namespace App\Console\Commands;

use App\Enums\MatchStatus;
use App\Models\ShootingMatch;
use App\Notifications\MatchReminderNotification;
use Illuminate\Console\Command;

class SendMatchReminders extends Command
{
    protected $signature = 'matches:send-reminders';
    protected $description = 'Send reminder notifications for matches happening tomorrow';

    public function handle(): void
    {
        $tomorrow = now()->addDay()->toDateString();

        $matches = ShootingMatch::where('status', MatchStatus::Active)
            ->whereDate('date', $tomorrow)
            ->get();

        foreach ($matches as $match) {
            $shooters = $match->shooters()
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter()
                ->unique('id');

            foreach ($shooters as $user) {
                if ($user->wantsNotification('match_reminders')) {
                    $user->notify(new MatchReminderNotification($match));
                }
            }

            $this->info("Sent reminders for {$match->name} ({$shooters->count()} shooters)");
        }
    }
}
