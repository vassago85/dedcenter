<?php

namespace App\Services;

use App\Enums\ShooterClaimStatus;
use App\Models\MatchRegistration;
use App\Models\ShooterAccountClaim;
use App\Models\UserAchievement;
use App\Models\UserEquipmentProfile;
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates the approve/reject state transitions for shooter account
 * claims. Extracted out of the admin Volt component so the flow can be
 * unit-tested without rendering a view (the project-wide view compile
 * hits an unrelated <flux:tab.group> error).
 */
class ShooterAccountClaimService
{
    /**
     * Possible outcomes returned from approve() so the caller can pick
     * the right toast copy.
     */
    public const APPROVED_WALKIN = 'approved_walkin';
    public const APPROVED_IMPORTED = 'approved_imported';
    public const REJECTED_ALREADY_LINKED = 'rejected_already_linked';
    public const NOT_PENDING = 'not_pending';

    /**
     * Approve a claim, linking the shooter row to the claimant's account.
     *
     * When the shooter row was previously linked to an @import.invalid
     * placeholder user, this also migrates the imported MatchRegistration
     * (equipment / rifle data) and any match-scoped UserAchievement rows
     * from the placeholder over to the claimant so they show up on the
     * real user's profile. The placeholder User row is LEFT IN PLACE
     * intentionally (can't be used to log in, and leaving it preserves
     * idempotency if the importer runs again).
     *
     * Returns one of the APPROVED_* / REJECTED_* / NOT_PENDING constants.
     */
    public function approve(ShooterAccountClaim $claim, ?int $reviewerId, ?string $reviewerNote = null): string
    {
        $claim->loadMissing('shooter.user');

        if (! $claim->isPending()) {
            return self::NOT_PENDING;
        }

        $currentUserId = $claim->shooter->user_id;
        $currentUser = $claim->shooter->user;

        $alreadyLinkedToRealUser = $currentUserId !== null
            && $currentUserId !== $claim->user_id
            && $currentUser !== null
            && ! $currentUser->isImportPlaceholder();

        if ($alreadyLinkedToRealUser) {
            $claim->update([
                'status' => ShooterClaimStatus::Rejected,
                'reviewer_id' => $reviewerId,
                'reviewed_at' => now(),
                'reviewer_note' => 'Auto-rejected: shooter already linked to a different account.',
            ]);

            return self::REJECTED_ALREADY_LINKED;
        }

        $placeholderUserId = ($currentUser !== null && $currentUser->isImportPlaceholder())
            ? $currentUserId
            : null;

        DB::transaction(function () use ($claim, $placeholderUserId, $reviewerId, $reviewerNote) {
            $claim->shooter->update(['user_id' => $claim->user_id]);

            if ($placeholderUserId !== null) {
                $this->moveImportedRegistration($placeholderUserId, $claim->user_id, $claim->match_id);

                $alreadyHeld = UserAchievement::where('user_id', $claim->user_id)
                    ->where('match_id', $claim->match_id)
                    ->pluck('achievement_id');

                UserAchievement::where('user_id', $placeholderUserId)
                    ->where('match_id', $claim->match_id)
                    ->whereNotIn('achievement_id', $alreadyHeld)
                    ->update(['user_id' => $claim->user_id]);
            }

            $claim->update([
                'status' => ShooterClaimStatus::Approved,
                'reviewer_id' => $reviewerId,
                'reviewed_at' => now(),
                'reviewer_note' => $reviewerNote,
            ]);

            ShooterAccountClaim::where('shooter_id', $claim->shooter_id)
                ->where('id', '!=', $claim->id)
                ->where('status', ShooterClaimStatus::Pending)
                ->update([
                    'status' => ShooterClaimStatus::Rejected,
                    'reviewer_id' => $reviewerId,
                    'reviewed_at' => now(),
                    'reviewer_note' => 'Auto-rejected: another claim for this shooter was approved.',
                ]);
        });

        return $placeholderUserId !== null
            ? self::APPROVED_IMPORTED
            : self::APPROVED_WALKIN;
    }

    /**
     * Hand the imported registration to the claimant. A user can only hold one
     * registration per match, so if they also entered themselves we keep theirs,
     * fill its blank equipment fields from the import, and drop the import.
     */
    private function moveImportedRegistration(int $fromUserId, int $toUserId, int $matchId): void
    {
        $imported = MatchRegistration::where('user_id', $fromUserId)->where('match_id', $matchId)->first();
        if (! $imported) {
            return;
        }

        $own = MatchRegistration::where('user_id', $toUserId)->where('match_id', $matchId)->first();
        if (! $own) {
            $imported->update(['user_id' => $toUserId]);

            return;
        }

        $fill = collect([...UserEquipmentProfile::EQUIPMENT_FIELDS, 'rifle_id', 'division_id', 'category_id', 'alrha_class'])
            ->filter(fn (string $field) => blank($own->{$field}) && filled($imported->{$field}))
            ->mapWithKeys(fn (string $field) => [$field => $imported->{$field}])
            ->all();

        if ($fill) {
            $own->update($fill);
        }
        $imported->delete();
    }
}
