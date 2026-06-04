<?php

namespace Database\Seeders;

use App\Enums\ElrEngagementMode;
use App\Enums\ElrStageType;
use App\Enums\MatchStatus;
use App\Models\ElrScoringProfile;
use App\Models\ElrStage;
use App\Models\ElrTarget;
use App\Models\MatchDivision;
use App\Models\MatchRegistration;
use App\Models\Organization;
use App\Models\Season;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seeds the two June 2026 weekend TEST matches JD Els asked for, mirroring
 * the real match-day spreadsheets exactly so the new ELR scoring engine and
 * the native Android scoring app can be exercised end-to-end on realistic
 * data before/at the live event:
 *
 *   • Peregrine ELR Challenge — Test Match (Jun 2026)
 *       5 sponsored stations (Warrior / Brothers Arms / Integrix / Delta /
 *       Zeiss), 4-rung ladders, Minor T1‑T3 + Major T2‑T4, two-shooter teams,
 *       3 shots per target, multipliers 1.5 / 1.25 / 1.0. Real 5-squad
 *       squadding + shooting order from the workbook.
 *
 *   • Forster 2 Mile Challenge — Test Match (Jun 2026)
 *       4 stations at 2024 / 2478 / 2836 / 3272, Major only, individual,
 *       5 shots per target, multipliers 2.0 / 1.75 / 1.5 / 1.25 / 1.0.
 *
 * Both matches are seeded in `Active` status (scoring live, downloadable to
 * tablets) with empty scores — they are practice/validation matches, not
 * pre-scored results. `elr_distance_based_scoring` is on so points =
 * distance × shot multiplier, and base_points is set to the distance so the
 * Android offline scoring path lands on the same number.
 *
 * Roster / squadding / distances / multipliers come from
 * `database/seeders/demo/june-2026-test-matches.json` (extracted from the
 * organiser's June workbooks). Idempotent and defensive about Phase A columns.
 */
class DemoJuneTestMatchesSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@deadcenter.co.za')->first()
            ?? User::where('role', 'owner')->first();

        if (! $admin) {
            $this->command?->warn('No admin user found — skipping DemoJuneTestMatchesSeeder.');
            return;
        }

        $dataPath = __DIR__ . '/demo/june-2026-test-matches.json';
        if (! is_file($dataPath)) {
            $this->command?->warn("Missing demo data file: $dataPath — skipping.");
            return;
        }

        $data = json_decode(file_get_contents($dataPath), true);
        if (! is_array($data)) {
            $this->command?->warn('Could not parse june-2026-test-matches.json — skipping.');
            return;
        }

        $this->seedPeregrineTest($admin, $data['peregrine'] ?? []);
        $this->seedForsterTest($admin, $data['forster'] ?? []);
        $this->ensureJdOwnership();

        $this->command?->info('Seeded June 2026 Peregrine + Forster TEST matches (ready to score).');
    }

    // ───────────────────────── Peregrine ─────────────────────────

    private function seedPeregrineTest(User $admin, array $payload): void
    {
        if (empty($payload['shooters'] ?? [])) {
            $this->command?->warn('No Peregrine June roster in JSON — skipping Peregrine test match.');
            return;
        }

        $org = $this->resolvePeregrineOrg($admin);
        $season = Season::firstOrCreate(
            ['name' => 'Peregrine ELR Challenge 2026', 'organization_id' => $org->id],
            [
                'year'       => 2026,
                'start_date' => '2026-03-07',
                'end_date'   => '2026-10-31',
                'created_by' => $admin->id,
            ]
        );

        $match = $this->upsertMatch(
            admin: $admin,
            organization: $org,
            season: $season,
            name: $payload['match_name'] ?? 'Peregrine ELR Challenge — Test Match (Jun 2026)',
            date: $payload['match_date'] ?? '2026-06-06',
            status: MatchStatus::Active,
            location: 'Pierre van Ryneveld Long Range',
            province: 'gauteng',
            extra: [
                'team_event' => true,
                'team_size'  => 2,
                'public_bio' => 'TEST match — five sponsored ELR stations, two divisions, team pairs. Used to validate scoring + the native app.',
            ]
        );

        $profile = ElrScoringProfile::updateOrCreate(
            ['match_id' => $match->id, 'name' => 'Peregrine 3-shot ramp'],
            ['multipliers' => $payload['multipliers'] ?? [1.5, 1.25, 1.0]]
        );

        $this->applyElrMatchSettings($match, $profile, shotsPerTarget: 3);

        $this->seedLadderStations($match, $profile, $payload['stations'] ?? [], maxShots: 3);

        $minor = MatchDivision::updateOrCreate(
            ['match_id' => $match->id, 'name' => 'Minor'],
            ['sort_order' => 1, 'description' => 'Minor — engages T1, T2 and T3 on every station.']
        );
        $major = MatchDivision::updateOrCreate(
            ['match_id' => $match->id, 'name' => 'Major'],
            ['sort_order' => 2, 'description' => 'Major — engages T2, T3 and T4 on every station.']
        );

        $this->mapDivisionTargets($match, $minor, [1, 2, 3]);
        $this->mapDivisionTargets($match, $major, [2, 3, 4]);

        $this->populatePeregrineRoster($match, $payload['shooters'], $minor, $major);
    }

    // ───────────────────────── Forster ─────────────────────────

    private function seedForsterTest(User $admin, array $payload): void
    {
        if (empty($payload['shooters'] ?? [])) {
            $this->command?->warn('No Forster June roster in JSON — skipping Forster test match.');
            return;
        }

        $org = $this->resolveForsterOrg($admin);
        $season = Season::firstOrCreate(
            ['name' => 'Forster 2 Mile Challenge 2025/26', 'organization_id' => $org->id],
            [
                'year'       => 2026,
                'start_date' => '2025-11-28',
                'end_date'   => '2026-09-30',
                'created_by' => $admin->id,
            ]
        );

        $match = $this->upsertMatch(
            admin: $admin,
            organization: $org,
            season: $season,
            name: $payload['match_name'] ?? 'Forster 2 Mile Challenge — Test Match (Jun 2026)',
            date: $payload['match_date'] ?? '2026-06-06',
            status: MatchStatus::Active,
            location: 'Forster Long Range',
            province: 'free_state',
            extra: [
                'team_event' => false,
                'public_bio' => 'TEST match — 4 stations at 2 024 m, 2 478 m, 2 836 m and 3 272 m. Major only. Used to validate scoring + the native app.',
            ]
        );

        $profile = ElrScoringProfile::updateOrCreate(
            ['match_id' => $match->id, 'name' => 'Forster 5-shot ramp'],
            ['multipliers' => $payload['multipliers'] ?? [2.0, 1.75, 1.5, 1.25, 1.0]]
        );

        $this->applyElrMatchSettings($match, $profile, shotsPerTarget: 5);

        $this->seedForsterStations($match, $profile, $payload['stations'] ?? []);

        $major = MatchDivision::updateOrCreate(
            ['match_id' => $match->id, 'name' => 'Major'],
            ['sort_order' => 1, 'description' => 'Major calibre only — 375 CheyTac and up.']
        );

        $this->populateForsterRoster($match, $payload['shooters'], $major);
    }

    // ───────────────────────── shared helpers ─────────────────────────

    private function resolvePeregrineOrg(User $admin): Organization
    {
        $org = Organization::firstOrCreate(
            ['slug' => 'peregrine-elr-challenge'],
            [
                'name'             => 'Peregrine ELR Challenge',
                'description'      => 'South African Extra Long Range team series. Five sponsored stations, four targets each, Minor & Major divisions, two-shooter pairs.',
                'type'             => 'competition',
                'status'           => 'active',
                'created_by'       => $admin->id,
                'primary_color'    => '#f59e0b',
                'secondary_color'  => '#0f172a',
                'portal_enabled'   => true,
                'portal_entitled'  => true,
                'portal_ad_rights' => true,
                'best_of'          => 3,
            ]
        );
        $org->admins()->syncWithoutDetaching([$admin->id => ['is_owner' => true]]);
        return $org;
    }

    private function resolveForsterOrg(User $admin): Organization
    {
        $org = Organization::firstOrCreate(
            ['slug' => 'forster-2-mile-challenge'],
            [
                'name'             => 'Forster 2 Mile Challenge',
                'description'      => "South Africa's premier 2-Mile ELR series. Four stations from 2 000 m to 3 300 m. Major only.",
                'type'             => 'competition',
                'status'           => 'active',
                'created_by'       => $admin->id,
                'primary_color'    => '#1f2937',
                'secondary_color'  => '#dc2626',
                'portal_enabled'   => true,
                'portal_entitled'  => true,
                'portal_ad_rights' => true,
                'best_of'          => 3,
            ]
        );
        $org->admins()->syncWithoutDetaching([$admin->id => ['is_owner' => true]]);
        return $org;
    }

    private function upsertMatch(
        User $admin,
        Organization $organization,
        Season $season,
        string $name,
        string $date,
        MatchStatus $status,
        string $location,
        string $province,
        array $extra = []
    ): ShootingMatch {
        return ShootingMatch::updateOrCreate(
            ['name' => $name],
            array_merge([
                'date'             => $date,
                'location'         => $location,
                'province'         => $province,
                'status'           => $status,
                'scoring_type'     => 'elr',
                'created_by'       => $admin->id,
                'organization_id'  => $organization->id,
                'season_id'        => $season->id,
                'scores_published' => false,
            ], $extra)
        );
    }

    private function applyElrMatchSettings(ShootingMatch $match, ElrScoringProfile $profile, int $shotsPerTarget): void
    {
        $matchUpdate = [
            'elr_scoring_profile_id' => $profile->id,
            'elr_engagement_mode'    => ElrEngagementMode::TargetByTarget,
            'elr_shots_per_target'   => $shotsPerTarget,
        ];
        if (Schema::hasColumn('matches', 'elr_distance_based_scoring')) {
            $matchUpdate['elr_distance_based_scoring'] = true;
        }
        $match->update($matchUpdate);
    }

    /**
     * Peregrine-style: each station is a single ELR stage carrying a 4-rung
     * target ladder. base_points = distance so the Android offline path and
     * the cloud distance-based path produce the same score.
     *
     * @param  array<int,array{label:string,sponsor:?string,targets:array<int>}>  $stations
     */
    private function seedLadderStations(ShootingMatch $match, ElrScoringProfile $profile, array $stations, int $maxShots): void
    {
        $palette = ['#f97316', '#eab308', '#3b82f6', '#10b981', '#ef4444', '#a855f7'];

        foreach ($stations as $idx => $s) {
            $stagePayload = [
                'stage_type'             => ElrStageType::Static,
                'elr_scoring_profile_id' => $profile->id,
                'sort_order'             => $idx + 1,
            ];
            if (Schema::hasColumn('elr_stages', 'sponsor')) {
                $stagePayload['sponsor'] = $s['sponsor'] ?? null;
            }
            if (Schema::hasColumn('elr_stages', 'color')) {
                $stagePayload['color'] = $palette[$idx % count($palette)];
            }

            $stage = ElrStage::updateOrCreate(
                ['match_id' => $match->id, 'label' => $s['label']],
                $stagePayload
            );

            foreach (($s['targets'] ?? []) as $i => $distance) {
                ElrTarget::updateOrCreate(
                    ['elr_stage_id' => $stage->id, 'name' => 'T' . ($i + 1)],
                    [
                        'distance_m'          => $distance,
                        'base_points'         => $distance,
                        'max_shots'           => $maxShots,
                        'must_hit_to_advance' => false,
                        'sort_order'          => $i + 1,
                    ]
                );
            }
        }
    }

    /**
     * Forster-style: each station is one target shot 5 times.
     *
     * @param  array<int,array{label:string,distance:int}>  $stations
     */
    private function seedForsterStations(ShootingMatch $match, ElrScoringProfile $profile, array $stations): void
    {
        $palette = ['#3b82f6', '#22c55e', '#eab308', '#ef4444'];

        foreach ($stations as $idx => $s) {
            $stagePayload = [
                'stage_type'             => ElrStageType::Static,
                'elr_scoring_profile_id' => $profile->id,
                'sort_order'             => $idx + 1,
            ];
            if (Schema::hasColumn('elr_stages', 'sponsor')) {
                $stagePayload['sponsor'] = null;
            }
            if (Schema::hasColumn('elr_stages', 'color')) {
                $stagePayload['color'] = $palette[$idx % count($palette)];
            }

            $stage = ElrStage::updateOrCreate(
                ['match_id' => $match->id, 'label' => $s['label']],
                $stagePayload
            );

            ElrTarget::updateOrCreate(
                ['elr_stage_id' => $stage->id, 'name' => 'T1'],
                [
                    'distance_m'          => $s['distance'],
                    'base_points'         => $s['distance'],
                    'max_shots'           => 5,
                    'must_hit_to_advance' => false,
                    'sort_order'          => 1,
                ]
            );
        }
    }

    /**
     * @param  array<int>  $positions  ladder rungs this division engages.
     */
    private function mapDivisionTargets(ShootingMatch $match, MatchDivision $division, array $positions): void
    {
        if (! Schema::hasTable('elr_division_targets')) {
            return;
        }
        $targetIds = ElrTarget::query()
            ->whereIn('elr_stage_id', $match->elrStages()->pluck('id'))
            ->whereIn('sort_order', $positions)
            ->pluck('id')
            ->all();
        $division->elrTargets()->sync($targetIds);
    }

    /**
     * Build the 5 real squads, teams (by spreadsheet team name) and shooters,
     * preserving the workbook's squad assignment and within-squad shooting
     * order. Each shooter gets a User + confirmed MatchRegistration so caliber
     * surfaces on the ELR leaderboard payload.
     *
     * @param  array<int,array{squad:int,name:string,team:string,caliber:string,class:string}>  $shooters
     */
    private function populatePeregrineRoster(
        ShootingMatch $match,
        array $shooters,
        MatchDivision $minor,
        MatchDivision $major
    ): void {
        $squadCache = [];
        $teamCache  = [];
        $orderInSquad = [];

        foreach ($shooters as $globalIndex => $row) {
            $squadNo = (int) ($row['squad'] ?? 0) ?: 1;
            if (! isset($squadCache[$squadNo])) {
                $squadCache[$squadNo] = Squad::updateOrCreate(
                    ['match_id' => $match->id, 'name' => "Squad $squadNo"],
                    ['sort_order' => $squadNo, 'max_capacity' => 16]
                );
                $orderInSquad[$squadNo] = 0;
            }
            $squad = $squadCache[$squadNo];
            $orderInSquad[$squadNo]++;

            $division = strcasecmp($row['class'] ?? '', 'Major') === 0 ? $major : $minor;

            $teamName = trim($row['team'] ?? '') ?: 'Unassigned';
            $teamKey = $match->id . '|' . $teamName;
            if (! isset($teamCache[$teamKey])) {
                $teamCache[$teamKey] = Team::updateOrCreate(
                    ['match_id' => $match->id, 'name' => $teamName],
                    ['max_size' => 2, 'sort_order' => count($teamCache) + 1]
                );
            }
            $team = $teamCache[$teamKey];

            $user = $this->upsertDemoUser($row['name']);

            MatchRegistration::updateOrCreate(
                ['match_id' => $match->id, 'user_id' => $user->id],
                [
                    'caliber'           => $row['caliber'] ?? null,
                    'payment_status'    => 'confirmed',
                    'payment_reference' => sprintf('TEST-PER-%d-%d', $match->id, $user->id),
                    'is_free_entry'     => true,
                ]
            );

            Shooter::updateOrCreate(
                ['squad_id' => $squad->id, 'name' => $row['name']],
                [
                    'bib_number'        => 'P-' . str_pad((string) ($globalIndex + 1), 3, '0', STR_PAD_LEFT),
                    'user_id'           => $user->id,
                    'match_division_id' => $division->id,
                    'team_id'           => $team->id,
                    'sort_order'        => $orderInSquad[$squadNo],
                    'status'            => 'active',
                ]
            );
        }
    }

    /**
     * Forster is individual — one Heat squad, every shooter Major, ordered by bib.
     *
     * @param  array<int,array{bib:int,name:string,caliber:string}>  $shooters
     */
    private function populateForsterRoster(ShootingMatch $match, array $shooters, MatchDivision $major): void
    {
        $squad = Squad::updateOrCreate(
            ['match_id' => $match->id, 'name' => 'Heat 1'],
            ['sort_order' => 1, 'max_capacity' => 40]
        );

        foreach ($shooters as $index => $row) {
            $user = $this->upsertDemoUser($row['name']);

            MatchRegistration::updateOrCreate(
                ['match_id' => $match->id, 'user_id' => $user->id],
                [
                    'caliber'           => $row['caliber'] ?? null,
                    'payment_status'    => 'confirmed',
                    'payment_reference' => sprintf('TEST-FOR-%d-%d', $match->id, $user->id),
                    'is_free_entry'     => true,
                ]
            );

            Shooter::updateOrCreate(
                ['squad_id' => $squad->id, 'name' => $row['name']],
                [
                    'bib_number'        => 'F-' . str_pad((string) ($row['bib'] ?? ($index + 1)), 3, '0', STR_PAD_LEFT),
                    'user_id'           => $user->id,
                    'match_division_id' => $major->id,
                    'sort_order'        => $index + 1,
                    'status'            => 'active',
                ]
            );
        }
    }

    /**
     * Keep JD Els owning both ELR orgs even when this seeder runs standalone
     * (DemoEliteSeasonsSeeder normally does this, but `--class` runs may skip it).
     */
    private function ensureJdOwnership(): void
    {
        $jd = User::where('email', 'Jd.els1989@gmail.com')->first();
        if (! $jd) {
            return;
        }
        foreach (['peregrine-elr-challenge', 'forster-2-mile-challenge'] as $slug) {
            $org = Organization::where('slug', $slug)->first();
            $org?->admins()->syncWithoutDetaching([$jd->id => ['is_owner' => true]]);
        }
    }

    private function upsertDemoUser(string $fullName): User
    {
        $slug = Str::slug($fullName, '.');
        $email = $slug . '@elr-demo.deadcenter.local';

        return User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => $fullName,
                'password' => Hash::make(Str::random(24)),
                'role'     => 'shooter',
            ]
        );
    }
}
