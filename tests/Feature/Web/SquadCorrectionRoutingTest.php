<?php

/**
 * Regression: the squad score-correction editor reads/writes the STANDARD
 * `scores` gong table. Reaching it for a PRS/ELR match rendered the wrong
 * tool and 500'd on real PRS data. Non-standard matches must bounce back to
 * squadding instead; standard matches still open the editor.
 */

use App\Models\Gong;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeSquadForMatch(ShootingMatch $match): Squad
{
    $stage = TargetSet::factory()->create(['match_id' => $match->id, 'label' => 'Stage 1', 'sort_order' => 1]);
    Gong::factory()->create(['target_set_id' => $stage->id, 'number' => 1, 'multiplier' => 1.0]);
    Gong::factory()->create(['target_set_id' => $stage->id, 'number' => 2, 'multiplier' => 1.0]);

    $squad = Squad::factory()->create(['match_id' => $match->id]);
    Shooter::factory()->create(['squad_id' => $squad->id, 'name' => 'Alpha']);

    return $squad;
}

test('admin squad correction redirects a PRS match back to squadding instead of 500ing', function () {
    $admin = User::factory()->admin()->create();
    $match = ShootingMatch::factory()->prs()->create(['created_by' => $admin->id]);
    $squad = makeSquadForMatch($match);

    $res = $this->actingAs($admin)->get("/admin/matches/{$match->id}/squads/{$squad->id}/correct");

    $res->assertRedirect(route('admin.matches.squadding', $match));
});

test('admin squad correction still opens for a standard match', function () {
    $admin = User::factory()->admin()->create();
    $match = ShootingMatch::factory()->create(['created_by' => $admin->id, 'scoring_type' => 'standard']);
    $squad = makeSquadForMatch($match);

    $res = $this->actingAs($admin)->get("/admin/matches/{$match->id}/squads/{$squad->id}/correct");

    $res->assertOk();
});
