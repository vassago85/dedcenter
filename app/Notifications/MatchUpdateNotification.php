<?php

namespace App\Notifications;

use App\Models\ShootingMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MatchUpdateNotification extends Notification
{
    use Queueable;

    public function __construct(public ShootingMatch $match, public string $changeDescription = 'Match details have been updated.') {}

    public function via($notifiable): array
    {
        $channels = ['database'];
        if ($notifiable->wantsEmailNotification('match_updates')) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Match Updated',
            'body' => "{$this->match->name}: {$this->changeDescription}",
            'url' => "/events/{$this->match->id}",
            'match_id' => $this->match->id,
            'icon' => '/icons/icon-192.png',
            'tag' => "update-{$this->match->id}",
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Match Updated — {$this->match->name}")
            ->greeting("Hey {$notifiable->name}!")
            ->line("There's an update for **{$this->match->name}**:")
            ->line($this->changeDescription)
            ->action('View Match', url("/events/{$this->match->id}"))
            ->salutation('— DeadCenter');
    }
}
