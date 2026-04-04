<?php

namespace App\Notifications;

use App\Models\ShootingMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationOpenNotification extends Notification
{
    use Queueable;

    public function __construct(public ShootingMatch $match) {}

    public function via($notifiable): array
    {
        $channels = ['database'];
        if ($notifiable->wantsEmailNotification('registration_open')) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Registration Open',
            'body' => "Registration is now open for {$this->match->name}.",
            'url' => "/events/{$this->match->id}",
            'match_id' => $this->match->id,
            'icon' => '/icons/icon-192.png',
            'tag' => "reg-open-{$this->match->id}",
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Registration Open — {$this->match->name}")
            ->greeting("Hey {$notifiable->name}!")
            ->line("Registration is now open for **{$this->match->name}**.");

        if ($this->match->date) {
            $mail->line("Date: {$this->match->date->format('j M Y')}");
        }
        if ($this->match->location) {
            $mail->line("Location: {$this->match->location}");
        }

        return $mail
            ->action('Register Now', url("/events/{$this->match->id}"))
            ->line('You showed interest in this event — secure your spot before it fills up.')
            ->salutation('— DeadCenter');
    }
}
