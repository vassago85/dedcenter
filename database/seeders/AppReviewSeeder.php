<?php

namespace Database\Seeders;

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\MatchDivision;
use App\Models\Organization;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Guest accounts and one sandbox match for Android app testing and Play review.
 *
 * These are platform shooters attached only to the App Review organisation.
 * They can sign in and score that match. They are not platform admins, so
 * they cannot see any other club's matches.
 *
 * Safe to re-run. Re-running resets the three passwords and puts the sandbox
 * match back to Ready with its future date, so the auto-close job does not
 * hide it from the app.
 *
 * Sign-in (all three): password Guest-Test-2026
 *   reviewer@deadcenter.co.za        Play review / org owner
 *   director.guest@deadcenter.co.za  match director
 *   shooter.guest@deadcenter.co.za   shooter who can score the sandbox
 *
 * No account is required for "Join a hub (offline)" in the Android app.
 */
class AppReviewSeeder extends Seeder
{
    public const PASSWORD = 'Guest-Test-2026';

    public const REVIEWER_EMAIL = 'reviewer@deadcenter.co.za';

    public const DIRECTOR_EMAIL = 'director.guest@deadcenter.co.za';

    public const SHOOTER_EMAIL = 'shooter.guest@deadcenter.co.za';

    public const ORG_SLUG = 'app-review';

    public const MATCH_NAME = 'App Review Sandbox';

    public function run(): void
    {
        $reviewer = $this->account(self::REVIEWER_EMAIL, 'Play Reviewer');
        $director = $this->account(self::DIRECTOR_EMAIL, 'Guest Director');
        $shooter = $this->account(self::SHOOTER_EMAIL, 'Guest Shooter');

        $org = Organization::firstOrCreate(
            ['slug' => self::ORG_SLUG],
            [
                'name' => 'DeadCenter App Review',
                'description' => 'Sandbox organisation for app testing and Play review. Not a real club.',
                'type' => 'club',
                'status' => 'active',
                'created_by' => $reviewer->id,
            ]
        );

        $org->admins()->sync([
            $reviewer->id => [
                'is_owner' => true,
                'is_match_director' => true,
                'is_range_officer' => true,
                'is_shooter' => false,
            ],
            $director->id => [
                'is_owner' => false,
                'is_match_director' => true,
                'is_range_officer' => true,
                'is_shooter' => false,
            ],
            $shooter->id => [
                'is_owner' => false,
                'is_match_director' => false,
                'is_range_officer' => true,
                'is_shooter' => true,
            ],
        ]);

        $match = ShootingMatch::updateOrCreate(
            ['name' => self::MATCH_NAME, 'organization_id' => $org->id],
            [
                'date' => '2027-12-31',
                'location' => 'App review range',
                'status' => MatchStatus::Ready,
                'scoring_type' => 'standard',
                'concurrent_relays' => 1,
                'max_squad_size' => 4,
                'notes' => 'Sandbox match for the Android app and Play review. Safe to score.',
                'created_by' => $reviewer->id,
                'entry_fee' => 0,
            ]
        );

        $division = MatchDivision::firstOrCreate(
            ['match_id' => $match->id, 'name' => 'Open'],
            ['sort_order' => 1]
        );

        $stage = TargetSet::updateOrCreate(
            ['match_id' => $match->id, 'label' => '100m'],
            [
                'distance_meters' => 100,
                'distance_multiplier' => 1,
                'sort_order' => 1,
                'is_tiebreaker' => false,
            ]
        );

        Gong::updateOrCreate(
            ['target_set_id' => $stage->id, 'number' => 1],
            ['label' => 'Gong', 'multiplier' => 1]
        );

        $squad = Squad::updateOrCreate(
            ['match_id' => $match->id, 'name' => 'Squad 1'],
            ['sort_order' => 1, 'max_capacity' => 4]
        );

        foreach ([$reviewer, $director, $shooter] as $i => $user) {
            Shooter::updateOrCreate(
                ['squad_id' => $squad->id, 'user_id' => $user->id],
                [
                    'name' => $user->name,
                    'bib_number' => (string) ($i + 1),
                    'match_division_id' => $division->id,
                    'sort_order' => $i + 1,
                    'status' => 'active',
                ]
            );
        }
    }

    private function account(string $email, string $name): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => self::PASSWORD,
                'role' => 'shooter',
            ]
        );

        $user->forceFill([
            'name' => $name,
            'password' => self::PASSWORD,
            'role' => 'shooter',
            'email_verified_at' => now(),
            'accepted_terms_at' => now(),
            'onboarded_at' => now(),
        ])->save();

        return $user->fresh();
    }
}
