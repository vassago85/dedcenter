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
 * Peregrine ELR at Alma on Saturday 7 November 2026.
 *
 * Roster is Shooters_Alma_Nov2026.xlsx. JD Els (jd.els1989@gmail.com) owns
 * the match. Three rows had no squad and land in "Unassigned".
 *
 * Idempotent. Run on the server:
 *   docker exec deadcenter-app php artisan db:seed --class=PeregrineAlma7Nov2026Seeder
 */
class PeregrineAlma7Nov2026Seeder extends Seeder
{
    public const MATCH_NAME = 'Peregrine ELR Challenge — Alma (7 Nov 2026)';

    public const OWNER_EMAIL = 'jd.els1989@gmail.com';

    /** Canonical Peregrine 5-station 4-rung ladder. */
    private const STATIONS = [
        ['label' => 'Warrior',       'sponsor' => 'Warrior',       'targets' => [594, 827, 916, 1679], 'color' => '#f97316'],
        ['label' => 'Brothers Arms', 'sponsor' => 'Brothers Arms', 'targets' => [531, 800, 1259, 2163], 'color' => '#eab308'],
        ['label' => 'Integrix',      'sponsor' => 'Integrix',      'targets' => [567, 958, 1617, 2090], 'color' => '#3b82f6'],
        ['label' => 'Delta Optics',  'sponsor' => 'Delta Optics',  'targets' => [620, 1190, 1234, 1502], 'color' => '#10b981'],
        ['label' => 'Zeiss Optics',  'sponsor' => 'Zeiss Optics',  'targets' => [715, 888, 1420, 1574], 'color' => '#ef4444'],
    ];

    private const MULTIPLIERS = [1.5, 1.25, 1.0];

    /**
     * Workbook order, so shooting order inside a squad is preserved.
     * Squad 0 is the sheet's blank squad column.
     *
     * @var array<int, array{squad:int, name:string, team:string, cartridge:string, class:string}>
     */
    private const ROSTER = [
        ['squad' => 1, 'name' => 'Brian Koen', 'team' => '321 Systems', 'cartridge' => '7 PRC', 'class' => 'Minor'],
        ['squad' => 1, 'name' => 'Francois Davel', 'team' => '321 Systems', 'cartridge' => '284 Win', 'class' => 'Minor'],
        ['squad' => 1, 'name' => 'Dries Bekker', 'team' => 'Broers met Roers', 'cartridge' => '7 RSAUM', 'class' => 'Minor'],
        ['squad' => 1, 'name' => 'Pieter Bekker', 'team' => 'Broers met Roers', 'cartridge' => '7 RSAUM', 'class' => 'Minor'],
        ['squad' => 1, 'name' => 'Floris Booysen', 'team' => 'Ellisras ELR 1', 'cartridge' => '338LMI', 'class' => 'Major'],
        ['squad' => 1, 'name' => 'Christo Jooste', 'team' => 'Ellisras ELR 1', 'cartridge' => '33XC', 'class' => 'Major'],
        ['squad' => 1, 'name' => 'Ed knibbs', 'team' => 'Impact Xtreme 2', 'cartridge' => '30SMF', 'class' => 'Minor'],
        ['squad' => 1, 'name' => 'Thys de Beer', 'team' => 'Impact Xtreme 2', 'cartridge' => '30SMF', 'class' => 'Major'],
        ['squad' => 1, 'name' => 'Arthur Coleby', 'team' => 'Impact Xtreme 3', 'cartridge' => '33XC', 'class' => 'Major'],
        ['squad' => 1, 'name' => 'JD Makkink', 'team' => 'Makkink 2', 'cartridge' => '308 Win', 'class' => 'Minor'],
        ['squad' => 1, 'name' => 'Kevin Makkink', 'team' => 'Makkink 2', 'cartridge' => '6.5', 'class' => 'Minor'],
        ['squad' => 1, 'name' => 'Jaco Venter', 'team' => 'Probeerders', 'cartridge' => '7 RSAUM', 'class' => 'Minor'],
        ['squad' => 1, 'name' => 'Ruben Aucamp', 'team' => 'Probeerders', 'cartridge' => '6.5CM', 'class' => 'Minor'],

        ['squad' => 2, 'name' => 'Carel coetzee', 'team' => 'Assegaai', 'cartridge' => '6,5x47', 'class' => 'Minor'],
        ['squad' => 2, 'name' => 'Jan Nel', 'team' => 'Assegaai', 'cartridge' => '284 Win', 'class' => 'Minor'],
        ['squad' => 2, 'name' => 'Henk Rykaart', 'team' => 'Brothers Arms', 'cartridge' => '7 PRC', 'class' => 'Minor'],
        ['squad' => 2, 'name' => 'Nicki Barnard', 'team' => 'Brothers Arms', 'cartridge' => '284 Shehane', 'class' => 'Minor'],
        ['squad' => 2, 'name' => 'Koos Duvenhagen', 'team' => 'Ellisras ELR 2', 'cartridge' => '7 PRC', 'class' => 'Minor'],
        ['squad' => 2, 'name' => 'Okkert van wyk', 'team' => 'Ellisras ELR 2', 'cartridge' => '7 Rem Mag', 'class' => 'Minor'],
        ['squad' => 2, 'name' => 'Bertie Zaaiman', 'team' => 'Hotshots', 'cartridge' => '6,5 PRC', 'class' => 'Minor'],
        ['squad' => 2, 'name' => 'Leon Swartz', 'team' => 'Hotshots', 'cartridge' => '7 PRC', 'class' => 'Minor'],
        ['squad' => 2, 'name' => 'Chris Cronje', 'team' => 'Huismoles', 'cartridge' => '7 RSAUM', 'class' => 'Minor'],
        ['squad' => 2, 'name' => 'Wiehahn Coetzee', 'team' => 'Huismoles', 'cartridge' => '6.5 Creedmoor', 'class' => 'Minor'],
        ['squad' => 2, 'name' => 'Antonie van Rensburg', 'team' => 'I-Shooter', 'cartridge' => '338 Lapua Mag', 'class' => 'Major'],
        ['squad' => 2, 'name' => 'Jaco Venter', 'team' => 'I-Shooter', 'cartridge' => '338 Lapua Mag', 'class' => 'Major'],
        ['squad' => 2, 'name' => 'Dewald Makkink', 'team' => 'Makkinkies', 'cartridge' => '7 PRC', 'class' => 'Minor'],
        ['squad' => 2, 'name' => 'Monica Makkink', 'team' => 'Makkinkies', 'cartridge' => '7 PRC', 'class' => 'Minor'],

        ['squad' => 3, 'name' => 'Carl Louw', 'team' => 'Biltong Ballistics', 'cartridge' => '7 RSAUM', 'class' => 'Minor'],
        ['squad' => 3, 'name' => 'De Waal Uys', 'team' => 'Biltong Ballistics', 'cartridge' => '6.5 Creedmoor', 'class' => 'Minor'],
        ['squad' => 3, 'name' => 'Brian Beeming', 'team' => 'Biltong Boelies', 'cartridge' => '7 PRC', 'class' => 'Minor'],
        ['squad' => 3, 'name' => 'Jason Odendaal', 'team' => 'Biltong Boelies', 'cartridge' => '6.5 Creedmoor', 'class' => 'Minor'],
        ['squad' => 3, 'name' => 'Danie Koch', 'team' => 'Lights Out', 'cartridge' => '300 Norma Mag', 'class' => 'Major'],
        ['squad' => 3, 'name' => 'JD Els', 'team' => 'Lights Out', 'cartridge' => '338 Lapua Mag', 'class' => 'Major'],
        ['squad' => 3, 'name' => 'Pieter Meyer', 'team' => 'Peregrine', 'cartridge' => '37XC', 'class' => 'Major'],
        ['squad' => 3, 'name' => 'Karien Els', 'team' => 'Peregrine', 'cartridge' => '300WSM', 'class' => 'Major'],
        ['squad' => 3, 'name' => 'Eddie Engelbrecht', 'team' => 'Out There Adventures', 'cartridge' => '300 PRC', 'class' => 'Major'],
        ['squad' => 3, 'name' => 'Henry King', 'team' => 'Out There Adventures', 'cartridge' => '300 PRC', 'class' => 'Major'],
        ['squad' => 3, 'name' => 'Christiaan Liebenberg', 'team' => 'Rookie Snipers', 'cartridge' => '7 RSAUM', 'class' => 'Minor'],
        ['squad' => 3, 'name' => 'Herman Boshoff', 'team' => 'Rookie Snipers', 'cartridge' => '338 Lapua Mag Improved', 'class' => 'Major'],

        ['squad' => 4, 'name' => 'Johan Coetzee', 'team' => 'Brothers Arms Peregrine', 'cartridge' => '375 CheyTac', 'class' => 'Major'],
        ['squad' => 4, 'name' => 'Rudi Barnard', 'team' => 'Brothers Arms Peregrine', 'cartridge' => '375 EnabELR', 'class' => 'Major'],
        ['squad' => 4, 'name' => 'Fred van der Westhuizen', 'team' => 'Dasher Smasher', 'cartridge' => '6 Dasher', 'class' => 'Minor'],
        ['squad' => 4, 'name' => 'Alex Pienaar', 'team' => 'Dasher Smasher', 'cartridge' => '6 Dasher', 'class' => 'Minor'],
        ['squad' => 4, 'name' => 'Christo Els', 'team' => 'Kettie & Klip', 'cartridge' => '284 Shehane', 'class' => 'Minor'],
        ['squad' => 4, 'name' => 'Zander Els', 'team' => 'Kettie & Klip', 'cartridge' => '6.5 Creedmoor', 'class' => 'Minor'],
        ['squad' => 4, 'name' => 'Daniel Bonthuys', 'team' => 'Land Rover', 'cartridge' => '7 PRC', 'class' => 'Minor'],
        ['squad' => 4, 'name' => 'Werner Bonthuys', 'team' => 'Land Rover', 'cartridge' => '7 RSAUM', 'class' => 'Minor'],
        ['squad' => 4, 'name' => 'Christo Duvenhage', 'team' => 'Panthers', 'cartridge' => '33XC', 'class' => 'Major'],
        ['squad' => 4, 'name' => 'Emilio Patricio', 'team' => 'Panthers', 'cartridge' => '7mm PRC', 'class' => 'Minor'],
        ['squad' => 4, 'name' => 'DW de Klerk', 'team' => 'Young Guns', 'cartridge' => '338 Lapua Mag', 'class' => 'Major'],
        ['squad' => 4, 'name' => 'Friedrich Schmidt', 'team' => 'Young guns', 'cartridge' => '375 CheyTac', 'class' => 'Major'],

        ['squad' => 5, 'name' => 'Koos Grobler', 'team' => 'Blazing PRCs', 'cartridge' => '300 PRC', 'class' => 'Major'],
        ['squad' => 5, 'name' => 'Richard Meissner', 'team' => 'Blazing PRCs', 'cartridge' => '7 PRC', 'class' => 'Minor'],
        ['squad' => 5, 'name' => 'Gert Loots', 'team' => 'DeadZero', 'cartridge' => '7 PRC', 'class' => 'Minor'],
        ['squad' => 5, 'name' => 'Werick Venter', 'team' => 'Deadzero', 'cartridge' => '7 PRC', 'class' => 'Minor'],
        ['squad' => 5, 'name' => 'Brendan Fike', 'team' => 'Impact Xtreme 1', 'cartridge' => '30SMF', 'class' => 'Major'],
        ['squad' => 5, 'name' => 'Theo Botha', 'team' => 'Impact Xtreme 1', 'cartridge' => '30SMF', 'class' => 'Major'],
        ['squad' => 5, 'name' => 'Albert Stoel', 'team' => 'Rooiwarm', 'cartridge' => '338 Lapua Mag', 'class' => 'Major'],
        ['squad' => 5, 'name' => 'Steven Coombs', 'team' => 'Rooiwarm', 'cartridge' => '7 PRC', 'class' => 'Minor'],
        ['squad' => 5, 'name' => 'Alwyn botha', 'team' => 'Slagpen', 'cartridge' => '7mm Prac', 'class' => 'Minor'],
        ['squad' => 5, 'name' => 'Thinus Botha', 'team' => 'Slagpen', 'cartridge' => '7mm Prac', 'class' => 'Minor'],
        ['squad' => 5, 'name' => 'Angelo Kordom', 'team' => 'Terrible Twins', 'cartridge' => '7mm Blaser Mag', 'class' => 'Minor'],
        ['squad' => 5, 'name' => 'Jimmy Erasmus', 'team' => 'Terrible Twins', 'cartridge' => '7mm Blaser Mag', 'class' => 'Minor'],

        ['squad' => 0, 'name' => 'Anton de Jager', 'team' => '', 'cartridge' => '338LM', 'class' => 'Major'],
        ['squad' => 0, 'name' => 'Jaco van der Merwe', 'team' => 'Rooikraal Long Rangers', 'cartridge' => '300 RUM', 'class' => 'Major'],
        ['squad' => 0, 'name' => 'Andre ten Haaf', 'team' => 'Rooikraal Long Rangers', 'cartridge' => '6.5SLR', 'class' => 'Minor'],
    ];

    public function run(): void
    {
        $owner = User::query()->whereRaw('LOWER(email) = ?', [self::OWNER_EMAIL])->first();
        if (! $owner) {
            $this->command?->error('No user with email '.self::OWNER_EMAIL.' — Alma match was not created.');

            return;
        }

        $org = $this->resolveOrg($owner);
        $season = Season::firstOrCreate(
            ['name' => 'Peregrine ELR Challenge 2026', 'organization_id' => $org->id],
            [
                'year' => 2026,
                'start_date' => '2026-03-07',
                'end_date' => '2026-12-31',
                'created_by' => $owner->id,
            ]
        );
        if ($season->end_date !== null && $season->end_date->lt('2026-11-07')) {
            $season->update(['end_date' => '2026-12-31']);
        }

        $match = ShootingMatch::updateOrCreate(
            ['name' => self::MATCH_NAME],
            [
                'date' => '2026-11-07',
                'location' => 'Alma',
                'province' => 'limpopo',
                'status' => MatchStatus::Ready,
                'scoring_type' => 'elr',
                'created_by' => $owner->id,
                'organization_id' => $org->id,
                'season_id' => $season->id,
                'scores_published' => false,
                'team_event' => true,
                'team_size' => 2,
                'public_bio' => 'Peregrine ELR at Alma on 7 November 2026. Five sponsored stations, Minor and Major, two-shooter teams.',
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

        $this->command?->info(sprintf(
            'Alma 7 Nov 2026 is match #%d, owned by %s (%s). %d shooters.',
            $match->id,
            $owner->name,
            $owner->email,
            count(self::ROSTER)
        ));
    }

    private function resolveOrg(User $owner): Organization
    {
        $org = Organization::firstOrCreate(
            ['slug' => 'peregrine-elr-challenge'],
            [
                'name' => 'Peregrine ELR Challenge',
                'description' => 'South African Extra Long Range team series. Five sponsored stations, four targets each, Minor and Major divisions, two-shooter pairs.',
                'type' => 'competition',
                'status' => 'active',
                'created_by' => $owner->id,
                'primary_color' => '#f59e0b',
                'secondary_color' => '#0f172a',
                'portal_enabled' => true,
                'portal_entitled' => true,
                'portal_ad_rights' => true,
                'best_of' => 3,
            ]
        );
        $org->admins()->syncWithoutDetaching([$owner->id => ['is_owner' => true]]);

        return $org;
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
            $squadName = $squadNo === 0 ? 'Unassigned' : "Squad {$squadNo}";
            if (! isset($squadCache[$squadNo])) {
                $squadCache[$squadNo] = Squad::updateOrCreate(
                    ['match_id' => $match->id, 'name' => $squadName],
                    ['sort_order' => $squadNo === 0 ? 99 : $squadNo, 'max_capacity' => 16]
                );
                $orderInSquad[$squadNo] = 0;
            }
            $squad = $squadCache[$squadNo];
            $orderInSquad[$squadNo]++;

            $division = strcasecmp($row['class'], 'Major') === 0 ? $major : $minor;
            $team = $this->resolveTeam($match, $row['team'], $teamCache);
            $user = $this->resolveShooterUser($owner, $row);

            MatchRegistration::updateOrCreate(
                ['match_id' => $match->id, 'user_id' => $user->id],
                [
                    'caliber' => $row['cartridge'],
                    'division_id' => $division->id,
                    'payment_status' => 'confirmed',
                    'payment_reference' => sprintf('PER-ALMA7-%d-%d', $match->id, $user->id),
                    'is_free_entry' => true,
                    'amount' => 0,
                ]
            );

            $payload = [
                'squad_id' => $squad->id,
                'name' => $row['name'],
                'bib_number' => 'A-'.str_pad((string) ($globalIndex + 1), 3, '0', STR_PAD_LEFT),
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
    private function resolveShooterUser(User $owner, array $row): User
    {
        if (strcasecmp(trim($row['name']), 'JD Els') === 0) {
            return $owner;
        }

        $slug = Str::slug($row['name'].' '.$row['team'].' '.$row['squad'], '.');
        $email = $slug.'@peregrine-alma-7nov2026.deadcenter.local';

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
