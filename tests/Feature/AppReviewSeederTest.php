<?php

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\User;
use Database\Seeders\AppReviewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function reviewLogin(string $email): string
{
    $response = test()->postJson('/api/login', [
        'email' => $email,
        'password' => AppReviewSeeder::PASSWORD,
        'device_name' => 'Play Review',
    ])->assertOk();

    return $response->json('token');
}

it('gives the review accounts a sandbox match and nothing else', function () {
    $this->seed(AppReviewSeeder::class);

    $otherOrg = Organization::factory()->create();
    $outsider = User::factory()->create();
    ShootingMatch::factory()->ready()->create([
        'name' => 'Someone Elses Match',
        'organization_id' => $otherOrg->id,
        'created_by' => $outsider->id,
    ]);

    foreach ([
        AppReviewSeeder::REVIEWER_EMAIL,
        AppReviewSeeder::DIRECTOR_EMAIL,
        AppReviewSeeder::SHOOTER_EMAIL,
    ] as $email) {
        $token = reviewLogin($email);

        $this->withToken($token)
            ->getJson('/api/matches')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', AppReviewSeeder::MATCH_NAME);
    }

    $shooter = User::where('email', AppReviewSeeder::SHOOTER_EMAIL)->first();
    $match = ShootingMatch::where('name', AppReviewSeeder::MATCH_NAME)->first();
    $shooterRow = $match->shooters()->where('user_id', $shooter->id)->first();
    $gong = Gong::where('target_set_id', $match->targetSets()->value('id'))->first();

    $this->withToken(reviewLogin(AppReviewSeeder::SHOOTER_EMAIL))
        ->postJson("/api/matches/{$match->id}/scores", [
            'scores' => [[
                'shooter_id' => $shooterRow->id,
                'gong_id' => $gong->id,
                'is_hit' => true,
                'device_id' => 'review-device',
                'recorded_at' => now()->toISOString(),
            ]],
        ])->assertOk();

    expect($match->fresh()->status)->toBe(MatchStatus::Active);

    $this->seed(AppReviewSeeder::class);

    expect(ShootingMatch::where('name', AppReviewSeeder::MATCH_NAME)->count())->toBe(1)
        ->and(ShootingMatch::where('name', AppReviewSeeder::MATCH_NAME)->first()->status)->toBe(MatchStatus::Ready);

    reviewLogin(AppReviewSeeder::REVIEWER_EMAIL);
});

it('publishes the privacy policy including the android app', function () {
    $this->get('/privacy')
        ->assertOk()
        ->assertSee('Android App')
        ->assertSee('Join a hub (offline)')
        ->assertSee('5 November 2026');
});
