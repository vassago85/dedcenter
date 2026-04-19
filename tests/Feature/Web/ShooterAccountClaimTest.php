<?php

/**
 * Exercises the "Claim your result" flow for IMPORTED shooters, i.e.
 * shooters whose row already has a user_id set — but that user is the
 * @import.invalid placeholder the RoyalFlushEquipmentImportService
 * creates. These rows are effectively unclaimed (the placeholder user
 * can't log in and has no real email) and the real shooter must still
 * be able to claim them from the scoreboard.
 *
 * Prior to the User::isImportPlaceholder() work, these rows were hidden
 * from the Claim chip/banner AND auto-rejected by the admin approval
 * flow because shooter.user_id was already non-null.
 */

use App\Enums\MatchStatus;
use App\Enums\ShooterClaimStatus;
use App\Models\Achievement;
use App\Models\MatchRegistration;
use App\Models\Shooter;
use App\Models\ShooterAccountClaim;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\User;
use App\Models\UserAchievement;
use App\Services\ShooterAccountClaimService;

/** Helper: a fresh placeholder user like the importer would create. */
function makePlaceholderUser(ShootingMatch $match, string $suffix = 'abc'): User
{
    return User::factory()->create([
        'name' => 'Imported Shooter',
        'email' => "rf.m{$match->id}.n{$suffix}".User::IMPORT_PLACEHOLDER_EMAIL_SUFFIX,
        'role' => 'shooter',
    ]);
}

it('User::isImportPlaceholder identifies @import.invalid emails', function () {
    $match = ShootingMatch::factory()->create();
    $placeholder = makePlaceholderUser($match);
    $real = User::factory()->create(['email' => 'real@example.com']);

    expect($placeholder->isImportPlaceholder())->toBeTrue();
    expect($real->isImportPlaceholder())->toBeFalse();
});

it('Shooter::isUnclaimedResult is true for walk-ins with no user_id', function () {
    $match = ShootingMatch::factory()->create();
    $squad = Squad::factory()->create(['match_id' => $match->id]);
    $walkin = Shooter::factory()->create([
        'squad_id' => $squad->id,
        'user_id' => null,
    ]);

    expect($walkin->isUnclaimedResult())->toBeTrue();
});

it('Shooter::isUnclaimedResult is true for imported shooters linked to a placeholder user', function () {
    $match = ShootingMatch::factory()->create();
    $squad = Squad::factory()->create(['match_id' => $match->id]);
    $placeholder = makePlaceholderUser($match);
    $imported = Shooter::factory()->create([
        'squad_id' => $squad->id,
        'user_id' => $placeholder->id,
    ]);

    expect($imported->fresh()->isUnclaimedResult())->toBeTrue();
});

it('Shooter::isUnclaimedResult is false for shooters linked to a real user', function () {
    $match = ShootingMatch::factory()->create();
    $squad = Squad::factory()->create(['match_id' => $match->id]);
    $real = User::factory()->create(['email' => 'real@example.com']);
    $claimed = Shooter::factory()->create([
        'squad_id' => $squad->id,
        'user_id' => $real->id,
    ]);

    expect($claimed->fresh()->isUnclaimedResult())->toBeFalse();
});

it('Shooter::unclaimedResult scope returns placeholder-linked AND null-user rows, excludes real links', function () {
    $match = ShootingMatch::factory()->create();
    $squad = Squad::factory()->create(['match_id' => $match->id]);
    $placeholder = makePlaceholderUser($match);
    $real = User::factory()->create(['email' => 'real@example.com']);

    $walkin = Shooter::factory()->create(['squad_id' => $squad->id, 'user_id' => null]);
    $imported = Shooter::factory()->create(['squad_id' => $squad->id, 'user_id' => $placeholder->id]);
    $claimed = Shooter::factory()->create(['squad_id' => $squad->id, 'user_id' => $real->id]);

    $ids = Shooter::query()->unclaimedResult()->pluck('id')->all();

    expect($ids)->toContain($walkin->id);
    expect($ids)->toContain($imported->id);
    expect($ids)->not->toContain($claimed->id);
});

it('approving a claim for an imported shooter re-points the shooter AND the placeholder registration + badges', function () {
    $match = ShootingMatch::factory()->create(['status' => MatchStatus::Completed]);
    $squad = Squad::factory()->create(['match_id' => $match->id]);

    $placeholder = makePlaceholderUser($match);
    $claimant = User::factory()->create(['email' => 'claimant@example.com', 'role' => 'shooter']);
    $admin = User::factory()->create(['role' => 'owner']);

    $shooter = Shooter::factory()->create([
        'squad_id' => $squad->id,
        'user_id' => $placeholder->id,
    ]);

    $registration = MatchRegistration::create([
        'match_id' => $match->id,
        'user_id' => $placeholder->id,
        'payment_status' => 'confirmed',
        'payment_reference' => 'TEST-REF-1',
        'amount' => 0,
        'caliber' => '6.5 Creedmoor',
    ]);

    $achievement = Achievement::create([
        'slug' => 'test-badge',
        'label' => 'Test Badge',
        'description' => 'For test purposes.',
        'category' => 'match_special',
        'scope' => 'match',
        'is_repeatable' => false,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $badge = UserAchievement::create([
        'user_id' => $placeholder->id,
        'achievement_id' => $achievement->id,
        'match_id' => $match->id,
    ]);

    $claim = ShooterAccountClaim::create([
        'user_id' => $claimant->id,
        'shooter_id' => $shooter->id,
        'match_id' => $match->id,
        'status' => ShooterClaimStatus::Pending,
        'evidence' => 'I was on Alpha squad with a 6.5 CM.',
    ]);

    $outcome = app(ShooterAccountClaimService::class)->approve($claim, $admin->id, 'Verified via roster.');

    expect($outcome)->toBe(ShooterAccountClaimService::APPROVED_IMPORTED);
    expect($shooter->fresh()->user_id)->toBe($claimant->id);
    expect($claim->fresh()->status)->toBe(ShooterClaimStatus::Approved);
    expect($registration->fresh()->user_id)->toBe($claimant->id);
    expect($badge->fresh()->user_id)->toBe($claimant->id);
    expect(User::find($placeholder->id))->not->toBeNull();
});

it('approval rejects when shooter is linked to a different REAL account', function () {
    $match = ShootingMatch::factory()->create(['status' => MatchStatus::Completed]);
    $squad = Squad::factory()->create(['match_id' => $match->id]);

    $otherReal = User::factory()->create(['email' => 'someone@example.com']);
    $claimant = User::factory()->create(['email' => 'claimant@example.com']);
    $admin = User::factory()->create(['role' => 'owner']);

    $shooter = Shooter::factory()->create([
        'squad_id' => $squad->id,
        'user_id' => $otherReal->id,
    ]);

    $claim = ShooterAccountClaim::create([
        'user_id' => $claimant->id,
        'shooter_id' => $shooter->id,
        'match_id' => $match->id,
        'status' => ShooterClaimStatus::Pending,
    ]);

    $outcome = app(ShooterAccountClaimService::class)->approve($claim, $admin->id);

    expect($outcome)->toBe(ShooterAccountClaimService::REJECTED_ALREADY_LINKED);
    expect($claim->fresh()->status)->toBe(ShooterClaimStatus::Rejected);
    expect($shooter->fresh()->user_id)->toBe($otherReal->id);
});

it('approval for a walk-in (null user_id) marks APPROVED_WALKIN without migrating anything', function () {
    $match = ShootingMatch::factory()->create(['status' => MatchStatus::Completed]);
    $squad = Squad::factory()->create(['match_id' => $match->id]);
    $claimant = User::factory()->create(['email' => 'claimant@example.com']);
    $admin = User::factory()->create(['role' => 'owner']);

    $shooter = Shooter::factory()->create([
        'squad_id' => $squad->id,
        'user_id' => null,
    ]);

    $claim = ShooterAccountClaim::create([
        'user_id' => $claimant->id,
        'shooter_id' => $shooter->id,
        'match_id' => $match->id,
        'status' => ShooterClaimStatus::Pending,
    ]);

    $outcome = app(ShooterAccountClaimService::class)->approve($claim, $admin->id);

    expect($outcome)->toBe(ShooterAccountClaimService::APPROVED_WALKIN);
    expect($shooter->fresh()->user_id)->toBe($claimant->id);
});

it('approval is a no-op when the claim is already approved/rejected', function () {
    $match = ShootingMatch::factory()->create(['status' => MatchStatus::Completed]);
    $squad = Squad::factory()->create(['match_id' => $match->id]);
    $claimant = User::factory()->create();
    $admin = User::factory()->create(['role' => 'owner']);

    $shooter = Shooter::factory()->create([
        'squad_id' => $squad->id,
        'user_id' => null,
    ]);

    $claim = ShooterAccountClaim::create([
        'user_id' => $claimant->id,
        'shooter_id' => $shooter->id,
        'match_id' => $match->id,
        'status' => ShooterClaimStatus::Approved,
    ]);

    $outcome = app(ShooterAccountClaimService::class)->approve($claim, $admin->id);

    expect($outcome)->toBe(ShooterAccountClaimService::NOT_PENDING);
});
