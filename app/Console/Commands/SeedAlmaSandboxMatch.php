<?php

namespace App\Console\Commands;

use App\Enums\ElrEngagementMode;
use App\Enums\ElrStageType;
use App\Enums\MatchStatus;
use App\Models\ElrScoringProfile;
use App\Models\ElrShot;
use App\Models\ElrStage;
use App\Models\ElrStageDivisionRange;
use App\Models\ElrTarget;
use App\Models\ElrTeamStageEntry;
use App\Models\MatchDivision;
use App\Models\MatchRegistration;
use App\Models\Organization;
use App\Models\Season;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Sandbox clone of the Peregrine ELR Alma match for JD Els to experiment on.
 *
 * Creates an organisation + match named with a clear "Sandbox" suffix, owned
 * by jd.els1989@gmail.com. Twelve fake shooters across two squads, Minor and
 * Major, two-shooter teams, the Peregrine 5-station ladder.
 *
 * Idempotent and resettable:
 *   docker exec deadcenter-app php artisan match:seed-alma-sandbox          # create / refresh
 *   docker exec deadcenter-app php artisan match:seed-alma-sandbox --fresh  # wipe match data + rebuild
 */
class SeedAlmaSandboxMatch extends Command
{
    protected $signature = 'match:seed-alma-sandbox {--fresh : Delete shots, stage entries, shooters, teams and squads before rebuilding.}';

    protected $description = 'Create or refresh a Peregrine-shaped sandbox match for JD to practise on.';

    public const OWNER_EMAIL = 'jd.els1989@gmail.com';

    public const MATCH_NAME = 'Peregrine ELR — Sandbox (Test Match)';

    private const STATIONS = [
        ['label' => 'Warrior',       'sponsor' => 'Warrior',       'targets' => [594, 827, 916, 1679], 'color' => '#f97316'],
        ['label' => 'Brothers Arms', 'sponsor' => 'Brothers Arms', 'targets' => [531, 800, 1259, 2163], 'color' => '#eab308'],
        ['label' => 'Integrix',      'sponsor' => 'Integrix',      'targets' => [567, 958, 1617, 2090], 'color' => '#3b82f6'],
        ['label' => 'Delta Optics',  'sponsor' => 'Delta Optics',  'targets' => [620, 1190, 1234, 1502], 'color' => '#10b981'],
        ['label' => 'Zeiss Optics',  'sponsor' => 'Zeiss Optics',  'targets' => [715, 888, 1420, 1574], 'color' => '#ef4444'],
    ];

    private const MULTIPLIERS = [1.5, 1.25, 1.0];

    /**
     * Fictional, Peregrine-flavoured roster. Twelve shooters, two squads, six
     * teams of two. The "Sandbox" team pairs with "Sandbox 2" so JD has an
     * easy one to rename or shuffle.
     *
     * @var array<int, array{squad:int, name:string, team:string, cartridge:string, class:string}>
     */
    private const ROSTER = [
        ['squad' => 1, 'name' => 'Sandbox Shooter One',   'team' => 'Sandbox Pair',   'cartridge' => '7 PRC',           'class' => 'Minor'],
        ['squad' => 1, 'name' => 'Sandbox Shooter Two',   'team' => 'Sandbox Pair',   'cartridge' => '284 Win',         'class' => 'Minor'],
        ['squad' => 1, 'name' => 'Range Rookie One',      'team' => 'Range Rookies',  'cartridge' => '6.5 Creedmoor',   'class' => 'Minor'],
        ['squad' => 1, 'name' => 'Range Rookie Two',      'team' => 'Range Rookies',  'cartridge' => '6.5 Creedmoor',   'class' => 'Minor'],
        ['squad' => 1, 'name' => 'Big Bore One',          'team' => 'Big Bore Boys',  'cartridge' => '338 Lapua Mag',   'class' => 'Major'],
        ['squad' => 1, 'name' => 'Big Bore Two',          'team' => 'Big Bore Boys',  'cartridge' => '338 Lapua Mag',   'class' => 'Major'],
        ['squad' => 2, 'name' => 'Peregrine Tester One',  'team' => 'Test Flight',    'cartridge' => '7 RSAUM',         'class' => 'Minor'],
        ['squad' => 2, 'name' => 'Peregrine Tester Two',  'team' => 'Test Flight',    'cartridge' => '7 RSAUM',         'class' => 'Minor'],
        ['squad' => 2, 'name' => 'Wind Reader One',       'team' => 'Wind Readers',   'cartridge' => '30 SMF',          'class' => 'Major'],
        ['squad' => 2, 'name' => 'Wind Reader Two',       'team' => 'Wind Readers',   'cartridge' => '33 XC',           'class' => 'Major'],
        ['squad' => 2, 'name' => 'Cold Bore One',         'team' => 'Cold Bore',      'cartridge' => '300 Norma Mag',   'class' => 'Major'],
        ['squad' => 2, 'name' => 'Cold Bore Two',         'team' => 'Cold Bore',      'cartridge' => '300 PRC',         'class' => 'Major'],
    ];

    public function handle(): int
    {
        $owner = User::query()->whereRaw('LOWER(email) = ?', [self::OWNER_EMAIL])->first();
        if (! $owner) {
            $this->error('No user with email '.self::OWNER_EMAIL.' — sandbox match was not created.');

            return self::FAILURE;
        }

        $org = $this->resolveOrg($owner);
        $season = $this->resolveSeason($org, $owner);

        $match = ShootingMatch::updateOrCreate(
            ['name' => self::MATCH_NAME],
            [
                'date' => now()->toDateString(),
                'location' => 'Sandbox Range',
                'province' => 'limpopo',
                'status' => MatchStatus::Ready,
                'scoring_type' => 'elr',
                'created_by' => $owner->id,
                'organization_id' => $org->id,
                'season_id' => $season->id,
                'scores_published' => false,
                'team_event' => true,
                'team_size' => 2,
                'public_bio' => 'Sandbox copy of the Peregrine ELR Alma setup. Safe to score, correct, move shooters, change divisions, and reset — nothing here feeds live results.',
            ]
        );

        $profile = ElrScoringProfile::updateOrCreate(
            ['match_id' => $match->id, 'name' => 'Peregrine 3-shot ramp'],
            ['multipliers' => self::MULTIPLIERS]
        );

        $matchUpdate = [
            'elr_scoring_profile_id' => $profile->id,
            'elr_engagement_mode' => ElrEngagementMode::TeamSequence,
            'elr_shots_per_target' => 3,
            'elr_team_time_limit_seconds' => 720,
            'created_by' => $owner->id,
        ];
        if (Schema::hasColumn('matches', 'elr_distance_based_scoring')) {
            $matchUpdate['elr_distance_based_scoring'] = true;
        }
        $match->update($matchUpdate);

        if ($this->option('fresh')) {
            $this->wipeMatchData($match);
        }

        $this->seedStations($match, $profile);

        $minor = MatchDivision::updateOrCreate(
            ['match_id' => $match->id, 'name' => 'Minor'],
            ['sort_order' => 1, 'description' => 'Minor — engages gongs 1, 2 and 3 on every station.']
        );
        $major = MatchDivision::updateOrCreate(
            ['match_id' => $match->id, 'name' => 'Major'],
            ['sort_order' => 2, 'description' => 'Major — engages gongs 2, 3 and 4 on every station.']
        );

        $this->mapGongRanges($match, $minor, $major);
        $this->populateRoster($match, $owner, $minor, $major);

        $this->info(sprintf(
            'Sandbox match #%d ready for %s (%s). %d shooters, status: %s.',
            $match->id,
            $owner->name,
            $owner->email,
            count(self::ROSTER),
            $match->status->label(),
        ));

        return self::SUCCESS;
    }

    private function resolveOrg(User $owner): Organization
    {
        $org = Organization::firstOrCreate(
            ['slug' => 'peregrine-elr-sandbox'],
            [
                'name' => 'Peregrine ELR — Sandbox',
                'description' => 'Private sandbox org for the Peregrine test match. Not a public competition.',
                'type' => 'competition',
                'status' => 'active',
                'created_by' => $owner->id,
                'primary_color' => '#64748b',
                'secondary_color' => '#0f172a',
                'portal_enabled' => false,
                'portal_entitled' => false,
                'portal_ad_rights' => false,
                'best_of' => 1,
            ]
        );
        $org->admins()->syncWithoutDetaching([$owner->id => ['is_owner' => true]]);

        return $org;
    }

    private function resolveSeason(Organization $org, User $owner): Season
    {
        $year = (int) now()->year;

        return Season::firstOrCreate(
            ['name' => 'Peregrine Sandbox '.$year, 'organization_id' => $org->id],
            [
                'year' => $year,
                'start_date' => now()->startOfYear()->toDateString(),
                'end_date' => now()->endOfYear()->toDateString(),
                'created_by' => $owner->id,
            ]
        );
    }

    private function wipeMatchData(ShootingMatch $match): void
    {
        DB::transaction(function () use ($match) {
            $stageIds = $match->elrStages()->pluck('id');
            $targetIds = ElrTarget::whereIn('elr_stage_id', $stageIds)->pluck('id');

            ElrShot::whereIn('elr_target_id', $targetIds)->delete();
            ElrTeamStageEntry::whereIn('elr_stage_id', $stageIds)->delete();
            if (Schema::hasTable('elr_division_targets')) {
                DB::table('elr_division_targets')->whereIn('elr_target_id', $targetIds)->delete();
            }
            ElrStageDivisionRange::whereIn('elr_stage_id', $stageIds)->delete();

            foreach ($match->squads()->with('shooters')->get() as $squad) {
                $squad->shooters()->delete();
                $squad->delete();
            }
            $match->teams()->delete();
            $match->registrations()->delete();
        });

        $this->line('Cleared existing sandbox shooters, teams, squads and shots.');
    }

    private function seedStations(ShootingMatch $match, ElrScoringProfile $profile): void
    {
        foreach (self::STATIONS as $idx => $s) {
            $stagePayload = [
                'stage_type' => ElrStageType::Static,
                'elr_scoring_profile_id' => $profile->id,
                'sort_order' => $idx + 1,
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

            foreach ($s['targets'] as $i => $distance) {
                ElrTarget::updateOrCreate(
                    ['elr_stage_id' => $stage->id, 'name' => 'T'.($i + 1)],
                    [
                        'distance_m' => $distance,
                        'base_points' => $distance,
                        'max_shots' => 3,
                        'must_hit_to_advance' => false,
                        'sort_order' => $i + 1,
                    ]
                );
            }
        }
    }

    private function mapGongRanges(ShootingMatch $match, MatchDivision $minor, MatchDivision $major): void
    {
        if (! Schema::hasTable('elr_stage_division_ranges')) {
            $this->mapDivisionTargets($match, $minor, [1, 2, 3]);
            $this->mapDivisionTargets($match, $major, [2, 3, 4]);

            return;
        }

        $rangeService = app(\App\Services\Scoring\ElrTeamRangeService::class);
        foreach ($match->elrStages()->orderBy('sort_order')->get() as $stage) {
            $rangeService->saveRange($stage, $minor->id, 1, 3);
            $rangeService->saveRange($stage, $major->id, 2, 4);
        }
    }

    /** @param  array<int>  $positions */
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

    private function populateRoster(ShootingMatch $match, User $owner, MatchDivision $minor, MatchDivision $major): void
    {
        $squadCache = [];
        $teamCache = [];
        $orderInSquad = [];

        foreach (self::ROSTER as $globalIndex => $row) {
            $squadNo = $row['squad'];
            if (! isset($squadCache[$squadNo])) {
                $squadCache[$squadNo] = Squad::updateOrCreate(
                    ['match_id' => $match->id, 'name' => 'Squad '.$squadNo],
                    ['sort_order' => $squadNo, 'max_capacity' => 12]
                );
                $orderInSquad[$squadNo] = 0;
            }
            $squad = $squadCache[$squadNo];
            $orderInSquad[$squadNo]++;

            $division = strcasecmp($row['class'], 'Major') === 0 ? $major : $minor;
            $team = $this->resolveTeam($match, $row['team'], $teamCache);
            $user = $this->resolveShooterUser($row);

            MatchRegistration::updateOrCreate(
                ['match_id' => $match->id, 'user_id' => $user->id],
                [
                    'caliber' => $row['cartridge'],
                    'division_id' => $division->id,
                    'payment_status' => 'confirmed',
                    'payment_reference' => sprintf('PER-SAND-%d-%d', $match->id, $user->id),
                    'is_free_entry' => true,
                    'amount' => 0,
                ]
            );

            $payload = [
                'squad_id' => $squad->id,
                'name' => $row['name'],
                'bib_number' => 'S-'.str_pad((string) ($globalIndex + 1), 3, '0', STR_PAD_LEFT),
                'user_id' => $user->id,
                'match_division_id' => $division->id,
                'team_id' => $team?->id,
                'sort_order' => $orderInSquad[$squadNo],
                'status' => 'active',
            ];

            $shooter = Shooter::query()
                ->where('user_id', $user->id)
                ->whereHas('squad', fn ($q) => $q->where('match_id', $match->id))
                ->first();

            if ($shooter) {
                $shooter->update($payload);
            } else {
                Shooter::create($payload);
            }
        }
    }

    /** @param  array<string, Team>  $teamCache */
    private function resolveTeam(ShootingMatch $match, string $raw, array &$teamCache): ?Team
    {
        $display = preg_replace('/\s+/', ' ', trim($raw)) ?? '';
        if ($display === '') {
            return null;
        }
        $key = strtolower($display);
        if (isset($teamCache[$key])) {
            return $teamCache[$key];
        }

        $existing = $match->teams()->get()->first(
            fn (Team $team) => strtolower(preg_replace('/\s+/', ' ', $team->name) ?? '') === $key
        );
        if (! $existing) {
            $existing = $match->teams()->create([
                'name' => $display,
                'max_size' => 2,
                'sort_order' => $match->teams()->count() + 1,
            ]);
        }
        $teamCache[$key] = $existing;

        return $existing;
    }

    /** @param  array{squad:int, name:string, team:string, cartridge:string, class:string}  $row */
    private function resolveShooterUser(array $row): User
    {
        $slug = Str::slug($row['name'], '.');
        $email = $slug.'@peregrine-sandbox.deadcenter.local';

        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $row['name'],
                'password' => Hash::make(Str::random(24)),
                'role' => 'shooter',
            ]
        );
    }
}
