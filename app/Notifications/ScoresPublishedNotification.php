<?php

namespace App\Notifications;

use App\Models\ShootingMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScoresPublishedNotification extends Notification
{
    use Queueable;

    public function __construct(public ShootingMatch $match) {}

    public function via($notifiable): array
    {
        $channels = ['database'];
        if ($notifiable->wantsEmailNotification('scores_published')) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Results Published',
            'body' => "Results are in for {$this->match->name}! See how you did and download your PDF report.",
            'url' => "/events/{$this->match->id}",
            'match_id' => $this->match->id,
            'icon' => '/icons/icon-192.png',
            'tag' => "scores-{$this->match->id}",
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $downloadUrl = url("/matches/{$this->match->id}/report/download");

        return (new MailMessage)
            ->subject("Results Published — {$this->match->name}")
            ->greeting("Hey {$notifiable->name}!")
            ->line("Results are in for **{$this->match->name}**!")
            ->line('See how you did, check out the badges awarded, and download your personal PDF match report.')
            ->action('View Results', url("/events/{$this->match->id}"))
            ->line("[Download your PDF report]({$downloadUrl})")
            ->salutation('— DeadCenter');
    }
}
