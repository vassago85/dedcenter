<?php

use App\Models\MatchRegistration;
use App\Models\ShootingMatch;
use App\Models\User;
use App\Notifications\MatchEntryInviteNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->match = ShootingMatch::factory()->active()->prs()->create([
        'created_by' => $this->admin->id,
        'name' => 'PPRC Test Match',
    ]);

    // Fresh (unverified) account — "new" in the importer's eyes
    $this->fresh = User::factory()->create([
        'email_verified_at' => null,
    ]);
    MatchRegistration::factory()->confirmed()->create([
        'match_id' => $this->match->id,
        'user_id' => $this->fresh->id,
    ]);

    // Existing (verified) account — "existing"
    $this->existing = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    MatchRegistration::factory()->confirmed()->create([
        'match_id' => $this->match->id,
        'user_id' => $this->existing->id,
    ]);

    // Not registered — should never receive an invite
    $this->bystander = User::factory()->create();
});

test('match:invite-entries --dry-run sends nothing', function () {
    Notification::fake();

    $this->artisan('match:invite-entries', [
        'match' => $this->match->id,
        '--dry-run' => true,
    ])->assertOk();

    Notification::assertNothingSent();
});

test('match:invite-entries --only=new only mails fresh accounts', function () {
    Notification::fake();

    $this->artisan('match:invite-entries', [
        'match' => $this->match->id,
        '--only' => 'new',
    ])->assertOk();

    Notification::assertSentTo($this->fresh, MatchEntryInviteNotification::class, function ($n) {
        return $n->isNewAccount === true && $n->match->id === $this->match->id;
    });
    Notification::assertNotSentTo($this->existing, MatchEntryInviteNotification::class);
    Notification::assertNotSentTo($this->bystander, MatchEntryInviteNotification::class);
});

test('match:invite-entries --only=existing only mails verified accounts', function () {
    Notification::fake();

    $this->artisan('match:invite-entries', [
        'match' => $this->match->id,
        '--only' => 'existing',
    ])->assertOk();

    Notification::assertSentTo($this->existing, MatchEntryInviteNotification::class, function ($n) {
        return $n->isNewAccount === false;
    });
    Notification::assertNotSentTo($this->fresh, MatchEntryInviteNotification::class);
});

test('match:invite-entries (default --only=all) mails every confirmed registrant', function () {
    Notification::fake();

    $this->artisan('match:invite-entries', [
        'match' => $this->match->id,
    ])->assertOk();

    Notification::assertSentTo($this->fresh, MatchEntryInviteNotification::class);
    Notification::assertSentTo($this->existing, MatchEntryInviteNotification::class);
    Notification::assertNotSentTo($this->bystander, MatchEntryInviteNotification::class);
});

test('match:invite-entries --test restricts to a single recipient', function () {
    Notification::fake();

    $this->artisan('match:invite-entries', [
        'match' => $this->match->id,
        '--test' => $this->existing->email,
    ])->assertOk();

    Notification::assertSentTo($this->existing, MatchEntryInviteNotification::class);
    Notification::assertNotSentTo($this->fresh, MatchEntryInviteNotification::class);
});

test('invite notification mailable renders with the expected subject and CTA', function () {
    $newMail = (new MatchEntryInviteNotification($this->match, isNewAccount: true))->toMail($this->fresh);
    $rendered = $newMail->toArray();

    expect($rendered['subject'])->toContain('PPRC Test Match');
    expect(implode("\n", $rendered['actionText'] !== null ? [$rendered['actionText']] : []))
        ->toContain('Set Password');

    $existingMail = (new MatchEntryInviteNotification($this->match, isNewAccount: false))->toMail($this->existing);
    expect($existingMail->toArray()['actionText'])->toBe('Pick Your Squad');
});
