<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OrganizationApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(public Organization $organization) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Organization Approved',
            'body' => "Your organization \"{$this->organization->name}\" has been approved! You can now create matches.",
            'url' => "/org/{$this->organization->slug}/dashboard",
            'icon' => '/icons/icon-192.png',
            'tag' => "org-approved-{$this->organization->id}",
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Organization Has Been Approved — DeadCenter')
            ->greeting("Good news, {$notifiable->name}!")
            ->line("Your organization \"{$this->organization->name}\" has been approved on DeadCenter.")
            ->line('You can now create matches and start hosting events.')
            ->action('Go to Your Organization', url("/org/{$this->organization->slug}/dashboard"))
            ->salutation('— DeadCenter');
    }
}
