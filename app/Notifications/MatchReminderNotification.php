<?php

namespace App\Notifications;

use App\Models\ShootingMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MatchReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public ShootingMatch $match) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $venue = $this->match->location ? " at {$this->match->location}" : '';
        return [
            'title' => 'Match Tomorrow',
            'body' => "{$this->match->name} is tomorrow{$venue}. Good luck!",
            'url' => "/matches/{$this->match->id}",
            'match_id' => $this->match->id,
            'icon' => '/icons/icon-192.png',
            'tag' => "reminder-{$this->match->id}",
        ];
    }
}
