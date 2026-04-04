<?php

namespace App\Notifications;

use App\Models\MatchMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MatchAnnouncementNotification extends Notification
{
    use Queueable;

    public function __construct(public MatchMessage $message) {}

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
            'title' => $this->message->subject,
            'body' => $this->message->body,
            'url' => "/events/{$this->message->match_id}",
            'match_id' => $this->message->match_id,
            'sender' => $this->message->sender?->name,
            'icon' => '/icons/icon-192.png',
            'tag' => "announcement-{$this->message->id}",
            'type' => 'match_announcement',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $match = $this->message->match;
        $sender = $this->message->sender;

        return (new MailMessage)
            ->subject($this->message->subject . ($match ? " — {$match->name}" : ''))
            ->greeting("Hey {$notifiable->name}!")
            ->line($sender ? "Message from **{$sender->name}**" . ($match ? " about **{$match->name}**:" : ':') : '')
            ->line($this->message->body)
            ->action('View Match', url("/events/{$this->message->match_id}"))
            ->salutation('— DeadCenter');
    }
}
