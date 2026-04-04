<?php

namespace App\Services;

use App\Enums\MatchStatus;
use App\Jobs\SendPostMatchNotifications;
use App\Models\ShootingMatch;
use App\Notifications\RegistrationOpenNotification;
use App\Notifications\SquaddingOpenNotification;
use App\Notifications\MatchUpdateNotification;
use App\Services\PushNotificationService;

class NotificationService
{
    public function onStatusChange(ShootingMatch $match, MatchStatus $oldStatus, MatchStatus $newStatus): void
    {
        match ($newStatus) {
            MatchStatus::RegistrationOpen => $this->notifyRegistrationOpen($match),
            MatchStatus::SquaddingOpen => $this->notifySquaddingOpen($match),
            MatchStatus::Completed => $this->schedulePostMatchNotifications($match),
            default => null,
        };
    }

    protected function notifyRegistrationOpen(ShootingMatch $match): void
    {
        $preRegistered = $match->registrations()
            ->whereNotNull('pre_registered_at')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        foreach ($preRegistered as $user) {
            if ($user->wantsNotification('registration_open')) {
                $notification = new RegistrationOpenNotification($match);
                $user->notify($notification);
                $data = $notification->toArray($user);
                PushNotificationService::send($user, $data['title'], $data['body'], $data['url']);
            }
        }
    }

    protected function notifySquaddingOpen(ShootingMatch $match): void
    {
        $registered = $match->registrations()
            ->where('payment_status', 'confirmed')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        foreach ($registered as $user) {
            if ($user->wantsNotification('squadding_open')) {
                $notification = new SquaddingOpenNotification($match);
                $user->notify($notification);
                $data = $notification->toArray($user);
                PushNotificationService::send($user, $data['title'], $data['body'], $data['url']);
            }
        }
    }

    /**
     * Delay results notifications and match reports by 1 hour
     * to give the MD time for prize giving and final admin.
     */
    protected function schedulePostMatchNotifications(ShootingMatch $match): void
    {
        if (!$match->scores_published) return;

        SendPostMatchNotifications::dispatch($match)->delay(now()->addHour());
    }

    public function notifyMatchUpdate(ShootingMatch $match, string $change): void
    {
        $shooters = $match->shooters()
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id');

        foreach ($shooters as $user) {
            if ($user->wantsNotification('match_updates')) {
                $notification = new MatchUpdateNotification($match, $change);
                $user->notify($notification);
                $data = $notification->toArray($user);
                PushNotificationService::send($user, $data['title'], $data['body'], $data['url']);
            }
        }
    }
}
