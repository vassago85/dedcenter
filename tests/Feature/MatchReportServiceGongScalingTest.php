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
use App\Services\MatchReportService;

/*
|--------------------------------------------------------------------------
| Per-gong multiplier surfacing in MatchReportService payload
|--------------------------------------------------------------------------
| Royal Flush stages have five gongs whose nominal point value scales
| 1.0 / 1.25 / 1.5 / 1.75 / 2.0 across the line — the smallest gong
| (last) is worth twice the largest (first), and the whole strip is then
| multiplied by 1/100 of the distance. So a 500m stage produces
| 5.0 / 6.25 / 7.5 / 8.75 / 10.0 per gong.
|
| The reports used to bury this scaling: the per-stage gong row was just
| five identical coloured dots with no point indication, so a viewer
| couldn't tell whether the green dot in slot #5 was worth 5pt or 10pt.
| MatchReportService now exposes both the raw `multiplier` (1.0…2.0)
| and the calculated `value` (mult × distance/100) on every gong in the
| `stages[].gongs[]` payload, and the four report views (mobile share,
| phone PDF, A4 PDF, email) render `value` beneath each dot.
|
| This test locks both the math and the payload shape so nobody quietly
| drops the keys later.
*/

it('exposes the Royal Flush 1/1.25/1.5/1.75/2 per-gong scaling on a 500m stage', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $org = Organization::create([
        'name' => 'Test Club',
        'slug' => 'rf-gong-scaling',
        'type' => 'club',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $match = ShootingMatch::factory()->create([
        'created_by' => $owner->id,
        'organization_id' => $org->id,
        'scoring_type' => 'standard',
        'royal_flush_enabled' => true,
        'status' => MatchStatus::Completed,
    ]);

    $ts = TargetSet::create([
        'match_id' => $match->id,
        'label' => '500m',
        'distance_meters' => 500,
        'distance_multiplier' => 5.0,
        'sort_order' => 1,
    ]);
    $multipliers = [1.0, 1.25, 1.5, 1.75, 2.0];
    $gongs = [];
    foreach ($multipliers as $i => $m) {
        $gongs[] = Gong::create([
            'target_set_id' => $ts->id,
            'number' => $i + 1,
            'label' => 'G'.($i + 1),
            'multiplier' => (string) $m,
        ]);
    }

    $squad = Squad::create(['match_id' => $match->id, 'name' => 'A']);
    $shooter = Shooter::create(['name' => 'Pat', 'squad_id' => $squad->id, 'user_id' => $owner->id, 'status' => 'active']);

    // Hit gong #1 (worth 5.0) and gong #5 (worth 10.0); leave 2/3/4 unscored
    // so the test also covers the no-shot path's payload shape.
    Score::create(['shooter_id' => $shooter->id, 'gong_id' => $gongs[0]->id, 'is_hit' => true, 'recorded_at' => now()]);
    Score::create(['shooter_id' => $shooter->id, 'gong_id' => $gongs[4]->id, 'is_hit' => true, 'recorded_at' => now()]);

    $report = (new MatchReportService)->generateReport($match, $shooter);

    $stage = $report['stages'][0];
    expect($stage['gongs'])->toHaveCount(5);

    // Per-gong multiplier is the raw 1.0…2.0 RF scaling.
    expect(array_column($stage['gongs'], 'multiplier'))->toEqual([1.0, 1.25, 1.5, 1.75, 2.0]);

    // `value` is the gong's nominal worth (mult × distance/100). With
    // distance_multiplier = 5.0 the strip should be 5.0/6.25/7.5/8.75/10.0.
    expect(array_column($stage['gongs'], 'value'))->toEqual([5.0, 6.25, 7.5, 8.75, 10.0]);

    // Result + earned-points cross-check: hits earn the value, miss/no-shot earn 0.
    expect($stage['gongs'][0]['result'])->toBe('hit');
    expect($stage['gongs'][0]['points'])->toBe(5.0);
    expect($stage['gongs'][4]['result'])->toBe('hit');
    expect($stage['gongs'][4]['points'])->toBe(10.0);
    expect($stage['gongs'][1]['result'])->toBe('no_shot');
    expect($stage['gongs'][1]['points'])->toBe(0);

    // Stage total = sum of the two hit values (5 + 10 = 15).
    expect($stage['score'])->toBe(15.0);
});

it('renders per-gong values in the mobile share view for non-PRS matches', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $org = Organization::create([
        'name' => 'Test Club',
        'slug' => 'rf-gong-scaling-view',
        'type' => 'club',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $match = ShootingMatch::factory()->create([
        'created_by' => $owner->id,
        'organization_id' => $org->id,
        'scoring_type' => 'standard',
        'royal_flush_enabled' => true,
        'status' => MatchStatus::Completed,
    ]);

    $ts = TargetSet::create([
        'match_id' => $match->id,
        'label' => '500m',
        'distance_meters' => 500,
        'distance_multiplier' => 5.0,
        'sort_order' => 1,
    ]);
    foreach ([1.0, 1.25, 1.5, 1.75, 2.0] as $i => $m) {
        Gong::create(['target_set_id' => $ts->id, 'number' => $i + 1, 'label' => 'G'.($i + 1), 'multiplier' => (string) $m]);
    }

    $squad = Squad::create(['match_id' => $match->id, 'name' => 'A']);
    $shooter = Shooter::create(['name' => 'Pat', 'squad_id' => $squad->id, 'user_id' => $owner->id, 'status' => 'active']);
    foreach ($ts->gongs()->orderBy('number')->get() as $g) {
        if (in_array($g->number, [1, 5])) {
            Score::create(['shooter_id' => $shooter->id, 'gong_id' => $g->id, 'is_hit' => true, 'recorded_at' => now()]);
        }
    }

    $res = $this->actingAs($owner)->get(route('matches.my-report', $match));
    $res->assertOk();
    // The hero gong values 6.25 and 8.75 are unique enough to assert
    // unambiguously that the per-gong value strip rendered (they can only
    // come from the 1.25 / 1.75 RF multipliers × 5.0 distance scale).
    $body = $res->getContent();
    expect($body)->toContain('6.25');
    expect($body)->toContain('8.75');
});
