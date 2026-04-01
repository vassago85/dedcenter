<?php

namespace App\Notifications;

use App\Models\ShootingMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SquaddingOpenNotification extends Notification
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
            'title' => 'Choose Your Squad',
            'body' => "Squadding is now open for {$this->match->name}. Pick your squad!",
            'url' => "/matches/{$this->match->id}/squadding",
            'match_id' => $this->match->id,
            'icon' => '/icons/icon-192.png',
            'tag' => "squad-open-{$this->match->id}",
        ];
    }
}
