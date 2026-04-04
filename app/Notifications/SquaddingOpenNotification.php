<?php

namespace App\Notifications;

use App\Models\ShootingMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SquaddingOpenNotification extends Notification
{
    use Queueable;

    public function __construct(public ShootingMatch $match) {}

    public function via($notifiable): array
    {
        $channels = ['database'];
        if ($notifiable->wantsEmailNotification('squadding_open')) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Choose Your Squad',
            'body' => "Squadding is now open for {$this->match->name}. Pick your squad!",
            'url' => "/events/{$this->match->id}",
            'match_id' => $this->match->id,
            'icon' => '/icons/icon-192.png',
            'tag' => "squad-open-{$this->match->id}",
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Squadding Open — {$this->match->name}")
            ->greeting("Hey {$notifiable->name}!")
            ->line("Squadding is now open for **{$this->match->name}**.")
            ->line('Choose your squad before the slots fill up.')
            ->action('Pick Your Squad', url("/events/{$this->match->id}"))
            ->salutation('— DeadCenter');
    }
}
