<?php

use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\User;
use App\Services\Scoring\AlrhaSharedRifleValidator;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => 'owner']);
    $this->match = ShootingMatch::factory()->active()->alrha('varmint')->create([
        'created_by' => $this->owner->id,
    ]);

    $this->r1 = Squad::create(['match_id' => $this->match->id, 'name' => 'Relay 1', 'sort_order' => 1]);
    $this->r2 = Squad::create(['match_id' => $this->match->id, 'name' => 'Relay 2', 'sort_order' => 2]);
    $this->r3 = Squad::create(['match_id' => $this->match->id, 'name' => 'Relay 3', 'sort_order' => 3]);
});

it('flags rifle sharing between adjacent (overlapping) relays', function () {
    Shooter::factory()->create([
        'squad_id' => $this->r1->id, 'name' => 'Alice', 'shared_rifle_key' => 'RIFLE-A',
    ]);
    Shooter::factory()->create([
        'squad_id' => $this->r2->id, 'name' => 'Bob', 'shared_rifle_key' => 'RIFLE-A',
    ]);

    $validator = app(AlrhaSharedRifleValidator::class);
    $conflicts = $validator->findConflicts($this->match);

    expect($conflicts)->toHaveCount(1);
    expect($conflicts[0]['key'])->toBe('RIFLE-A');
    expect($conflicts[0]['shooters'])->toHaveCount(2);
});

it('allows rifle sharing between non-adjacent relays (R1 <-> R3)', function () {
    Shooter::factory()->create([
        'squad_id' => $this->r1->id, 'name' => 'Alice', 'shared_rifle_key' => 'RIFLE-A',
    ]);
    Shooter::factory()->create([
        'squad_id' => $this->r3->id, 'name' => 'Charlie', 'shared_rifle_key' => 'RIFLE-A',
    ]);

    $validator = app(AlrhaSharedRifleValidator::class);
    $conflicts = $validator->findConflicts($this->match);

    expect($conflicts)->toBe([]);
});

it('reports a conflict when a shooter is being moved into an adjacent relay', function () {
    $alice = Shooter::factory()->create([
        'squad_id' => $this->r1->id, 'name' => 'Alice', 'shared_rifle_key' => 'RIFLE-A',
    ]);

    $validator = app(AlrhaSharedRifleValidator::class);

    // Moving a new shooter into R2 should conflict with Alice in R1.
    $conflicts = $validator->findConflictsForShooter($this->match, 'RIFLE-A', $this->r2);
    expect($conflicts)->toHaveCount(1);
    expect($conflicts[0]['shooter_name'])->toBe('Alice');
    expect($conflicts[0]['squad_name'])->toBe('Relay 1');

    // Moving into R3 (non-adjacent) is fine.
    $conflictsR3 = $validator->findConflictsForShooter($this->match, 'RIFLE-A', $this->r3);
    expect($conflictsR3)->toBe([]);
});
