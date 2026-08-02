<?php

namespace App\Console\Commands;

use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Attach an import placeholder account to every match shooter that has no
 * linked user yet (user_id IS NULL).
 *
 * Background: some matches were seeded/imported before placeholder accounts
 * were created for un-matched shooters. A shooter with a NULL user_id has
 * nowhere for a podium / achievement badge to land, so the podium ranking
 * silently dropped them — which handed the gold to a lower, account-linked
 * finisher. Giving every shooter an @import.invalid placeholder makes them
 * rankable, surfaces them as "awaiting claim", and lets any badge transfer
 * to the real profile once they claim (ShooterAccountClaimService::approve).
 *
 * After running this, re-run `badges:reevaluate {match} --full` so the
 * podium is recomputed with the now-complete field.
 *
 * Safe to re-run: only shooters still missing a user_id are touched.
 */
class BackfillShooterPlaceholders extends Command
{
    protected $signature = 'shooters:backfill-placeholders
        {match : Match ID whose unlinked shooters should get placeholders}
        {--dry-run : List who would be linked without writing anything}';

    protected $description = 'Give an import placeholder account to match shooters that have no linked user, so podium/achievement badges can be awarded and later claimed.';

    public function handle(): int
    {
        $matchId = (int) $this->argument('match');
        $match = ShootingMatch::find($matchId);

        if (! $match) {
            $this->error("Match {$matchId} not found.");

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        $shooters = Shooter::query()
            ->whereHas('squad', fn ($q) => $q->where('match_id', $matchId))
            ->whereNull('user_id')
            ->orderBy('id')
            ->get();

        if ($shooters->isEmpty()) {
            $this->info("Match {$matchId}: every shooter already has a linked user. Nothing to do.");

            return self::SUCCESS;
        }

        $linked = 0;
        foreach ($shooters as $index => $shooter) {
            $user = $this->placeholderUser($match, (string) $shooter->name, $index);

            $this->line(sprintf(
                '  shooter %d · %s → placeholder user %d (%s)',
                $shooter->id,
                $shooter->name,
                $user->id,
                $user->email,
            ));

            if (! $dry) {
                $shooter->user_id = $user->id;
                $shooter->save();
            }

            $linked++;
        }

        $this->info(sprintf(
            'Match %d: %s %d shooter(s) to import placeholders.',
            $matchId,
            $dry ? 'would link' : 'linked',
            $linked,
        ));

        if (! $dry) {
            $this->comment("Next: php artisan badges:reevaluate {$matchId} --full");
        }

        return self::SUCCESS;
    }

    /**
     * Create (or reuse) a deterministic @import.invalid placeholder account
     * for this shooter so re-running the command never spawns duplicates.
     */
    private function placeholderUser(ShootingMatch $match, string $name, int $index): User
    {
        $hash = substr(hash('sha256', $match->id.'|'.$name.'|'.$index), 0, 20);
        $email = sprintf('pprc.m%d.n%s%s', $match->id, $hash, User::IMPORT_PLACEHOLDER_EMAIL_SUFFIX);

        $user = User::where('email', $email)->first();
        if ($user) {
            return $user;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(Str::random(40)),
            'role' => 'shooter',
            'accepted_terms_at' => now(),
        ]);
        $user->forceFill(['email_verified_at' => null])->save();

        return $user;
    }
}
