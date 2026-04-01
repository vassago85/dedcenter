<?php

namespace App\Notifications;

use App\Models\ShootingMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MatchUpdateNotification extends Notification
{
    use Queueable;

    public function __construct(public ShootingMatch $match, public string $changeDescription = 'Match details have been updated.') {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Match Updated',
            'body' => "{$this->match->name}: {$this->changeDescription}",
            'url' => "/matches/{$this->match->id}",
            'match_id' => $this->match->id,
            'icon' => '/icons/icon-192.png',
            'tag' => "update-{$this->match->id}",
        ];
    }
}
