<?php

use App\Models\Disqualification;
use App\Models\Gong;
use App\Models\MatchDivision;
use App\Models\MatchRegistration;
use App\Models\Organization;
use App\Models\PushSubscription;
use App\Models\Score;
use App\Models\Season;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use Illuminate\Support\Str;

/*
 * Happy paths and guard rails for the API endpoints the scoring app and the
 * Android hub call that had no coverage of their own.
 */

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->md = User::factory()->create(['password' => bcrypt('secret-pass')]);
    $this->org->admins()->attach($this->md, ['is_match_director' => true]);
    $this->shooterUser = User::factory()->create();

    $this->match = ShootingMatch::factory()->active()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->md->id,
    ]);
    $stage = TargetSet::factory()->create(['match_id' => $this->match->id]);
    $this->gong = Gong::factory()->create(['target_set_id' => $stage->id, 'number' => 1]);
    $this->alpha = Squad::factory()->create(['match_id' => $this->match->id, 'name' => 'Alpha', 'sort_order' => 1]);
    $this->bravo = Squad::factory()->create(['match_id' => $this->match->id, 'name' => 'Bravo', 'sort_order' => 2]);
    $this->first = Shooter::factory()->create(['squad_id' => $this->alpha->id, 'sort_order' => 1]);
    $this->second = Shooter::factory()->create(['squad_id' => $this->alpha->id, 'sort_order' => 2]);
});

// ── Token auth used by the native app ──

it('logs in with a device token, reads the user, and logs that token out', function () {
    $token = $this->postJson('/api/login', ['email' => $this->md->email, 'password' => 'secret-pass', 'device_name' => 'tablet-1'])
        ->assertOk()
        ->assertJsonPath('user.email', $this->md->email)
        ->json('token');

    $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('user.id', $this->md->id);
    $this->withToken($token)->postJson('/api/logout')->assertOk();

    expect($this->md->tokens()->count())->toBe(0);
});

it('rejects a wrong password at login', function () {
    $this->postJson('/api/login', ['email' => $this->md->email, 'password' => 'nope', 'device_name' => 'x'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('verifies the password for destructive actions and checks match authority', function () {
    $this->actingAs($this->md)->postJson('/api/auth/verify-password', ['password' => 'secret-pass', 'match_id' => $this->match->id])
        ->assertOk()->assertJsonPath('ok', true);
    $this->actingAs($this->md)->postJson('/api/auth/verify-password', ['password' => 'wrong'])
        ->assertStatus(401);
    $this->actingAs($this->shooterUser)->postJson('/api/auth/verify-password', ['password' => 'password', 'match_id' => $this->match->id])
        ->assertForbidden();
});

it('rate-limits password verification', function () {
    foreach (range(1, 10) as $_) {
        $this->actingAs($this->md)->postJson('/api/auth/verify-password', ['password' => 'wrong'])->assertStatus(401);
    }
    $this->actingAs($this->md)->postJson('/api/auth/verify-password', ['password' => 'secret-pass'])->assertStatus(429);
});

// ── Manage Shooters on the tablet ──

it('adds a walk-in, moves them to another squad, and changes firing order', function () {
    $division = MatchDivision::factory()->create(['match_id' => $this->match->id, 'name' => 'Open']);

    $this->actingAs($this->md)->postJson("/api/matches/{$this->match->id}/shooters", [
        'name' => 'Walk In', 'caliber' => '6 Dasher', 'squad_id' => $this->alpha->id,
    ])->assertStatus(422)->assertJsonValidationErrors('division_id');

    $id = $this->actingAs($this->md)->postJson("/api/matches/{$this->match->id}/shooters", [
        'name' => 'Walk In', 'caliber' => '6 Dasher', 'squad_id' => $this->alpha->id, 'division_id' => $division->id,
    ])->assertCreated()->assertJsonPath('shooter.name', 'Walk In — 6 Dasher')->json('shooter.id');

    $this->actingAs($this->md)->patchJson("/api/matches/{$this->match->id}/shooters/{$id}/squad", ['squad_id' => $this->bravo->id])
        ->assertOk()->assertJsonPath('shooter.squad_id', $this->bravo->id);

    $this->actingAs($this->md)->patchJson("/api/matches/{$this->match->id}/shooters/{$this->second->id}/order", ['direction' => 'up'])
        ->assertOk();
    expect($this->second->fresh()->sort_order)->toBeLessThan($this->first->fresh()->sort_order);
});

it('will not move a shooter from another match or into a full squad', function () {
    $foreign = Shooter::factory()->create();
    $this->actingAs($this->md)->patchJson("/api/matches/{$this->match->id}/shooters/{$foreign->id}/squad", ['squad_id' => $this->bravo->id])
        ->assertNotFound();

    $this->bravo->update(['max_capacity' => 1]);
    Shooter::factory()->create(['squad_id' => $this->bravo->id]);
    $this->actingAs($this->md)->patchJson("/api/matches/{$this->match->id}/shooters/{$this->first->id}/squad", ['squad_id' => $this->bravo->id])
        ->assertStatus(422);
});

it('only match staff can manage shooters', function () {
    $this->actingAs($this->shooterUser)->postJson("/api/matches/{$this->match->id}/shooters", ['name' => 'X', 'squad_id' => $this->alpha->id])
        ->assertForbidden();
});

// ── Disqualifications ──

it('revoking a match DQ reinstates the shooter', function () {
    $dqId = $this->actingAs($this->md)->postJson("/api/matches/{$this->match->id}/disqualifications", [
        'shooter_id' => $this->first->id, 'reason' => 'Unsafe muzzle direction',
    ])->assertCreated()->json('disqualification.id');
    expect($this->first->fresh()->status)->toBe('dq');

    $this->actingAs($this->md)->deleteJson("/api/matches/{$this->match->id}/disqualifications/{$dqId}")->assertOk();

    expect(Disqualification::find($dqId))->toBeNull()
        ->and($this->first->fresh()->status)->toBe('active');
});

it('will not revoke a DQ through another match', function () {
    $other = ShootingMatch::factory()->active()->create(['organization_id' => $this->org->id, 'created_by' => $this->md->id]);
    $dq = Disqualification::create(['match_id' => $this->match->id, 'shooter_id' => $this->first->id, 'reason' => 'Unsafe act', 'issued_by' => $this->md->id]);

    $this->actingAs($this->md)->deleteJson("/api/matches/{$other->id}/disqualifications/{$dq->id}")->assertNotFound();
    expect(Disqualification::find($dq->id))->not->toBeNull();
});

// ── Cloud → device sync ──

it('incremental sync returns scores written after the previous pull', function () {
    Score::factory()->create(['shooter_id' => $this->first->id, 'gong_id' => $this->gong->id, 'is_hit' => true]);
    $this->travel(5)->seconds();

    $first = $this->actingAs($this->md)->getJson("/api/matches/{$this->match->id}/scores/sync")->assertOk();
    expect($first->json('scores'))->toHaveCount(1)
        ->and($first->json('shooters'))->toHaveCount(2);

    $this->travel(5)->seconds();
    $late = Score::factory()->create(['shooter_id' => $this->second->id, 'gong_id' => $this->gong->id, 'is_hit' => false]);

    $next = $this->actingAs($this->md)->getJson("/api/matches/{$this->match->id}/scores/sync?since=".urlencode($first->json('server_time')))
        ->assertOk();
    expect(collect($next->json('scores'))->pluck('id')->all())->toBe([$late->id]);
});

it('a score saved in the same second as the pull is not lost', function () {
    $this->freezeSecond();
    $first = $this->actingAs($this->md)->getJson("/api/matches/{$this->match->id}/scores/sync")->assertOk();
    $sameSecond = Score::factory()->create(['shooter_id' => $this->first->id, 'gong_id' => $this->gong->id, 'is_hit' => true]);

    $next = $this->actingAs($this->md)->getJson("/api/matches/{$this->match->id}/scores/sync?since=".urlencode($first->json('server_time')));
    expect(collect($next->json('scores'))->pluck('id'))->toContain($sameSecond->id);
});

it('sync accepts the UTC cursor a fresh device sends and rejects garbage', function () {
    Score::factory()->create(['shooter_id' => $this->first->id, 'gong_id' => $this->gong->id, 'is_hit' => true]);

    $this->actingAs($this->md)->getJson("/api/matches/{$this->match->id}/scores/sync?since=1970-01-01T00:00:00Z")
        ->assertOk()->assertJsonCount(1, 'scores');
    $this->actingAs($this->md)->getJson("/api/matches/{$this->match->id}/scores/sync?since=not-a-date")
        ->assertStatus(422);
});

it('sync is closed to non-staff', function () {
    $this->actingAs($this->shooterUser)->getJson("/api/matches/{$this->match->id}/scores/sync")->assertForbidden();
});

// ── Member app data ──

it('lists my matches separately from ones I can browse', function () {
    $upcoming = ShootingMatch::factory()->create(['status' => 'registration_open']);
    $browse = ShootingMatch::factory()->create(['status' => 'registration_open']);
    MatchRegistration::factory()->create(['match_id' => $upcoming->id, 'user_id' => $this->shooterUser->id]);

    $res = $this->actingAs($this->shooterUser)->getJson('/api/member/matches')->assertOk();

    expect(collect($res->json('upcoming'))->pluck('id')->all())->toBe([$upcoming->id])
        ->and(collect($res->json('browse'))->pluck('id'))->toContain($browse->id)
        ->not->toContain($upcoming->id);
});

it('lists seasons and their standings publicly', function () {
    $season = Season::create(['name' => '2026 Series', 'year' => 2026, 'organization_id' => $this->org->id]);

    $this->getJson('/api/seasons')->assertOk()->assertJsonPath('seasons.0.name', '2026 Series');
    $this->getJson("/api/seasons/{$season->id}/standings")->assertOk()
        ->assertJsonPath('season.id', $season->id)
        ->assertJsonStructure(['season', 'divisions', 'standings']);
});

it('reads and marks notifications for the signed-in user only', function () {
    $mine = (string) Str::uuid();
    $theirs = (string) Str::uuid();
    $this->shooterUser->notifications()->create(['id' => $mine, 'type' => 'App\\Notifications\\Test', 'data' => ['m' => 1]]);
    $this->md->notifications()->create(['id' => $theirs, 'type' => 'App\\Notifications\\Test', 'data' => ['m' => 2]]);

    $this->actingAs($this->shooterUser)->getJson('/api/notifications')->assertOk()
        ->assertJsonPath('unread_count', 1)->assertJsonCount(1, 'notifications');

    $this->actingAs($this->shooterUser)->postJson("/api/notifications/{$theirs}/read")->assertOk();
    expect($this->md->unreadNotifications()->count())->toBe(1);

    $this->actingAs($this->shooterUser)->postJson("/api/notifications/{$mine}/read")->assertOk();
    $this->md->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'App\\Notifications\\Test', 'data' => []]);
    $this->actingAs($this->shooterUser)->postJson('/api/notifications/read-all')->assertOk();

    expect($this->shooterUser->unreadNotifications()->count())->toBe(0)
        ->and($this->md->unreadNotifications()->count())->toBe(2);
});

it('subscribes and unsubscribes a push endpoint', function () {
    $endpoint = 'https://push.example.test/abc123';
    $this->actingAs($this->shooterUser)->postJson('/api/push/subscribe', [
        'endpoint' => $endpoint, 'keys' => ['p256dh' => 'pub', 'auth' => 'secret'],
    ])->assertOk();
    expect(PushSubscription::where('user_id', $this->shooterUser->id)->count())->toBe(1);

    $this->actingAs($this->md)->deleteJson('/api/push/unsubscribe', ['endpoint' => $endpoint])->assertOk();
    expect(PushSubscription::where('endpoint', $endpoint)->exists())->toBeTrue();

    $this->actingAs($this->shooterUser)->deleteJson('/api/push/unsubscribe', ['endpoint' => $endpoint])->assertOk();
    expect(PushSubscription::where('endpoint', $endpoint)->exists())->toBeFalse();
});
