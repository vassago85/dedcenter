<?php

namespace App\Console\Commands;

use App\Models\MatchRegistration;
use App\Models\ShootingMatch;
use App\Models\User;
use App\Notifications\MatchEntryInviteNotification;
use Illuminate\Console\Command;

/**
 * Sends a "you're entered" invite to every confirmed registrant on a match.
 *
 * New accounts (no verified email yet) get a set-password link; existing
 * accounts get a direct link to the self-squad page.
 *
 * Usage:
 *   php artisan match:invite-entries 42                       # invite everyone
 *   php artisan match:invite-entries 42 --only=new            # only freshly created accounts
 *   php artisan match:invite-entries 42 --only=existing       # only pre-existing accounts
 *   php artisan match:invite-entries 42 --test=md@example.com # send a single test to one address
 *   php artisan match:invite-entries 42 --dry-run             # don't send, just print who'd get it
 */
class InviteMatchEntries extends Command
{
    protected $signature = 'match:invite-entries
        {match : The match ID to invite registrants for}
        {--only=all : new|existing|all  — pick which subset to invite (default: all)}
        {--test= : Send a single test invite to this email address only (must be a confirmed registrant)}
        {--dry-run : List recipients without sending}';

    protected $description = "Email every confirmed registrant a 'you're entered' invite (with set-password link for new accounts).";

    public function handle(): int
    {
        $matchId = (int) $this->argument('match');
        $match = ShootingMatch::find($matchId);
        if (! $match) {
            $this->error("Match #{$matchId} not found.");

            return self::FAILURE;
        }

        $only = strtolower((string) $this->option('only'));
        if (! in_array($only, ['new', 'existing', 'all'], true)) {
            $this->error("--only must be one of: new, existing, all. Got: {$only}");

            return self::FAILURE;
        }

        $testEmail = $this->option('test');
        $dryRun = (bool) $this->option('dry-run');

        $query = MatchRegistration::query()
            ->where('match_id', $match->id)
            ->where('payment_status', 'confirmed')
            ->with('user');

        $registrations = $query->get()
            ->filter(fn ($r) => $r->user !== null);

        if ($testEmail !== null && $testEmail !== '') {
            $testEmail = strtolower(trim((string) $testEmail));
            $registrations = $registrations->filter(
                fn ($r) => strtolower($r->user->email) === $testEmail
            );
            if ($registrations->isEmpty()) {
                $this->error("No confirmed registrant on match #{$match->id} with email {$testEmail}.");

                return self::FAILURE;
            }
        }

        $this->line("Match: [{$match->id}] {$match->name}");
        $this->line("Mode:  {$only}".($testEmail ? "  ·  test={$testEmail}" : '').($dryRun ? '  ·  DRY RUN' : ''));
        $this->newLine();

        $sent = 0;
        $skipped = 0;

        foreach ($registrations as $reg) {
            $user = $reg->user;
            $isNewAccount = $this->looksLikeNewAccount($user);

            if ($only === 'new' && ! $isNewAccount) {
                $skipped++;

                continue;
            }
            if ($only === 'existing' && $isNewAccount) {
                $skipped++;

                continue;
            }

            $tag = $isNewAccount ? '[new]     ' : '[existing]';
            $this->line(" {$tag} {$user->email}  ({$user->name})");

            if (! $dryRun) {
                try {
                    $user->notify(new MatchEntryInviteNotification($match, $isNewAccount));
                    $sent++;
                } catch (\Throwable $e) {
                    $this->error('  ↳ send failed: '.$e->getMessage());
                }
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->comment("Dry run — would send to {$registrations->count()} registrant(s); skipped {$skipped} due to --only={$only}.");
        } else {
            $this->info("Sent {$sent} invite(s); skipped {$skipped} due to --only={$only}.");
        }

        return self::SUCCESS;
    }

    private function looksLikeNewAccount(User $user): bool
    {
        // "New" = the importer just created this account moments ago and
        // the entrant has never claimed it. The importer deliberately
        // leaves email_verified_at null, so that doubles as our
        // "needs to set a password" signal — once they accept the
        // invite link and finish setup, email_verified_at gets stamped.
        return $user->email_verified_at === null;
    }
}
