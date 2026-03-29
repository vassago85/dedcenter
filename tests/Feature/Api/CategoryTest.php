<?php

use App\Models\Gong;
use App\Models\MatchCategory;
use App\Models\MatchDivision;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->match = ShootingMatch::factory()->active()->create();
    $this->squad = Squad::factory()->create(['match_id' => $this->match->id]);
    $this->ts = TargetSet::factory()->create(['match_id' => $this->match->id]);
    $this->gong = Gong::factory()->create(['target_set_id' => $this->ts->id, 'number' => 1, 'multiplier' => 2.00]);
});

// ── Category CRUD ──

test('can create a category for a match', function () {
    $cat = $this->match->categories()->create(['name' => 'Overall', 'slug' => 'overall', 'sort_order' => 1]);

    expect($cat)->toBeInstanceOf(MatchCategory::class);
    expect($cat->name)->toBe('Overall');
    expect($cat->slug)->toBe('overall');
    expect($cat->match_id)->toBe($this->match->id);
});

test('match can have multiple categories', function () {
    $this->match->categories()->create(['name' => 'Overall', 'slug' => 'overall', 'sort_order' => 1]);
    $this->match->categories()->create(['name' => 'Ladies', 'slug' => 'ladies', 'sort_order' => 2]);
    $this->match->categories()->create(['name' => 'Junior', 'slug' => 'junior', 'sort_order' => 3]);
    $this->match->categories()->create(['name' => 'Senior', 'slug' => 'senior', 'sort_order' => 4]);

    expect($this->match->categories()->count())->toBe(4);
});

test('deleting a match cascades to categories', function () {
    $this->match->categories()->create(['name' => 'Overall', 'slug' => 'overall', 'sort_order' => 1]);
    expect(MatchCategory::count())->toBe(1);

    $this->match->delete();

    expect(MatchCategory::count())->toBe(0);
});

test('deleting a category detaches shooters from pivot but does not delete shooters', function () {
    $cat = $this->match->categories()->create(['name' => 'Ladies', 'slug' => 'ladies', 'sort_order' => 1]);
    $shooter = Shooter::factory()->create(['squad_id' => $this->squad->id]);
    $shooter->categories()->attach($cat->id);

    expect($shooter->categories()->count())->toBe(1);

    $cat->delete();

    expect($shooter->fresh())->not->toBeNull();
    expect($shooter->categories()->count())->toBe(0);
});

// ── Shooter Multi-Select Assignment ──

test('shooter can belong to multiple categories', function () {
    $overall = $this->match->categories()->create(['name' => 'Overall', 'slug' => 'overall', 'sort_order' => 1]);
    $ladies = $this->match->categories()->create(['name' => 'Ladies', 'slug' => 'ladies', 'sort_order' => 2]);
    $junior = $this->match->categories()->create(['name' => 'Junior', 'slug' => 'junior', 'sort_order' => 3]);

    $shooter = Shooter::factory()->create(['squad_id' => $this->squad->id]);
    $shooter->categories()->sync([$overall->id, $ladies->id, $junior->id]);

    expect($shooter->categories()->count())->toBe(3);
    expect($shooter->categories->pluck('name')->sort()->values()->all())->toBe(['Junior', 'Ladies', 'Overall']);
});

test('shooter can have no categories', function () {
    $shooter = Shooter::factory()->create(['squad_id' => $this->squad->id]);

    expect($shooter->categories()->count())->toBe(0);
});

test('category has many shooters via pivot', function () {
    $overall = $this->match->categories()->create(['name' => 'Overall', 'slug' => 'overall', 'sort_order' => 1]);
    $s1 = Shooter::factory()->create(['squad_id' => $this->squad->id]);
    $s2 = Shooter::factory()->create(['squad_id' => $this->squad->id]);
    $s3 = Shooter::factory()->create(['squad_id' => $this->squad->id]);

    $overall->shooters()->sync([$s1->id, $s2->id, $s3->id]);

    expect($overall->shooters()->count())->toBe(3);
});

test('shooter can sync categories replacing old ones', function () {
    $overall = $this->match->categories()->create(['name' => 'Overall', 'slug' => 'overall', 'sort_order' => 1]);
    $ladies = $this->match->categories()->create(['name' => 'Ladies', 'slug' => 'ladies', 'sort_order' => 2]);
    $senior = $this->match->categories()->create(['name' => 'Senior', 'slug' => 'senior', 'sort_order' => 3]);

    $shooter = Shooter::factory()->create(['squad_id' => $this->squad->id]);
    $shooter->categories()->sync([$overall->id, $ladies->id]);
    expect($shooter->categories()->count())->toBe(2);

    $shooter->categories()->sync([$overall->id, $senior->id]);
    $shooter->refresh();
    expect($shooter->categories()->count())->toBe(2);
    expect($shooter->categories->pluck('name')->sort()->values()->all())->toBe(['Overall', 'Senior']);
});

// ── Divisions + Categories Independence ──

test('shooter can have both a division and multiple categories', function () {
    $div = $this->match->divisions()->create(['name' => 'Open', 'sort_order' => 1]);
    $overall = $this->match->categories()->create(['name' => 'Overall', 'slug' => 'overall', 'sort_order' => 1]);
    $ladies = $this->match->categories()->create(['name' => 'Ladies', 'slug' => 'ladies', 'sort_order' => 2]);

    $shooter = Shooter::factory()->create([
        'squad_id' => $this->squad->id,
        'match_division_id' => $div->id,
    ]);
    $shooter->categories()->sync([$overall->id, $ladies->id]);

    expect($shooter->division->name)->toBe('Open');
    expect($shooter->categories()->count())->toBe(2);
});

// ── Match API ──

test('match api returns categories', function () {
    $this->match->categories()->create(['name' => 'Overall', 'slug' => 'overall', 'sort_order' => 1]);
    $this->match->categories()->create(['name' => 'Ladies', 'slug' => 'ladies', 'sort_order' => 2]);

    $response = $this->getJson("/api/matches/{$this->match->id}");

    $response->assertOk();
    $categories = $response->json('data.categories');
    expect($categories)->toHaveCount(2);
    expect($categories[0]['name'])->toBe('Overall');
    expect($categories[0]['slug'])->toBe('overall');
});

test('match api returns shooter category ids', function () {
    $overall = $this->match->categories()->create(['name' => 'Overall', 'slug' => 'overall', 'sort_order' => 1]);
    $ladies = $this->match->categories()->create(['name' => 'Ladies', 'slug' => 'ladies', 'sort_order' => 2]);

    $shooter = Shooter::factory()->create(['squad_id' => $this->squad->id]);
    $shooter->categories()->sync([$overall->id, $ladies->id]);

    $response = $this->getJson("/api/matches/{$this->match->id}");

    $response->assertOk();
    $shooters = collect($response->json('data.squads'))->flatMap(fn ($s) => $s['shooters']);
    $found = $shooters->firstWhere('id', $shooter->id);
    expect($found['category_ids'])->toHaveCount(2);
    expect($found['category_ids'])->toContain($overall->id);
    expect($found['category_ids'])->toContain($ladies->id);
});

// ── Scoreboard API with Categories ──

test('scoreboard includes categories list in match object', function () {
    $this->match->categories()->create(['name' => 'Overall', 'slug' => 'overall', 'sort_order' => 1]);
    $this->match->categories()->create(['name' => 'Ladies', 'slug' => 'ladies', 'sort_order' => 2]);
    Shooter::factory()->create(['squad_id' => $this->squad->id]);

    $response = $this->getJson("/api/matches/{$this->match->id}/scoreboard");

    $response->assertOk();
    $cats = $response->json('match.categories');
    expect($cats)->toHaveCount(2);
    expect($cats[0]['name'])->toBe('Overall');
});

test('scoreboard filters by category', function () {
    $overall = $this->match->categories()->create(['name' => 'Overall', 'slug' => 'overall', 'sort_order' => 1]);
    $ladies = $this->match->categories()->create(['name' => 'Ladies', 'slug' => 'ladies', 'sort_order' => 2]);

    $alice = Shooter::factory()->create(['squad_id' => $this->squad->id, 'name' => 'Alice']);
    $bob = Shooter::factory()->create(['squad_id' => $this->squad->id, 'name' => 'Bob']);

    $alice->categories()->sync([$overall->id, $ladies->id]);
    $bob->categories()->sync([$overall->id]);

    Score::factory()->create(['shooter_id' => $alice->id, 'gong_id' => $this->gong->id, 'is_hit' => true]);
    Score::factory()->create(['shooter_id' => $bob->id, 'gong_id' => $this->gong->id, 'is_hit' => true]);

    $all = $this->getJson("/api/matches/{$this->match->id}/scoreboard")->json('leaderboard');
    expect($all)->toHaveCount(2);

    $ladiesOnly = $this->getJson("/api/matches/{$this->match->id}/scoreboard?category={$ladies->id}")->json('leaderboard');
    expect($ladiesOnly)->toHaveCount(1);
    expect($ladiesOnly[0]['name'])->toBe('Alice');
    expect($ladiesOnly[0]['rank'])->toBe(1);

    $overallOnly = $this->getJson("/api/matches/{$this->match->id}/scoreboard?category={$overall->id}")->json('leaderboard');
    expect($overallOnly)->toHaveCount(2);
});

// ── 2D Filtering: Division + Category ──

test('scoreboard filters by both division and category simultaneously', function () {
    $open = $this->match->divisions()->create(['name' => 'Open', 'sort_order' => 1]);
    $factory = $this->match->divisions()->create(['name' => 'Factory', 'sort_order' => 2]);
    $ladies = $this->match->categories()->create(['name' => 'Ladies', 'slug' => 'ladies', 'sort_order' => 1]);
    $overall = $this->match->categories()->create(['name' => 'Overall', 'slug' => 'overall', 'sort_order' => 2]);

    $alice = Shooter::factory()->create(['squad_id' => $this->squad->id, 'name' => 'Alice', 'match_division_id' => $open->id]);
    $bob = Shooter::factory()->create(['squad_id' => $this->squad->id, 'name' => 'Bob', 'match_division_id' => $factory->id]);
    $carol = Shooter::factory()->create(['squad_id' => $this->squad->id, 'name' => 'Carol', 'match_division_id' => $open->id]);

    $alice->categories()->sync([$overall->id, $ladies->id]);
    $bob->categories()->sync([$overall->id, $ladies->id]);
    $carol->categories()->sync([$overall->id]);

    Score::factory()->create(['shooter_id' => $alice->id, 'gong_id' => $this->gong->id, 'is_hit' => true]);
    Score::factory()->create(['shooter_id' => $bob->id, 'gong_id' => $this->gong->id, 'is_hit' => true]);
    Score::factory()->create(['shooter_id' => $carol->id, 'gong_id' => $this->gong->id, 'is_hit' => true]);

    $openLadies = $this->getJson("/api/matches/{$this->match->id}/scoreboard?division={$open->id}&category={$ladies->id}")->json('leaderboard');
    expect($openLadies)->toHaveCount(1);
    expect($openLadies[0]['name'])->toBe('Alice');

    $factoryLadies = $this->getJson("/api/matches/{$this->match->id}/scoreboard?division={$factory->id}&category={$ladies->id}")->json('leaderboard');
    expect($factoryLadies)->toHaveCount(1);
    expect($factoryLadies[0]['name'])->toBe('Bob');

    $openAll = $this->getJson("/api/matches/{$this->match->id}/scoreboard?division={$open->id}")->json('leaderboard');
    expect($openAll)->toHaveCount(2);

    $ladiesAll = $this->getJson("/api/matches/{$this->match->id}/scoreboard?category={$ladies->id}")->json('leaderboard');
    expect($ladiesAll)->toHaveCount(2);
});

// ── PRS Scoreboard with Categories ──

test('prs scoreboard filters by category', function () {
    $prsMatch = ShootingMatch::factory()->active()->prs()->create();
    $squad = Squad::factory()->create(['match_id' => $prsMatch->id]);
    $ts = TargetSet::factory()->create(['match_id' => $prsMatch->id]);
    $gong = Gong::factory()->create(['target_set_id' => $ts->id, 'number' => 1, 'multiplier' => 1.00]);

    $junior = $prsMatch->categories()->create(['name' => 'Junior', 'slug' => 'junior', 'sort_order' => 1]);
    $overall = $prsMatch->categories()->create(['name' => 'Overall', 'slug' => 'overall', 'sort_order' => 2]);

    $alice = Shooter::factory()->create(['squad_id' => $squad->id, 'name' => 'Alice']);
    $bob = Shooter::factory()->create(['squad_id' => $squad->id, 'name' => 'Bob']);

    $alice->categories()->sync([$overall->id, $junior->id]);
    $bob->categories()->sync([$overall->id]);

    Score::factory()->create(['shooter_id' => $alice->id, 'gong_id' => $gong->id, 'is_hit' => true]);
    Score::factory()->create(['shooter_id' => $bob->id, 'gong_id' => $gong->id, 'is_hit' => true]);

    $juniorOnly = $this->getJson("/api/matches/{$prsMatch->id}/scoreboard?category={$junior->id}")->json('leaderboard');
    expect($juniorOnly)->toHaveCount(1);
    expect($juniorOnly[0]['name'])->toBe('Alice');
});

test('prs scoreboard filters by both division and category', function () {
    $prsMatch = ShootingMatch::factory()->active()->prs()->create();
    $squad = Squad::factory()->create(['match_id' => $prsMatch->id]);
    $ts = TargetSet::factory()->create(['match_id' => $prsMatch->id]);
    $gong = Gong::factory()->create(['target_set_id' => $ts->id, 'number' => 1, 'multiplier' => 1.00]);

    $open = $prsMatch->divisions()->create(['name' => 'Open', 'sort_order' => 1]);
    $factory = $prsMatch->divisions()->create(['name' => 'Factory', 'sort_order' => 2]);
    $ladies = $prsMatch->categories()->create(['name' => 'Ladies', 'slug' => 'ladies', 'sort_order' => 1]);

    $alice = Shooter::factory()->create(['squad_id' => $squad->id, 'name' => 'Alice', 'match_division_id' => $open->id]);
    $bob = Shooter::factory()->create(['squad_id' => $squad->id, 'name' => 'Bob', 'match_division_id' => $factory->id]);

    $alice->categories()->sync([$ladies->id]);
    $bob->categories()->sync([$ladies->id]);

    Score::factory()->create(['shooter_id' => $alice->id, 'gong_id' => $gong->id, 'is_hit' => true]);
    Score::factory()->create(['shooter_id' => $bob->id, 'gong_id' => $gong->id, 'is_hit' => true]);

    $openLadies = $this->getJson("/api/matches/{$prsMatch->id}/scoreboard?division={$open->id}&category={$ladies->id}")->json('leaderboard');
    expect($openLadies)->toHaveCount(1);
    expect($openLadies[0]['name'])->toBe('Alice');
});

// ── Live Scoreboard with Categories ──

test('live scoreboard shows category filter tabs', function () {
    $this->match->categories()->create(['name' => 'Ladies', 'slug' => 'ladies', 'sort_order' => 1]);
    $this->match->categories()->create(['name' => 'Junior', 'slug' => 'junior', 'sort_order' => 2]);

    $response = $this->get("/live/{$this->match->id}");

    $response->assertOk();
    $response->assertSee('Ladies');
    $response->assertSee('Junior');
});

test('scoreboard without categories still works', function () {
    $shooter = Shooter::factory()->create(['squad_id' => $this->squad->id, 'name' => 'Charlie']);
    Score::factory()->create(['shooter_id' => $shooter->id, 'gong_id' => $this->gong->id, 'is_hit' => true]);

    $response = $this->getJson("/api/matches/{$this->match->id}/scoreboard");

    $response->assertOk();
    $entry = $response->json('leaderboard.0');
    expect($entry['name'])->toBe('Charlie');
});
