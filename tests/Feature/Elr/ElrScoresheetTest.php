<?php

use App\Enums\ElrShotResult;
use App\Enums\MatchStatus;
use App\Models\ElrShot;
use App\Models\ScoreAuditLog;
use App\Models\ShootingMatch;
use App\Models\User;
use App\Services\Scoring\ElrScoresheetService;
use Database\Seeders\PeregrineAlma7Nov2026Seeder;
use Illuminate\Support\Facades\Hash;

it('opens the scoresheet for an admin and refuses a standard match', function () {
    $admin = User::factory()->admin()->create();
    $elr = ShootingMatch::factory()->elr()->create(['created_by' => $admin->id, 'scoring_type' => 'elr']);
    $standard = ShootingMatch::factory()->create(['created_by' => $admin->id, 'scoring_type' => 'standard']);

    $this->actingAs($admin)->get(route('admin.matches.scoresheet', $elr))
        ->assertOk()
        ->assertSee('Nothing to score yet');

    $this->actingAs($admin)->get(route('admin.matches.scoresheet', $standard))
        ->assertRedirect(route('admin.matches.scoring', $standard));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.matches.scoresheet', $elr))
        ->assertForbidden();
});

it('writes a hit on the gong that class actually shoots and clears a cell back to blank', function () {
    User::factory()->create([
        'name' => 'JD Els',
        'email' => 'jd.els1989@gmail.com',
        'password' => Hash::make('password'),
        'role' => 'owner',
    ]);
    $this->seed(PeregrineAlma7Nov2026Seeder::class);

    $admin = User::factory()->admin()->create();
    $match = ShootingMatch::where('name', PeregrineAlma7Nov2026Seeder::MATCH_NAME)->first();
    $service = app(ElrScoresheetService::class);
    $sheet = $service->sheet($match);

    $carl = collect($sheet['shooters'])->firstWhere('name', 'Carl Louw');
    $danie = collect($sheet['shooters'])->firstWhere('name', 'Danie Koch');
    $carlNear = $carl['groups'][0]['targets'][0];
    $danieNear = $danie['groups'][0]['targets'][0];

    expect($carlNear['distance'])->toBe(594)
        ->and($danieNear['distance'])->toBe(827);

    $miss = $carlNear['shots'][0]['key'];
    $hit = $carlNear['shots'][1]['key'];

    $service->apply($match, [
        $miss => '0',
        $hit => '1',
        $danie['id'].':'.$carlNear['target_id'].':1' => '1',
    ], 'paper squad 3', $admin->id);

    $match->refresh();
    expect($match->status)->toBe(MatchStatus::Ready);

    $shots = ElrShot::where('shooter_id', $carl['id'])->where('elr_target_id', $carlNear['target_id'])->orderBy('shot_number')->get();
    expect($shots)->toHaveCount(2)
        ->and($shots[0]->result)->toBe(ElrShotResult::Miss)
        ->and($shots[0]->impact_number)->toBeNull()
        ->and($shots[1]->result)->toBe(ElrShotResult::Hit)
        ->and($shots[1]->impact_number)->toBe(1)
        ->and((float) $shots[1]->points_awarded)->toBe(891.0);

    expect(ElrShot::where('shooter_id', $danie['id'])->where('elr_target_id', $carlNear['target_id'])->exists())->toBeFalse();

    expect(ScoreAuditLog::where('match_id', $match->id)->where('reason', 'paper squad 3')->count())->toBe(2);

    $service->apply($match, [$hit => ''], 'cleared the second shot', $admin->id);

    expect(ElrShot::where('shooter_id', $carl['id'])->where('elr_target_id', $carlNear['target_id'])->where('shot_number', 2)->exists())->toBeFalse()
        ->and(ElrShot::where('shooter_id', $carl['id'])->where('elr_target_id', $carlNear['target_id'])->where('shot_number', 1)->exists())->toBeTrue();

    $this->actingAs($admin)->get(route('admin.matches.scoresheet', $match))
        ->assertOk()
        ->assertSee('Carl Louw')
        ->assertSee('594m')
        ->assertSee('827m');
});
