<?php

use App\Models\Gong;
use App\Models\MatchDivision;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->match = ShootingMatch::factory()->active()->create();
    $this->squad = Squad::factory()->create(['match_id' => $this->match->id]);
    $this->ts = TargetSet::factory()->create(['match_id' => $this->match->id]);
    $this->gong = Gong::factory()->create(['target_set_id' => $this->ts->id, 'number' => 1, 'multiplier' => 2.00]);
});

// ── Division CRUD ──

test('can create a division for a match', function () {
    $div = $this->match->divisions()->create(['name' => 'Open', 'sort_order' => 1]);

    expect($div)->toBeInstanceOf(MatchDivision::class);
    expect($div->name)->toBe('Open');
    expect($div->match_id)->toBe($this->match->id);
});

test('match can have multiple divisions', function () {
    $this->match->divisions()->create(['name' => 'Minor', 'sort_order' => 1]);
    $this->match->divisions()->create(['name' => 'Major', 'sort_order' => 2]);

    expect($this->match->divisions()->count())->toBe(2);
});

test('minor/major preset creates two divisions', function () {
    $this->match->divisions()->create(['name' => 'Minor (.30 cal and below)', 'sort_order' => 1]);
    $this->match->divisions()->create(['name' => 'Major (above .30 cal)', 'sort_order' => 2]);

    $divs = $this->match->divisions()->orderBy('sort_order')->get();
    expect($divs)->toHaveCount(2);
    expect($divs[0]->name)->toBe('Minor (.30 cal and below)');
    expect($divs[1]->name)->toBe('Major (above .30 cal)');
});

test('deleting a division sets shooter FK to null', function () {
    $div = $this->match->divisions()->create(['name' => 'Open', 'sort_order' => 1]);
    $shooter = Shooter::factory()->create(['squad_id' => $this->squad->id, 'match_division_id' => $div->id]);

    expect($shooter->fresh()->match_division_id)->toBe($div->id);

    $div->delete();

    expect($shooter->fresh()->match_division_id)->toBeNull();
});

test('deleting a match cascades to divisions', function () {
    $this->match->divisions()->create(['name' => 'Open', 'sort_order' => 1]);
    expect(MatchDivision::count())->toBe(1);

    $this->match->delete();

    expect(MatchDivision::count())->toBe(0);
});

// ── Shooter Assignment ──

test('shooter can be assigned to a division', function () {
    $div = $this->match->divisions()->create(['name' => 'Minor', 'sort_order' => 1]);
    $shooter = Shooter::factory()->create(['squad_id' => $this->squad->id, 'match_division_id' => $div->id]);

    expect($shooter->division)->toBeInstanceOf(MatchDivision::class);
    expect($shooter->division->name)->toBe('Minor');
});

test('shooter can have no division', function () {
    $shooter = Shooter::factory()->create(['squad_id' => $this->squad->id]);

    expect($shooter->match_division_id)->toBeNull();
    expect($shooter->division)->toBeNull();
});

test('division has many shooters', function () {
    $div = $this->match->divisions()->create(['name' => 'Open', 'sort_order' => 1]);
    Shooter::factory()->count(3)->create(['squad_id' => $this->squad->id, 'match_division_id' => $div->id]);

    expect($div->shooters()->count())->toBe(3);
});

// ── Match API ──

test('match api returns divisions', function () {
    $this->match->divisions()->create(['name' => 'Minor', 'sort_order' => 1]);
    $this->match->divisions()->create(['name' => 'Major', 'sort_order' => 2]);

    $response = $this->getJson("/api/matches/{$this->match->id}");

    $response->assertOk();
    $divisions = $response->json('data.divisions');
    expect($divisions)->toHaveCount(2);
    expect($divisions[0]['name'])->toBe('Minor');
    expect($divisions[1]['name'])->toBe('Major');
});

test('match api returns shooter division info', function () {
    $div = $this->match->divisions()->create(['name' => 'Minor', 'sort_order' => 1]);
    $shooter = Shooter::factory()->create(['squad_id' => $this->squad->id, 'match_division_id' => $div->id]);

    $response = $this->getJson("/api/matches/{$this->match->id}");

    $response->assertOk();
    $shooters = collect($response->json('data.squads'))->flatMap(fn ($s) => $s['shooters']);
    $found = $shooters->firstWhere('id', $shooter->id);
    expect($found['division_id'])->toBe($div->id);
    expect($found['division'])->toBe('Minor');
});

// ── Scoreboard API with Divisions ──

test('scoreboard includes division info in leaderboard entries', function () {
    $div = $this->match->divisions()->create(['name' => 'Open', 'sort_order' => 1]);
    $shooter = Shooter::factory()->create(['squad_id' => $this->squad->id, 'match_division_id' => $div->id, 'name' => 'Alice']);
    Score::factory()->create(['shooter_id' => $shooter->id, 'gong_id' => $this->gong->id, 'is_hit' => true]);

    $response = $this->getJson("/api/matches/{$this->match->id}/scoreboard");

    $response->assertOk();
    $entry = collect($response->json('leaderboard'))->firstWhere('name', 'Alice');
    expect($entry['division_id'])->toBe($div->id);
    expect($entry['division'])->toBe('Open');
});

test('scoreboard includes divisions list in match object', function () {
    $this->match->divisions()->create(['name' => 'Minor', 'sort_order' => 1]);
    $this->match->divisions()->create(['name' => 'Major', 'sort_order' => 2]);
    Shooter::factory()->create(['squad_id' => $this->squad->id]);

    $response = $this->getJson("/api/matches/{$this->match->id}/scoreboard");

    $response->assertOk();
    $matchDivisions = $response->json('match.divisions');
    expect($matchDivisions)->toHaveCount(2);
});

test('scoreboard filters by division', function () {
    $minor = $this->match->divisions()->create(['name' => 'Minor', 'sort_order' => 1]);
    $major = $this->match->divisions()->create(['name' => 'Major', 'sort_order' => 2]);

    $alice = Shooter::factory()->create(['squad_id' => $this->squad->id, 'name' => 'Alice', 'match_division_id' => $minor->id]);
    $bob = Shooter::factory()->create(['squad_id' => $this->squad->id, 'name' => 'Bob', 'match_division_id' => $major->id]);

    Score::factory()->create(['shooter_id' => $alice->id, 'gong_id' => $this->gong->id, 'is_hit' => true]);
    Score::factory()->create(['shooter_id' => $bob->id, 'gong_id' => $this->gong->id, 'is_hit' => true]);

    $all = $this->getJson("/api/matches/{$this->match->id}/scoreboard")->json('leaderboard');
    expect($all)->toHaveCount(2);

    $minorOnly = $this->getJson("/api/matches/{$this->match->id}/scoreboard?division={$minor->id}")->json('leaderboard');
    expect($minorOnly)->toHaveCount(1);
    expect($minorOnly[0]['name'])->toBe('Alice');
    expect($minorOnly[0]['rank'])->toBe(1);

    $majorOnly = $this->getJson("/api/matches/{$this->match->id}/scoreboard?division={$major->id}")->json('leaderboard');
    expect($majorOnly)->toHaveCount(1);
    expect($majorOnly[0]['name'])->toBe('Bob');
    expect($majorOnly[0]['rank'])->toBe(1);
});

test('scoreboard without divisions still works', function () {
    $shooter = Shooter::factory()->create(['squad_id' => $this->squad->id, 'name' => 'Charlie']);
    Score::factory()->create(['shooter_id' => $shooter->id, 'gong_id' => $this->gong->id, 'is_hit' => true]);

    $response = $this->getJson("/api/matches/{$this->match->id}/scoreboard");

    $response->assertOk();
    $entry = $response->json('leaderboard.0');
    expect($entry['name'])->toBe('Charlie');
    expect($entry['division'])->toBeNull();
    expect($entry['division_id'])->toBeNull();
});

// ── PRS Scoreboard with Divisions ──

test('prs scoreboard filters by division', function () {
    $prsMatch = ShootingMatch::factory()->active()->prs()->create();
    $squad = Squad::factory()->create(['match_id' => $prsMatch->id]);
    $ts = TargetSet::factory()->create(['match_id' => $prsMatch->id]);
    $gong = Gong::factory()->create(['target_set_id' => $ts->id, 'number' => 1, 'multiplier' => 1.00]);

    $minor = $prsMatch->divisions()->create(['name' => 'Minor', 'sort_order' => 1]);
    $major = $prsMatch->divisions()->create(['name' => 'Major', 'sort_order' => 2]);

    $alice = Shooter::factory()->create(['squad_id' => $squad->id, 'name' => 'Alice', 'match_division_id' => $minor->id]);
    $bob = Shooter::factory()->create(['squad_id' => $squad->id, 'name' => 'Bob', 'match_division_id' => $major->id]);

    Score::factory()->create(['shooter_id' => $alice->id, 'gong_id' => $gong->id, 'is_hit' => true]);
    Score::factory()->create(['shooter_id' => $bob->id, 'gong_id' => $gong->id, 'is_hit' => true]);

    $minorOnly = $this->getJson("/api/matches/{$prsMatch->id}/scoreboard?division={$minor->id}")->json('leaderboard');
    expect($minorOnly)->toHaveCount(1);
    expect($minorOnly[0]['name'])->toBe('Alice');
});

// ── Live Scoreboard Page ──

test('live scoreboard page loads without auth', function () {
    $response = $this->get("/live/{$this->match->id}");

    $response->assertOk();
});

test('live scoreboard page shows match name', function () {
    $this->match->update(['name' => 'Test Live Match']);

    $response = $this->get("/live/{$this->match->id}");

    $response->assertOk();
    $response->assertSee('Test Live Match');
});

test('live scoreboard shows division filter tabs', function () {
    $this->match->divisions()->create(['name' => 'Minor', 'sort_order' => 1]);
    $this->match->divisions()->create(['name' => 'Major', 'sort_order' => 2]);

    $response = $this->get("/live/{$this->match->id}");

    $response->assertOk();
    $response->assertSee('Minor');
    $response->assertSee('Major');
});
