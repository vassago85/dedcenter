<?php

use App\Enums\MatchStatus;
use App\Enums\PrsShotResult;
use App\Models\Gong;
use App\Models\MatchRegistration;
use App\Models\Organization;
use App\Models\PrsShotScore;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use App\Services\MatchDashboardService;
use Laravel\Sanctum\Sanctum;
use Livewire\Volt\Volt;

function lockFixture(MatchStatus $status, array $match = []): array
{
    $owner = User::factory()->create();
    $org = Organization::factory()->create();
    $org->admins()->attach($owner, ['is_owner' => true]);

    $shootingMatch = ShootingMatch::factory()->create(array_merge([
        'organization_id' => $org->id,
        'created_by' => $owner->id,
        'status' => $status,
        'scoring_type' => 'standard',
        'scores_published' => true,
        'team_event' => false,
    ], $match));

    $squadA = Squad::factory()->create(['match_id' => $shootingMatch->id, 'name' => 'Squad 1', 'sort_order' => 1]);
    $squadB = Squad::factory()->create(['match_id' => $shootingMatch->id, 'name' => 'Squad 2', 'sort_order' => 2]);
    $user = User::factory()->create(['name' => 'Alex Shooter']);
    $shooter = Shooter::factory()->create([
        'squad_id' => $squadA->id,
        'user_id' => $user->id,
        'name' => 'Alex Shooter — 6.5 CM',
        'sort_order' => 1,
        'status' => 'active',
    ]);
    MatchRegistration::factory()->confirmed()->create([
        'match_id' => $shootingMatch->id,
        'user_id' => $user->id,
        'caliber' => null,
    ]);

    return [$org, $owner, $shootingMatch, $squadA, $squadB, $shooter];
}

function rosterSnapshot(ShootingMatch $match, Shooter $shooter): array
{
    return [
        'squads' => $match->squads()->count(),
        'shooters' => Shooter::query()->whereHas('squad', fn ($q) => $q->where('match_id', $match->id))->count(),
        'squad_id' => $shooter->fresh()->squad_id,
        'sort_order' => $shooter->fresh()->sort_order,
        'status' => $shooter->fresh()->status,
    ];
}

it('refuses bulk squad edits from Ready onward and still allows them while squadding is open', function (MatchStatus $status) {
    [$org, $owner, $match, $squadA, , $shooter] = lockFixture($status);
    $empty = Squad::factory()->create(['match_id' => $match->id, 'name' => 'Spare', 'sort_order' => 3]);
    $before = rosterSnapshot($match, $shooter);

    $this->actingAs($owner);
    $page = Volt::test('org.matches.squadding', ['organization' => $org, 'match' => $match]);

    foreach (['autoSetupRelays', 'autoSquad', 'randomizeRelays', 'autoTeam'] as $method) {
        $page->call($method);
    }
    $page->call('deleteShooter', $shooter->id);
    $page->call('deleteSquad', $empty->id);
    $page->call('removeFromRelay', $shooter->id);

    expect(rosterSnapshot($match, $shooter))->toBe($before)
        ->and($empty->fresh())->not->toBeNull();
})->with([
    MatchStatus::Ready,
    MatchStatus::Active,
    MatchStatus::Completed,
]);

it('still deletes a shooter while squadding is open', function () {
    [$org, $owner, $match, , , $shooter] = lockFixture(MatchStatus::SquaddingOpen);

    $this->actingAs($owner);
    Volt::test('org.matches.squadding', ['organization' => $org, 'match' => $match])
        ->call('deleteShooter', $shooter->id);

    expect(Shooter::find($shooter->id))->toBeNull();
});

it('moves a shooter while the match is Ready or Active', function (MatchStatus $status) {
    [$org, $owner, $match, , $squadB, $shooter] = lockFixture($status);

    $this->actingAs($owner);
    Volt::test('org.matches.squadding', ['organization' => $org, 'match' => $match])
        ->call('moveShooter', $shooter->id, $squadB->id);

    expect($shooter->fresh()->squad_id)->toBe($squadB->id);
})->with([MatchStatus::Ready, MatchStatus::Active]);

it('reopen brings individual moves back and leaves randomize locked', function () {
    [$org, $owner, $match, $squadA, $squadB, $shooter] = lockFixture(MatchStatus::Completed);

    $this->actingAs($owner);
    $page = Volt::test('org.matches.squadding', ['organization' => $org, 'match' => $match]);

    $page->call('moveShooter', $shooter->id, $squadB->id);
    expect($shooter->fresh()->squad_id)->toBe($squadA->id);

    $page->call('reopenMatch');
    expect($match->fresh()->status)->toBe(MatchStatus::Active);

    $page->call('moveShooter', $shooter->id, $squadB->id);
    expect($shooter->fresh()->squad_id)->toBe($squadB->id);

    $page->call('randomizeRelays');
    expect($shooter->fresh()->squad_id)->toBe($squadB->id);
});

it('refuses the same bulk delete on the admin squadding page', function () {
    [, $owner, $match, , , $shooter] = lockFixture(MatchStatus::Ready);

    $this->actingAs($owner);
    Volt::test('admin.matches.squadding', ['match' => $match])
        ->call('deleteShooter', $shooter->id);

    expect(Shooter::find($shooter->id))->not->toBeNull();
});

it('refuses a completed tablet move and roster push, and still applies a Ready push', function () {
    [, $owner, $completed, $squadA, $squadB, $shooter] = lockFixture(MatchStatus::Completed);

    Sanctum::actingAs($owner, ['*']);
    $this->patchJson("/api/matches/{$completed->id}/shooters/{$shooter->id}/squad", [
        'squad_id' => $squadB->id,
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This match is completed. Reopen it from Scoring before moving shooters.');

    expect($shooter->fresh()->squad_id)->toBe($squadA->id);

    Sanctum::actingAs($owner, ['*']);
    $this->postJson("/api/matches/{$completed->id}/roster", [
        'shooters' => [[
            'id' => $shooter->id,
            'squad_id' => $squadB->id,
            'caliber' => '308 Win',
        ]],
    ])->assertStatus(422);

    expect($shooter->fresh()->squad_id)->toBe($squadA->id)
        ->and($shooter->fresh()->name)->toBe('Alex Shooter — 6.5 CM');

    [, $readyOwner, $ready, $readyA, $readyB, $readyShooter] = lockFixture(MatchStatus::Ready);

    Sanctum::actingAs($readyOwner, ['*']);
    $this->postJson("/api/matches/{$ready->id}/roster", [
        'shooters' => [[
            'id' => $readyShooter->id,
            'squad_id' => $readyB->id,
        ]],
    ])->assertOk();

    expect($readyShooter->fresh()->squad_id)->toBe($readyB->id)
        ->and($readyShooter->fresh()->squad_id)->not->toBe($readyA->id);
});

it('does not warn about teams on a relay whose shooters are already squadded', function () {
    [, , $match] = lockFixture(MatchStatus::Ready);

    $dashboard = (new MatchDashboardService)->build($match->fresh());

    expect($dashboard['status_label'])->toBe('Ready')
        ->and($dashboard['scores_line']['label'])->toBe('No scores yet')
        ->and($dashboard['scores_line']['key'])->toBe('none')
        ->and($dashboard['not_in_squad_count'])->toBe(0)
        ->and($dashboard['no_team_count'])->toBe(0);
});

it('warns when a confirmed shooter was never placed, and separately when a team match has no team', function () {
    [$org, $owner, $match, $squadA] = lockFixture(MatchStatus::Ready, [
        'team_event' => true,
    ]);
    $waiting = User::factory()->create();
    MatchRegistration::factory()->confirmed()->create([
        'match_id' => $match->id,
        'user_id' => $waiting->id,
    ]);
    Shooter::factory()->create([
        'squad_id' => $squadA->id,
        'name' => 'Unteamed',
        'team_id' => null,
        'sort_order' => 2,
    ]);

    $dashboard = (new MatchDashboardService)->build($match->fresh());

    expect($dashboard['not_in_squad_count'])->toBe(1)
        ->and($dashboard['no_team_count'])->toBe(2);

    $this->actingAs($owner);
    $html = Volt::test('org.matches.hub', ['organization' => $org, 'match' => $match])->html();

    expect($html)->toContain('1 shooter is not in a squad')
        ->and($html)->toContain('2 shooters have no team')
        ->and($html)->not->toContain('not assigned to a squad or team');
});

it('names the scores line from shots, the publish switch, and completion', function (MatchStatus $status, bool $published, string $label, string $key) {
    [, , $match, $squadA, , $shooter] = lockFixture($status, ['scores_published' => $published]);
    $stage = TargetSet::factory()->create(['match_id' => $match->id]);
    $gong = Gong::factory()->create(['target_set_id' => $stage->id, 'number' => 1]);
    Score::factory()->hit()->create(['shooter_id' => $shooter->id, 'gong_id' => $gong->id]);

    $line = (new MatchDashboardService)->build($match->fresh())['scores_line'];

    expect($line['label'])->toBe($label)
        ->and($line['key'])->toBe($key);
})->with([
    'published final' => [MatchStatus::Completed, true, 'Published', 'published'],
    'hidden final' => [MatchStatus::Completed, false, 'Hidden', 'hidden'],
    'live match' => [MatchStatus::Active, true, 'Live', 'live'],
]);

it('counts a PRS shot even when the relay scores table is empty', function () {
    [, , $match, , , $shooter] = lockFixture(MatchStatus::Active, [
        'scoring_type' => 'prs',
        'scores_published' => true,
    ]);
    $stage = TargetSet::factory()->create(['match_id' => $match->id]);
    PrsShotScore::create([
        'match_id' => $match->id,
        'stage_id' => $stage->id,
        'shooter_id' => $shooter->id,
        'shot_number' => 1,
        'result' => PrsShotResult::Hit,
    ]);

    $line = (new MatchDashboardService)->build($match->fresh())['scores_line'];

    expect($line['label'])->toBe('Live');
});

it('shows the cartridge stored on the shooter name when registration caliber is blank', function () {
    [$org, $owner, $match] = lockFixture(MatchStatus::SquaddingOpen);

    $this->actingAs($owner);
    $html = Volt::test('org.matches.squadding', ['organization' => $org, 'match' => $match])->html();

    expect($html)->toContain('value="6.5 CM"');
});

it('shows the squad lock on a Ready match and keeps the randomize button from submitting', function () {
    [$org, $owner, $match] = lockFixture(MatchStatus::Ready);

    $this->actingAs($owner);
    $html = Volt::test('org.matches.squadding', ['organization' => $org, 'match' => $match])->html();

    expect($html)->toContain('Squads are locked. Step the match back to Squadding Open to randomize, auto-setup, or delete.')
        ->and($html)->toContain('You can still move a shooter between squads.');
});
