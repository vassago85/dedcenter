<?php

namespace App\Services;

use App\Enums\MatchStatus;
use App\Models\ShootingMatch;
use App\Notifications\RegistrationOpenNotification;
use App\Notifications\ScoresPublishedNotification;
use App\Notifications\SquaddingOpenNotification;
use App\Notifications\MatchUpdateNotification;

class NotificationService
{
    public function onStatusChange(ShootingMatch $match, MatchStatus $oldStatus, MatchStatus $newStatus): void
    {
        match ($newStatus) {
            MatchStatus::RegistrationOpen => $this->notifyRegistrationOpen($match),
            MatchStatus::SquaddingOpen => $this->notifySquaddingOpen($match),
            MatchStatus::Completed => $this->notifyScoresPublished($match),
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
                $user->notify(new RegistrationOpenNotification($match));
            }
        }
    }

    protected function notifySquaddingOpen(ShootingMatch $match): void
    {
        $registered = $match->registrations()
            ->whereNotNull('approved_at')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        foreach ($registered as $user) {
            if ($user->wantsNotification('squadding_open')) {
                $user->notify(new SquaddingOpenNotification($match));
            }
        }
    }

    protected function notifyScoresPublished(ShootingMatch $match): void
    {
        if (!$match->scores_published) return;

        $shooters = $match->shooters()
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id');

        foreach ($shooters as $user) {
            if ($user->wantsNotification('scores_published')) {
                $user->notify(new ScoresPublishedNotification($match));
            }
        }
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
                $user->notify(new MatchUpdateNotification($match, $change));
            }
        }
    }
}
