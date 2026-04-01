<?php

namespace App\Notifications;

use App\Models\ShootingMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RegistrationOpenNotification extends Notification
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
            'title' => 'Registration Open',
            'body' => "Registration is now open for {$this->match->name}.",
            'url' => "/matches/{$this->match->id}",
            'match_id' => $this->match->id,
            'icon' => '/icons/icon-192.png',
            'tag' => "reg-open-{$this->match->id}",
        ];
    }
}
