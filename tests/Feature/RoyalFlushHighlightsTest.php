<?php

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\Organization;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use App\Services\RoyalFlushHighlightsService;

/**
 * Covers:
 *   - RoyalFlushHighlightsService returns correct counts + perfect-hand
 *     detection on a small handcrafted RF match.
 *   - Non-RF matches return the empty shape (has_any=false).
 *   - The <x-royal-flush-highlights /> panel renders on the org hub for
 *     a completed RF match — and DOESN'T render when the match isn't
 *     completed yet or isn't a Royal Flush.
 */

beforeEach(function () {
    // Platform owner — bypasses per-org ACL via User::isOwner() so the
    // org-scoped route middleware (EnsureOrgAdmin) lets us through.
    $this->owner = User::factory()->create(['role' => 'owner']);

    $this->org = Organization::create([
        'name' => 'Test Club',
        'slug' => 'test-club',
        'type' => 'club',
        'status' => 'active',
        'created_by' => $this->owner->id,
    ]);
});

/**
 * Mirrors makeRfMatch() in FullMatchReportDataTest but attaches the
 * match to an organization so we can hit /org/{slug}/matches/{id} in
 * the view-render tests below.
 */
function makeRfMatchWithOrg(User $owner, Organization $org, MatchStatus $status = MatchStatus::Completed): array
{
    $match = ShootingMatch::factory()->create([
        'created_by' => $owner->id,
        'organization_id' => $org->id,
        'scoring_type' => 'standard',
        'royal_flush_enabled' => true,
        'status' => $status,
    ]);

    $ts400 = TargetSet::create(['match_id' => $match->id, 'label' => '400m', 'distance_meters' => 400, 'distance_multiplier' => 4.0, 'sort_order' => 1]);
    $ts500 = TargetSet::create(['match_id' => $match->id, 'label' => '500m', 'distance_meters' => 500, 'distance_multiplier' => 5.0, 'sort_order' => 2]);

    $g400_1 = Gong::create(['target_set_id' => $ts400->id, 'number' => 1, 'label' => 'G1', 'multiplier' => '1.00']);
    $g400_2 = Gong::create(['target_set_id' => $ts400->id, 'number' => 2, 'label' => 'G2', 'multiplier' => '1.50']);
    $g500_1 = Gong::create(['target_set_id' => $ts500->id, 'number' => 1, 'label' => 'G1', 'multiplier' => '1.00']);
    $g500_2 = Gong::create(['target_set_id' => $ts500->id, 'number' => 2, 'label' => 'G2', 'multiplier' => '1.50']);

    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Alpha']);

    return [
        'match' => $match,
        'squad' => $squad,
        'gongs' => [
            '400' => [$g400_1, $g400_2],
            '500' => [$g500_1, $g500_2],
        ],
    ];
}

it('service returns per-distance counts and detects perfect hands', function () {
    $ctx = makeRfMatchWithOrg($this->owner, $this->org);

    $alice = Shooter::create(['name' => 'Alice A', 'squad_id' => $ctx['squad']->id, 'status' => 'active']);
    $bob = Shooter::create(['name' => 'Bob B', 'squad_id' => $ctx['squad']->id, 'status' => 'active']);

    foreach ($ctx['gongs']['400'] as $g) Score::create(['shooter_id' => $alice->id, 'gong_id' => $g->id, 'is_hit' => true, 'recorded_at' => now()]);
    foreach ($ctx['gongs']['500'] as $g) Score::create(['shooter_id' => $alice->id, 'gong_id' => $g->id, 'is_hit' => true, 'recorded_at' => now()]);

    foreach ($ctx['gongs']['400'] as $g) Score::create(['shooter_id' => $bob->id, 'gong_id' => $g->id, 'is_hit' => true, 'recorded_at' => now()]);
    Score::create(['shooter_id' => $bob->id, 'gong_id' => $ctx['gongs']['500'][0]->id, 'is_hit' => true, 'recorded_at' => now()]);
    Score::create(['shooter_id' => $bob->id, 'gong_id' => $ctx['gongs']['500'][1]->id, 'is_hit' => false, 'recorded_at' => now()]);

    $result = app(RoyalFlushHighlightsService::class)->build($ctx['match']);

    expect($result['has_any'])->toBeTrue()
        ->and($result['flushes_by_distance'])->toBe([400 => 2, 500 => 1])
        ->and($result['shooters_by_distance'][400])->toContain('Alice A', 'Bob B')
        ->and($result['shooters_by_distance'][500])->toBe(['Alice A'])
        ->and($result['perfect_hand_shooters'])->toBe(['Alice A'])
        ->and($result['total_flushes'])->toBe(3);
});

it('service excludes no-show shooters from flush counts', function () {
    $ctx = makeRfMatchWithOrg($this->owner, $this->org);

    $ghost = Shooter::create(['name' => 'Ghost G', 'squad_id' => $ctx['squad']->id, 'status' => 'no_show']);

    foreach ($ctx['gongs']['400'] as $g) Score::create(['shooter_id' => $ghost->id, 'gong_id' => $g->id, 'is_hit' => true, 'recorded_at' => now()]);
    foreach ($ctx['gongs']['500'] as $g) Score::create(['shooter_id' => $ghost->id, 'gong_id' => $g->id, 'is_hit' => true, 'recorded_at' => now()]);

    $result = app(RoyalFlushHighlightsService::class)->build($ctx['match']);

    expect($result['has_any'])->toBeFalse()
        ->and($result['perfect_hand_shooters'])->toBe([])
        ->and($result['total_flushes'])->toBe(0);
});

it('service returns empty shape for non-RF matches', function () {
    $match = ShootingMatch::factory()->create([
        'created_by' => $this->owner->id,
        'organization_id' => $this->org->id,
        'scoring_type' => 'standard',
        'royal_flush_enabled' => false,
        'status' => MatchStatus::Completed,
    ]);

    $result = app(RoyalFlushHighlightsService::class)->build($match);

    expect($result['has_any'])->toBeFalse()
        ->and($result['flushes_by_distance'])->toBe([])
        ->and($result['perfect_hand_shooters'])->toBe([])
        ->and($result['total_flushes'])->toBe(0);
});

it('strips " — caliber" suffixes from shooter display names', function () {
    $ctx = makeRfMatchWithOrg($this->owner, $this->org);

    $jd = Shooter::create(['name' => 'JD Smith — 6.5CM', 'squad_id' => $ctx['squad']->id, 'status' => 'active']);
    foreach ($ctx['gongs']['400'] as $g) Score::create(['shooter_id' => $jd->id, 'gong_id' => $g->id, 'is_hit' => true, 'recorded_at' => now()]);

    $result = app(RoyalFlushHighlightsService::class)->build($ctx['match']);

    expect($result['shooters_by_distance'][400])->toBe(['JD Smith']);
});

it('renders the highlights panel on the org hub for completed RF matches', function () {
    $ctx = makeRfMatchWithOrg($this->owner, $this->org, MatchStatus::Completed);

    $alice = Shooter::create(['name' => 'Alice A — 6.5CM', 'squad_id' => $ctx['squad']->id, 'status' => 'active']);
    foreach ($ctx['gongs']['400'] as $g) Score::create(['shooter_id' => $alice->id, 'gong_id' => $g->id, 'is_hit' => true, 'recorded_at' => now()]);
    foreach ($ctx['gongs']['500'] as $g) Score::create(['shooter_id' => $alice->id, 'gong_id' => $g->id, 'is_hit' => true, 'recorded_at' => now()]);

    $html = $this->actingAs($this->owner)
        ->get("/org/{$this->org->slug}/matches/{$ctx['match']->id}")
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Royal Flush Highlights')
        ->and($html)->toContain('Perfect Hand')
        ->and($html)->toContain('Alice A')
        ->and($html)->toContain('Full Match Report');
});

it('does NOT render the panel for RF matches that are still in progress', function () {
    $ctx = makeRfMatchWithOrg($this->owner, $this->org, MatchStatus::Active);

    $alice = Shooter::create(['name' => 'Alice A', 'squad_id' => $ctx['squad']->id, 'status' => 'active']);
    foreach ($ctx['gongs']['400'] as $g) Score::create(['shooter_id' => $alice->id, 'gong_id' => $g->id, 'is_hit' => true, 'recorded_at' => now()]);

    $html = $this->actingAs($this->owner)
        ->get("/org/{$this->org->slug}/matches/{$ctx['match']->id}")
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('Royal Flush Highlights');
});

it('does NOT render the panel for non-RF matches even when completed', function () {
    $match = ShootingMatch::factory()->create([
        'created_by' => $this->owner->id,
        'organization_id' => $this->org->id,
        'scoring_type' => 'standard',
        'royal_flush_enabled' => false,
        'status' => MatchStatus::Completed,
    ]);

    $html = $this->actingAs($this->owner)
        ->get("/org/{$this->org->slug}/matches/{$match->id}")
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('Royal Flush Highlights');
});
