<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationPin extends Notification
{
    use Queueable;

    public function __construct(public string $code) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your email — DeadCenter')
            ->greeting("Welcome to DeadCenter!")
            ->line('Use the code below to verify your email address:')
            ->line("**{$this->code}**")
            ->line('This code expires in 30 minutes.')
            ->line('If you did not create an account, no action is required.');
    }
}
