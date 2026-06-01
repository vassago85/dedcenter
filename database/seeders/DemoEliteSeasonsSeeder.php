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
 * Demo seed for the two real ELR series JD Els runs in 2026:
 *
 *   • Peregrine ELR Challenge  — 5 sponsored stations, 4 targets each
 *                                (Minor T1‑T3, Major T2‑T4), team pairs,
 *                                3-shot per target with multipliers 1.5 / 1.25 / 1.0.
 *
 *   • Forster 2 Mile Challenge — 4 stations (A‑D) at 2024 / 2478 / 2836 / 3272 m,
 *                                Major only, individual, 5 shots per target
 *                                with multipliers 2.0 / 1.75 / 1.5 / 1.25 / 1.0.
 *
 * Roster data is read from `database/seeders/demo/elite-seasons-data.json` which
 * was extracted from the two sample spreadsheets the organisers sent through.
 * Per-shot data is NOT replayed — every match is seeded in its setup state so
 * the new ELR scoring engine can be exercised live.
 *
 * Idempotent: re-running the seeder updates organisations / seasons / matches
 * in place and only inserts shooters / teams / divisions that don't already
 * exist for that match. New ELR Phase A columns (sponsor / colour, division‑
 * target pivot, distance‑based scoring toggle) are written defensively — if a
 * deployment hasn't run those migrations yet the seeder skips just those
 * fields instead of blowing up.
 */
class DemoEliteSeasonsSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@deadcenter.co.za')->first()
            ?? User::where('role', 'owner')->first();

        if (! $admin) {
            $this->command?->warn('No admin user found — skipping DemoEliteSeasonsSeeder.');
            return;
        }

        $dataPath = __DIR__ . '/demo/elite-seasons-data.json';
        if (! is_file($dataPath)) {
            $this->command?->warn("Missing demo data file: $dataPath — skipping.");
            return;
        }

        $data = json_decode(file_get_contents($dataPath), true);
        if (! is_array($data)) {
            $this->command?->warn('Could not parse elite-seasons-data.json — skipping.');
            return;
        }

        $this->seedPeregrine($admin, $data['peregrine'] ?? []);
        $this->seedForster($admin, $data['forster'] ?? []);
        $this->seedJdEls();

        $this->command?->info('Seeded Peregrine ELR Challenge + Forster 2 Mile Challenge.');
    }

    /**
     * JD Els runs both ELR series in real life — seed him as a platform owner
     * with ownership on Royal Flush + Peregrine + Forster so he sees all three
     * orgs in his Organization mode switcher straight after `db:seed`.
     *
     * On a first-run, the account is created with password `Peregrine2026!`
     * (set once). On subsequent runs the password is left alone so JD can
     * change it via the UI without the seeder clobbering it.
     */
    private function seedJdEls(): void
    {
        $jd = User::firstOrCreate(
            ['email' => 'Jd.els1989@gmail.com'],
            [
                'name'     => 'JD Els',
                'password' => Hash::make('Peregrine2026!'),
                'role'     => 'owner',
            ]
        );

        // If the row already existed but with a lesser role, lift JD up to
        // owner so he keeps platform admin access on re-runs.
        if ($jd->role !== 'owner') {
            $jd->forceFill(['role' => 'owner'])->save();
        }

        foreach (['royal-flush', 'peregrine-elr-challenge', 'forster-2-mile-challenge'] as $slug) {
            $org = Organization::where('slug', $slug)->first();
            if ($org) {
                $org->admins()->syncWithoutDetaching([
                    $jd->id => ['is_owner' => true],
                ]);
            }
        }
    }

    // ───────────────────────── Peregrine ─────────────────────────

    private function seedPeregrine(User $admin, array $payload): void
    {
        $org = Organization::updateOrCreate(
            ['slug' => 'peregrine-elr-challenge'],
            [
                'name' => 'Peregrine ELR Challenge',
                'description' => 'South African Extra Long Range team series. Five sponsored stations, four targets each, Minor & Major divisions, two-shooter pairs.',
                'type' => 'competition',
                'status' => 'active',
                'created_by' => $admin->id,
                'primary_color' => '#f59e0b',   // peregrine orange
                'secondary_color' => '#0f172a',
                'hero_text' => 'Peregrine ELR Challenge 2026',
                'hero_description' => 'Four rounds. Five stations. Two divisions. Track every hit, every team, every round across the season.',
                'portal_enabled' => true,
                'portal_entitled' => true,
                'portal_ad_rights' => true,
                'best_of' => 3,
            ]
        );
        $org->admins()->syncWithoutDetaching([$admin->id => ['is_owner' => true]]);

        $season = Season::updateOrCreate(
            ['name' => 'Peregrine ELR Challenge 2026', 'organization_id' => $org->id],
            [
                'year' => 2026,
                'start_date' => '2026-03-07',
                'end_date' => '2026-10-31',
                'created_by' => $admin->id,
            ]
        );

        $stations = [
            ['label' => 'Warrior',       'sponsor' => 'Warrior',        'color' => '#f97316', 'targets' => [597, 840, 930, 1678]],
            ['label' => 'Brothers Arms', 'sponsor' => 'Brothers Arms',  'color' => '#eab308', 'targets' => [524, 800, 1256, 2175]],
            ['label' => 'Integrix',      'sponsor' => 'Integrix',       'color' => '#3b82f6', 'targets' => [560, 960, 1617, 2091]],
            ['label' => 'Delta Optics',  'sponsor' => 'Delta Optics',   'color' => '#10b981', 'targets' => [610, 1193, 1234, 1545]],
            ['label' => 'Zeiss',         'sponsor' => 'Zeiss',          'color' => '#ef4444', 'targets' => [754, 888, 1422, 1558]],
        ];

        // Four rounds across 2026 — Round 1 fully populated from the sample
        // spreadsheet, rounds 2-4 created as scheduled scaffolds.
        $rounds = [
            ['name' => 'Peregrine ELR Challenge — Round 1 (7 March 2026)',     'date' => '2026-03-07', 'status' => MatchStatus::Active, 'populate' => true],
            ['name' => 'Peregrine ELR Challenge — Round 2 (16 May 2026)',      'date' => '2026-05-16', 'status' => MatchStatus::Draft,  'populate' => false],
            ['name' => 'Peregrine ELR Challenge — Round 3 (29 August 2026)',   'date' => '2026-08-29', 'status' => MatchStatus::Draft,  'populate' => false],
            ['name' => 'Peregrine ELR Challenge — Round 4 (24 October 2026)',  'date' => '2026-10-24', 'status' => MatchStatus::Draft,  'populate' => false],
        ];

        foreach ($rounds as $r) {
            $match = $this->upsertMatch(
                admin: $admin,
                organization: $org,
                season: $season,
                name: $r['name'],
                date: $r['date'],
                status: $r['status'],
                location: 'Pierre van Ryneveld Long Range',
                province: 'gauteng',
                extra: [
                    'team_event'   => true,
                    'team_size'    => 2,
                    'public_bio'   => 'Five sponsored ELR stations, two divisions, team pairs.',
                ]
            );

            $profile = ElrScoringProfile::updateOrCreate(
                ['match_id' => $match->id, 'name' => 'Peregrine 3-shot ramp'],
                ['multipliers' => [1.5, 1.25, 1.0]]
            );

            $matchUpdate = [
                'elr_scoring_profile_id' => $profile->id,
                'elr_engagement_mode'    => ElrEngagementMode::TargetByTarget,
                'elr_shots_per_target'   => 3,
            ];
            if (Schema::hasColumn('matches', 'elr_distance_based_scoring')) {
                $matchUpdate['elr_distance_based_scoring'] = true;
            }
            $match->update($matchUpdate);

            $this->seedStations($match, $profile, $stations);

            [$minor, $major] = $this->seedPeregrineDivisions($match);

            // Map every Minor shooter → station T1‑T3, every Major → station T2‑T4.
            // The pivot is only present once Phase A migrations have run.
            $this->mapDivisionTargets($match, $minor, [1, 2, 3]);
            $this->mapDivisionTargets($match, $major, [2, 3, 4]);

            if ($r['populate']) {
                $this->populatePeregrineRoster($match, $payload['shooters'] ?? [], $minor, $major);
            }
        }
    }

    // ───────────────────────── Forster ─────────────────────────

    private function seedForster(User $admin, array $payload): void
    {
        $org = Organization::updateOrCreate(
            ['slug' => 'forster-2-mile-challenge'],
            [
                'name' => 'Forster 2 Mile Challenge',
                'description' => 'South Africa\'s premier 2-Mile ELR series. Four stations from 2 000 m to 3 300 m. Major only.',
                'type' => 'competition',
                'status' => 'active',
                'created_by' => $admin->id,
                'primary_color' => '#1f2937',
                'secondary_color' => '#dc2626',
                'hero_text' => 'Forster 2 Mile Challenge 2025/26',
                'hero_description' => 'Push the limits of what\'s possible behind the rifle. Hits at two miles. Real distance, real glass, real shooters.',
                'portal_enabled' => true,
                'portal_entitled' => true,
                'portal_ad_rights' => true,
                'best_of' => 3,
            ]
        );
        $org->admins()->syncWithoutDetaching([$admin->id => ['is_owner' => true]]);

        $season = Season::updateOrCreate(
            ['name' => 'Forster 2 Mile Challenge 2025/26', 'organization_id' => $org->id],
            [
                'year' => 2026,
                'start_date' => '2025-11-28',
                'end_date' => '2026-09-30',
                'created_by' => $admin->id,
            ]
        );

        $stations = [
            ['label' => 'Station A', 'sponsor' => null, 'color' => '#3b82f6', 'distance' => 2024],
            ['label' => 'Station B', 'sponsor' => null, 'color' => '#22c55e', 'distance' => 2478],
            ['label' => 'Station C', 'sponsor' => null, 'color' => '#eab308', 'distance' => 2836],
            ['label' => 'Station D', 'sponsor' => null, 'color' => '#ef4444', 'distance' => 3272],
        ];

        $rounds = [
            ['name' => 'Forster 2 Mile — Round 1, Heat 1 (28 November 2025)', 'date' => '2025-11-28', 'status' => MatchStatus::Active, 'populate' => true],
            ['name' => 'Forster 2 Mile — Round 1, Heat 2 (29 November 2025)', 'date' => '2025-11-29', 'status' => MatchStatus::Draft,  'populate' => false],
            ['name' => 'Forster 2 Mile — Round 2 (March 2026)',                'date' => '2026-03-21', 'status' => MatchStatus::Draft,  'populate' => false],
            ['name' => 'Forster 2 Mile — Round 3 (September 2026)',            'date' => '2026-09-26', 'status' => MatchStatus::Draft,  'populate' => false],
        ];

        foreach ($rounds as $r) {
            $match = $this->upsertMatch(
                admin: $admin,
                organization: $org,
                season: $season,
                name: $r['name'],
                date: $r['date'],
                status: $r['status'],
                location: 'Forster Long Range',
                province: 'free_state',
                extra: [
                    'team_event' => false,
                    'public_bio' => '4 stations at 2 024 m, 2 478 m, 2 836 m and 3 272 m. Major only.',
                ]
            );

            $profile = ElrScoringProfile::updateOrCreate(
                ['match_id' => $match->id, 'name' => 'Forster 5-shot ramp'],
                ['multipliers' => [2.0, 1.75, 1.5, 1.25, 1.0]]
            );

            $matchUpdate = [
                'elr_scoring_profile_id' => $profile->id,
                'elr_engagement_mode'    => ElrEngagementMode::TargetByTarget,
                'elr_shots_per_target'   => 5,
            ];
            if (Schema::hasColumn('matches', 'elr_distance_based_scoring')) {
                $matchUpdate['elr_distance_based_scoring'] = true;
            }
            $match->update($matchUpdate);

            // Each Forster "station" is one target, shot 5 times. So model each station
            // as a single ElrStage with one ElrTarget at the station distance.
            $this->seedForsterStations($match, $profile, $stations);

            // Single division for the whole match.
            $major = MatchDivision::updateOrCreate(
                ['match_id' => $match->id, 'name' => 'Major'],
                ['sort_order' => 1, 'description' => 'Major calibre only — 375 CheyTac and up.']
            );

            if ($r['populate']) {
                $this->populateForsterRoster($match, $payload['shooters'] ?? [], $major);
            }
        }
    }

    // ───────────────────────── helpers ─────────────────────────

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

    /**
     * Create / refresh a station per row, with branding + 4 targets at the supplied distances.
     */
    private function seedStations(ShootingMatch $match, ElrScoringProfile $profile, array $stations): void
    {
        foreach ($stations as $idx => $s) {
            $stagePayload = [
                'stage_type'             => ElrStageType::Static,
                'elr_scoring_profile_id' => $profile->id,
                'sort_order'             => $idx + 1,
            ];
            // sponsor + color columns only exist once Phase A migrations have run.
            if (Schema::hasColumn('elr_stages', 'sponsor')) {
                $stagePayload['sponsor'] = $s['sponsor'];
            }
            if (Schema::hasColumn('elr_stages', 'color')) {
                $stagePayload['color'] = $s['color'];
            }

            $stage = ElrStage::updateOrCreate(
                ['match_id' => $match->id, 'label' => $s['label']],
                $stagePayload
            );

            foreach ($s['targets'] as $i => $distance) {
                ElrTarget::updateOrCreate(
                    ['elr_stage_id' => $stage->id, 'name' => 'T' . ($i + 1)],
                    [
                        'distance_m'         => $distance,
                        'base_points'        => $distance,           // legacy fallback if distance_based_scoring is off
                        'max_shots'          => 3,
                        'must_hit_to_advance'=> false,
                        'sort_order'         => $i + 1,
                    ]
                );
            }
        }
    }

    private function seedForsterStations(ShootingMatch $match, ElrScoringProfile $profile, array $stations): void
    {
        foreach ($stations as $idx => $s) {
            $stagePayload = [
                'stage_type'             => ElrStageType::Static,
                'elr_scoring_profile_id' => $profile->id,
                'sort_order'             => $idx + 1,
            ];
            if (Schema::hasColumn('elr_stages', 'sponsor')) {
                $stagePayload['sponsor'] = $s['sponsor'];
            }
            if (Schema::hasColumn('elr_stages', 'color')) {
                $stagePayload['color'] = $s['color'];
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

    /** @return array{0:MatchDivision,1:MatchDivision} */
    private function seedPeregrineDivisions(ShootingMatch $match): array
    {
        $minor = MatchDivision::updateOrCreate(
            ['match_id' => $match->id, 'name' => 'Minor'],
            ['sort_order' => 1, 'description' => 'Minor — engages T1, T2 and T3 on every station.']
        );
        $major = MatchDivision::updateOrCreate(
            ['match_id' => $match->id, 'name' => 'Major'],
            ['sort_order' => 2, 'description' => 'Major — engages T2, T3 and T4 on every station.']
        );
        return [$minor, $major];
    }

    /**
     * Attach the elr_targets rows for this division to all matching positional targets.
     * No-ops if the pivot table doesn't exist yet (pre-Phase A deployment).
     *
     * @param  array<int>  $positions  e.g. [1,2,3] for T1‑T3.
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
     * Build squads (5 squads × ~14 shooters), teams (one per spreadsheet team
     * name) and shooters. Each shooter is also given a User + MatchRegistration
     * row so caliber surfaces on the new ELR leaderboard payload.
     */
    private function populatePeregrineRoster(
        ShootingMatch $match,
        array $shooters,
        MatchDivision $minor,
        MatchDivision $major
    ): void {
        if (empty($shooters)) return;

        // 5 squads — each holds ~14 shooters / ~7 two-person teams.
        $squads = [];
        for ($i = 1; $i <= 5; $i++) {
            $squads[] = Squad::updateOrCreate(
                ['match_id' => $match->id, 'name' => "Squad $i"],
                ['sort_order' => $i, 'max_capacity' => 16]
            );
        }

        // Spread shooters into squads round-robin so team-mates stay paired
        // (sort by team name first so a pair lands in the same squad).
        usort($shooters, fn ($a, $b) => strcmp((string) $a['team'], (string) $b['team']));

        // Index teams by name — same team across all squads is allowed and
        // matches how the source spreadsheet really runs.
        $teamCache = [];

        foreach ($shooters as $index => $row) {
            $squad = $squads[$index % count($squads)];
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
                    'payment_reference' => sprintf('DEMO-PER-%d-%d', $match->id, $user->id),
                    'is_free_entry'     => true,
                ]
            );

            Shooter::updateOrCreate(
                ['squad_id' => $squad->id, 'name' => $row['name']],
                [
                    'bib_number'        => 'P-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'user_id'           => $user->id,
                    'match_division_id' => $division->id,
                    'team_id'           => $team->id,
                    'sort_order'        => $index + 1,
                    'status'            => 'active',
                ]
            );
        }
    }

    /**
     * Forster is individual — no teams. One squad per heat, every shooter
     * registered as Major.
     */
    private function populateForsterRoster(ShootingMatch $match, array $shooters, MatchDivision $major): void
    {
        if (empty($shooters)) return;

        $squad = Squad::updateOrCreate(
            ['match_id' => $match->id, 'name' => 'Heat 1'],
            ['sort_order' => 1, 'max_capacity' => 30]
        );

        foreach ($shooters as $index => $row) {
            $user = $this->upsertDemoUser($row['name']);

            MatchRegistration::updateOrCreate(
                ['match_id' => $match->id, 'user_id' => $user->id],
                [
                    'caliber'           => $row['caliber'] ?? null,
                    'payment_status'    => 'confirmed',
                    'payment_reference' => sprintf('DEMO-FOR-%d-%d', $match->id, $user->id),
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
     * Deterministic, repeatable email for each demo shooter so re-running the
     * seeder reuses the same User and doesn't fan out new accounts.
     */
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
