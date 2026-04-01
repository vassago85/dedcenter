<?php

namespace App\Notifications;

use App\Models\ShootingMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ScoresPublishedNotification extends Notification
{
    use Queueable;

    public function __construct(public ShootingMatch $match) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Results Published',
            'body' => "Results are in for {$this->match->name}! See how you did.",
            'url' => "/scoreboard/{$this->match->id}",
            'match_id' => $this->match->id,
            'icon' => '/icons/icon-192.png',
            'tag' => "scores-{$this->match->id}",
        ];
    }
}
