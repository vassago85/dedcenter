<?php

namespace App\Notifications;

use App\Models\ShootingMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Notifies an imported match entrant that they are entered in an upcoming
 * match, and prompts them to:
 *
 *  - set a password (new accounts), then
 *  - sign in and self-squad (everyone).
 *
 * This notification is intentionally NOT gated by `wantsNotification(...)` —
 * it's an account/match onboarding mail, the same class as
 * `ResetPasswordNotification` and `EmailVerificationPin`. Members who
 * already have accounts but opted out of marketing still need this one to
 * find their entry and pick a squad.
 */
class MatchEntryInviteNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ShootingMatch $match,
        public bool $isNewAccount,
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toArray($notifiable): array
    {
        $url = $this->isNewAccount
            ? $this->setPasswordUrl($notifiable)
            : url("/matches/{$this->match->id}/squadding");

        return [
            'title' => "You're entered in {$this->match->name}",
            'body' => $this->isNewAccount
                ? 'Set your password to confirm your entry and pick a squad.'
                : 'Confirm your entry and pick a squad.',
            'url' => $url,
            'match_id' => $this->match->id,
            'icon' => '/icons/icon-192.png',
            'tag' => "match-invite-{$this->match->id}",
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $matchName = $this->match->name;
        $dateLine = $this->match->date ? "Date: {$this->match->date->format('j M Y')}" : null;
        $venueLine = $this->match->location ? "Location: {$this->match->location}" : null;

        $mail = (new MailMessage)
            ->subject("You're entered in {$matchName} — DeadCenter")
            ->greeting("Hey {$notifiable->name}!")
            ->line("You're entered in **{$matchName}**.");

        if ($dateLine) {
            $mail->line($dateLine);
        }
        if ($venueLine) {
            $mail->line($venueLine);
        }

        if ($this->isNewAccount) {
            $url = $this->setPasswordUrl($notifiable);
            $mail
                ->line("We've created a DeadCenter account for you so you can confirm your entry, pick a squad, and see your scoreboard live on match day.")
                ->action('Set Password & Pick Squad', $url)
                ->line('The link above expires in 60 minutes. If it expires before you use it, head to '.url('/forgot-password').' and request a fresh one with this email address.');
        } else {
            $url = url("/matches/{$this->match->id}/squadding");
            $mail
                ->line('You already have a DeadCenter account — sign in to confirm your entry and pick a squad.')
                ->action('Pick Your Squad', $url)
                ->line('Squads fill on a first-come basis, so grab your spot early.');
        }

        return $mail
            ->line('See you on the range!')
            ->salutation('— DeadCenter');
    }

    private function setPasswordUrl($notifiable): string
    {
        // Use the password-broker token machinery for new-account onboarding —
        // it's the same flow as ResetPasswordNotification but framed as
        // "set your password" instead of "reset your password" in the copy.
        $token = Password::createToken($notifiable);

        return url(route('password.reset', [
            'token' => $token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }
}
