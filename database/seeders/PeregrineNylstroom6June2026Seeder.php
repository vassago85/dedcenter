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
 * Seeds the live Peregrine ELR match at Nylstroom on 6 June 2026 — the real
 * weekend round (not the standing test match). Roster comes directly from
 * `PeregrineELR6JuneCompetitors.xlsx` (5 squads, 68 shooters, two-shooter
 * teams, Minor/Major, per-shooter cartridge); station ladders + multipliers
 * mirror the canonical Peregrine setup so MDs can fine-tune per-station
 * distances on the day if the Nylstroom ranges differ.
 *
 * Idempotent — re-running updates in place without duplicating rows.
 *
 * Run with: php artisan db:seed --class=PeregrineNylstroom6June2026Seeder
 */
class PeregrineNylstroom6June2026Seeder extends Seeder
{
    /** Canonical Peregrine 5-station 4-rung ladder (MDs can edit per-station). */
    private const STATIONS = [
        ['label' => 'Warrior',       'sponsor' => 'Warrior',       'targets' => [594, 827, 916, 1679], 'color' => '#f97316'],
        ['label' => 'Brothers Arms', 'sponsor' => 'Brothers Arms', 'targets' => [531, 800, 1259, 2163], 'color' => '#eab308'],
        ['label' => 'Integrix',      'sponsor' => 'Integrix',      'targets' => [567, 958, 1617, 2090], 'color' => '#3b82f6'],
        ['label' => 'Delta Optics',  'sponsor' => 'Delta Optics',  'targets' => [620, 1190, 1234, 1502], 'color' => '#10b981'],
        ['label' => 'Zeiss Optics',  'sponsor' => 'Zeiss Optics',  'targets' => [715, 888, 1420, 1574], 'color' => '#ef4444'],
    ];

    private const MULTIPLIERS = [1.5, 1.25, 1.0];

    /**
     * Roster from PeregrineELR6JuneCompetitors.xlsx, in workbook order so
     * within-squad shooting order is preserved. Team names are normalised
     * (Terrible twins/Twins merged); cartridge "300 Norma MagMajor" cleaned
     * back to "300 Norma Mag" (the trailing "Major" was a data-entry slip —
     * class is already Major in the source row).
     *
     * @var array<int,array{squad:int,name:string,team:string,cartridge:string,class:string}>
     */
    private const ROSTER = [
        // Squad 1
        ['squad' => 1, 'name' => 'Brian Koen',          'team' => '321 Systems',              'cartridge' => '7 PRC',           'class' => 'Minor'],
        ['squad' => 1, 'name' => 'Francois Davel',      'team' => '321 Systems',              'cartridge' => '284 Win',         'class' => 'Minor'],
        ['squad' => 1, 'name' => 'Henk Rykaart',        'team' => 'Brothers Arms',            'cartridge' => '7 PRC',           'class' => 'Minor'],
        ['squad' => 1, 'name' => 'Nicki Barnard',       'team' => 'Brothers Arms',            'cartridge' => '7 Shehane',       'class' => 'Minor'],
        ['squad' => 1, 'name' => 'JP Ferreira',         'team' => 'Ellisras ELR 3',           'cartridge' => '7 PRC',           'class' => 'Minor'],
        ['squad' => 1, 'name' => 'Gerhard Lotter',      'team' => 'Ellisras ELR 3',           'cartridge' => '6.5x284',         'class' => 'Major'],
        ['squad' => 1, 'name' => 'Thys De Beer',        'team' => 'Impact Xtreme 2',          'cartridge' => '30 SMF',          'class' => 'Major'],
        ['squad' => 1, 'name' => 'Ed Knibbs',           'team' => 'Impact Xtreme 2',          'cartridge' => '30 SMF',          'class' => 'Major'],
        ['squad' => 1, 'name' => 'Henry King',          'team' => 'Out There Adventures ELR', 'cartridge' => '300 PRC',         'class' => 'Major'],
        ['squad' => 1, 'name' => 'Eddie Engelbrecht',   'team' => 'Out There Adventures ELR', 'cartridge' => '300 PRC',         'class' => 'Major'],
        ['squad' => 1, 'name' => 'Neehan Uys',          'team' => 'Team Pittstop',            'cartridge' => '300 Norma Mag',   'class' => 'Major'],
        ['squad' => 1, 'name' => 'Freddie Uys',         'team' => 'Team Pittstop',            'cartridge' => '338 LMI',         'class' => 'Major'],
        ['squad' => 1, 'name' => 'Anton De Jager',      'team' => 'Wind Likkewane',           'cartridge' => '7 PRC',           'class' => 'Minor'],
        ['squad' => 1, 'name' => 'Brian Beeming',       'team' => 'Wind Likkewane',           'cartridge' => '7 PRC',           'class' => 'Minor'],

        // Squad 2
        ['squad' => 2, 'name' => 'Mujaahid Abdulla',    'team' => 'Abdullas',                 'cartridge' => '7 PRC',           'class' => 'Minor'],
        ['squad' => 2, 'name' => 'Adbdul Abdulia',      'team' => 'Abdullas',                 'cartridge' => '7 PRC',           'class' => 'Minor'],
        ['squad' => 2, 'name' => 'Koos Grobler',        'team' => 'Cold Bore Crew',           'cartridge' => '300 PRC',         'class' => 'Major'],
        ['squad' => 2, 'name' => 'Henry Pienaar',       'team' => 'Cold Bore Crew',           'cartridge' => '6.5 Creedmoor',   'class' => 'Minor'],
        ['squad' => 2, 'name' => 'Marnus Kruger',       'team' => 'Ellisras ELR 4',           'cartridge' => '6.5 Creedmoor',   'class' => 'Minor'],
        ['squad' => 2, 'name' => 'Pikkie Grundlingh',   'team' => 'Ellisras ELR 4',           'cartridge' => '6.5 Creedmoor',   'class' => 'Minor'],
        ['squad' => 2, 'name' => 'Theo Botha',          'team' => 'Impact Xtreme 3',          'cartridge' => '300 Norma Mag',   'class' => 'Major'],
        ['squad' => 2, 'name' => 'Arthur Coleby',       'team' => 'Impact Xtreme 3',          'cartridge' => '33 XC',           'class' => 'Major'],
        ['squad' => 2, 'name' => 'Johan Coetzee',       'team' => 'Peregrine Brothers Arms',  'cartridge' => '375 CheyTac',     'class' => 'Major'],
        ['squad' => 2, 'name' => 'Rudi Barnard',        'team' => 'Peregrine Brothers Arms',  'cartridge' => '375 EnabELR',     'class' => 'Major'],
        ['squad' => 2, 'name' => 'Gerhard Malan',       'team' => 'Teamwork Dreamwork',       'cartridge' => '338 Lapua Magnum', 'class' => 'Major'],
        ['squad' => 2, 'name' => 'Pieter Meyer',        'team' => 'Teamwork Dreamwork',       'cartridge' => '300 PRC',         'class' => 'Major'],
        ['squad' => 2, 'name' => 'Antonie Van Rensburg', 'team' => 'Wind Readers',            'cartridge' => '338 Lapua Mag',   'class' => 'Major'],
        ['squad' => 2, 'name' => 'Christi Louw',        'team' => 'Wind Readers',             'cartridge' => '338 Lapua Mag',   'class' => 'Major'],

        // Squad 3
        ['squad' => 3, 'name' => 'Jan Nel',             'team' => 'Assegaai',                 'cartridge' => '284 Win',         'class' => 'Minor'],
        ['squad' => 3, 'name' => 'Carel Coetzee',       'team' => 'Assegaai',                 'cartridge' => '6.5x47 Lapua',    'class' => 'Minor'],
        ['squad' => 3, 'name' => 'Werick Venter',       'team' => 'Deadzero',                 'cartridge' => '7 PRC',           'class' => 'Minor'],
        ['squad' => 3, 'name' => 'Gert Loots',          'team' => 'Deadzero',                 'cartridge' => '7 PRC',           'class' => 'Minor'],
        ['squad' => 3, 'name' => 'Bertie Zaaiman',      'team' => 'Hot Shots',                'cartridge' => '6.5 PRC',         'class' => 'Minor'],
        ['squad' => 3, 'name' => 'Leon Swartz',         'team' => 'Hot Shots',                'cartridge' => '7 PRC',           'class' => 'Minor'],
        ['squad' => 3, 'name' => 'Jaco Harmse',         'team' => 'Impact Xtreme 4',          'cartridge' => '300 PRC',         'class' => 'Major'],
        ['squad' => 3, 'name' => 'Alan Hewetson',       'team' => 'Impact Xtreme 4',          'cartridge' => '30 SMF',          'class' => 'Major'],
        ['squad' => 3, 'name' => 'Jaco Venter',         'team' => 'Pro en Probeerder',        'cartridge' => '6.5 Creedmoor',   'class' => 'Minor'],
        ['squad' => 3, 'name' => 'Ruben Aucamp',        'team' => 'Pro en Probeerder',        'cartridge' => '6.5 Creedmoor',   'class' => 'Minor'],
        ['squad' => 3, 'name' => 'Angelo Kordom',       'team' => 'Terrible Twins',           'cartridge' => '7 Blaser Mag',    'class' => 'Minor'],
        ['squad' => 3, 'name' => 'Jimmy Erasmus',       'team' => 'Terrible Twins',           'cartridge' => '7 Blaser Mag',    'class' => 'Minor'],
        ['squad' => 3, 'name' => 'Dw De Klerk',         'team' => 'Young Guns',               'cartridge' => '375 CheyTac',     'class' => 'Major'],
        ['squad' => 3, 'name' => 'Chané Jacobsohn',     'team' => 'Young Guns',               'cartridge' => '7 RSAUM',         'class' => 'Minor'],

        // Squad 4
        ['squad' => 4, 'name' => 'Carl Louw',           'team' => 'Biltong Ballistics',       'cartridge' => '7 RSAUM',         'class' => 'Minor'],
        ['squad' => 4, 'name' => 'Dewaal Uys',          'team' => 'Biltong Ballistics',       'cartridge' => '6.5 Creedmoor',   'class' => 'Minor'],
        ['squad' => 4, 'name' => 'Floris Booysen',      'team' => 'Ellisras ELR 1',           'cartridge' => '338 Lapua Mag',   'class' => 'Major'],
        ['squad' => 4, 'name' => 'Arno V Niekerk',      'team' => 'Ellisras ELR 1',           'cartridge' => '338 Lapua Mag',   'class' => 'Major'],
        ['squad' => 4, 'name' => 'Chris Cronje',        'team' => 'Huismoles',                'cartridge' => '7 RSAUM',         'class' => 'Minor'],
        ['squad' => 4, 'name' => 'Wiehahn Coetzee',     'team' => 'Huismoles',                'cartridge' => '6.5 Creedmoor',   'class' => 'Minor'],
        ['squad' => 4, 'name' => 'Danie Koch',          'team' => 'Lights Out',               'cartridge' => '338 Lapua Mag',   'class' => 'Major'],
        ['squad' => 4, 'name' => 'JD Els',              'team' => 'Lights Out',               'cartridge' => '338 Lapua Mag',   'class' => 'Major'],
        ['squad' => 4, 'name' => 'Vernon Harms',        'team' => 'Trigger Titans',           'cartridge' => '7 PRC',           'class' => 'Minor'],
        ['squad' => 4, 'name' => 'Dirk Pio',            'team' => 'Trigger Titans',           'cartridge' => '6.5 PRC',         'class' => 'Minor'],
        ['squad' => 4, 'name' => 'Andre vd Westhuizen', 'team' => 'Veld Snipers',             'cartridge' => '6.5 PRC',         'class' => 'Minor'],
        ['squad' => 4, 'name' => 'Willem vd Nest',      'team' => 'Veld Snipers',             'cartridge' => '300 Norma Mag',   'class' => 'Major'],
        ['squad' => 4, 'name' => 'Haroon Hafajee',      'team' => 'G&T Outdoor',              'cartridge' => '6.5 Creedmoor',   'class' => 'Minor'],
        ['squad' => 4, 'name' => 'Muawiya Hafajee',     'team' => 'G&T Outdoor',              'cartridge' => '6.5 Creedmoor',   'class' => 'Minor'],

        // Squad 5
        ['squad' => 5, 'name' => 'Dries Bekker',        'team' => 'Broers met Roers',         'cartridge' => '7 RSAUM',         'class' => 'Minor'],
        ['squad' => 5, 'name' => 'Pieter-Bekker',       'team' => 'Broers met Roers',         'cartridge' => '7 RSAUM',         'class' => 'Minor'],
        ['squad' => 5, 'name' => 'Okkert Van Wyk',      'team' => 'Ellisras ELR 2',           'cartridge' => '7 Rem Mag',       'class' => 'Minor'],
        ['squad' => 5, 'name' => 'Koos Duvenhage',      'team' => 'Ellisras ELR 2',           'cartridge' => '7 PRC',           'class' => 'Minor'],
        ['squad' => 5, 'name' => 'Brendan Fike',        'team' => 'Impact Xtreme 1',          'cartridge' => '30 SMF',          'class' => 'Major'],
        ['squad' => 5, 'name' => 'Shaun Flink',         'team' => 'Impact Xtreme 1',          'cartridge' => '30 SMF',          'class' => 'Major'],
        ['squad' => 5, 'name' => 'Monica Makkink',      'team' => 'Makkinkies',               'cartridge' => '7 PRC',           'class' => 'Minor'],
        ['squad' => 5, 'name' => 'Dewald Makkink',      'team' => 'Makkinkies',               'cartridge' => '338 Lapua Mag',   'class' => 'Major'],
        ['squad' => 5, 'name' => 'Herman Boshoff',      'team' => 'Rookie Snipers',           'cartridge' => '338 LMI',         'class' => 'Major'],
        ['squad' => 5, 'name' => 'Christiaan Liebenberg', 'team' => 'Rookie Snipers',         'cartridge' => '7 RSAUM',         'class' => 'Minor'],
        ['squad' => 5, 'name' => 'Albert Stoel',        'team' => 'Slagpen',                  'cartridge' => '338 Lapua Mag',   'class' => 'Major'],
        ['squad' => 5, 'name' => 'Steven Coombs',       'team' => 'Slagpen',                  'cartridge' => '7 PRC',           'class' => 'Minor'],
    ];

    public function run(): void
    {
        $admin = User::where('email', 'admin@deadcenter.co.za')->first()
            ?? User::where('role', 'owner')->first();

        if (! $admin) {
            $this->command?->warn('No admin user found — skipping PeregrineNylstroom6June2026Seeder.');
            return;
        }

        $org = $this->resolveOrg($admin);
        $season = Season::firstOrCreate(
            ['name' => 'Peregrine ELR Challenge 2026', 'organization_id' => $org->id],
            [
                'year'       => 2026,
                'start_date' => '2026-03-07',
                'end_date'   => '2026-10-31',
                'created_by' => $admin->id,
            ]
        );

        $match = ShootingMatch::updateOrCreate(
            ['name' => 'Peregrine ELR Challenge — Nylstroom (6 Jun 2026)'],
            [
                'date'             => '2026-06-06',
                'location'         => 'Nylstroom',
                'province'         => 'limpopo',
                'status'           => MatchStatus::Active,
                'scoring_type'     => 'elr',
                'created_by'       => $admin->id,
                'organization_id'  => $org->id,
                'season_id'        => $season->id,
                'scores_published' => false,
                'team_event'       => true,
                'team_size'        => 2,
                'public_bio'       => 'Live Peregrine ELR round at Nylstroom — five sponsored stations, two divisions (Minor/Major), two-shooter teams. 12-minute team timer, three shots per gong.',
            ]
        );

        $profile = ElrScoringProfile::updateOrCreate(
            ['match_id' => $match->id, 'name' => 'Peregrine 3-shot ramp'],
            ['multipliers' => self::MULTIPLIERS]
        );

        $matchUpdate = [
            'elr_scoring_profile_id'      => $profile->id,
            'elr_engagement_mode'         => ElrEngagementMode::TeamSequence,
            'elr_shots_per_target'        => 3,
            'elr_team_time_limit_seconds' => 720,
        ];
        if (Schema::hasColumn('matches', 'elr_distance_based_scoring')) {
            $matchUpdate['elr_distance_based_scoring'] = true;
        }
        if (Schema::hasColumn('matches', 'alternate_scoring')) {
            $matchUpdate['alternate_scoring'] = false;
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
        $this->populateRoster($match, $minor, $major);

        $this->command?->info(sprintf(
            'Seeded Peregrine Nylstroom (6 Jun 2026): match #%d, 5 stations, %d shooters across 5 squads.',
            $match->id,
            count(self::ROSTER)
        ));
    }

    private function resolveOrg(User $admin): Organization
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

        $jd = User::where('email', 'Jd.els1989@gmail.com')->first();
        if ($jd) {
            $org->admins()->syncWithoutDetaching([$jd->id => ['is_owner' => true]]);
        }

        return $org;
    }

    private function seedStations(ShootingMatch $match, ElrScoringProfile $profile): void
    {
        foreach (self::STATIONS as $idx => $s) {
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

            foreach ($s['targets'] as $i => $distance) {
                ElrTarget::updateOrCreate(
                    ['elr_stage_id' => $stage->id, 'name' => 'T' . ($i + 1)],
                    [
                        'distance_m'          => $distance,
                        'base_points'         => $distance,
                        'max_shots'           => 3,
                        'must_hit_to_advance' => false,
                        'sort_order'          => $i + 1,
                    ]
                );
            }
        }
    }

    /**
     * Persist Minor (gongs 1-3) and Major (gongs 2-4) ranges on every stage
     * via ElrTeamRangeService so the gong-range editor and elr_division_targets
     * pivot stay in sync. Falls back to the legacy direct pivot mapping if
     * the ranges table isn't migrated yet.
     */
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

    private function populateRoster(ShootingMatch $match, MatchDivision $minor, MatchDivision $major): void
    {
        $squadCache  = [];
        $teamCache   = [];
        $orderInSquad = [];

        foreach (self::ROSTER as $globalIndex => $row) {
            $squadNo = $row['squad'];
            if (! isset($squadCache[$squadNo])) {
                $squadCache[$squadNo] = Squad::updateOrCreate(
                    ['match_id' => $match->id, 'name' => "Squad $squadNo"],
                    ['sort_order' => $squadNo, 'max_capacity' => 16]
                );
                $orderInSquad[$squadNo] = 0;
            }
            $squad = $squadCache[$squadNo];
            $orderInSquad[$squadNo]++;

            $division = strcasecmp($row['class'], 'Major') === 0 ? $major : $minor;

            $teamKey = $match->id . '|' . $row['team'];
            if (! isset($teamCache[$teamKey])) {
                $teamCache[$teamKey] = Team::updateOrCreate(
                    ['match_id' => $match->id, 'name' => $row['team']],
                    ['max_size' => 2, 'sort_order' => count($teamCache) + 1]
                );
            }
            $team = $teamCache[$teamKey];

            $user = $this->upsertUser($row['name']);

            MatchRegistration::updateOrCreate(
                ['match_id' => $match->id, 'user_id' => $user->id],
                [
                    'caliber'           => $row['cartridge'],
                    'payment_status'    => 'confirmed',
                    'payment_reference' => sprintf('PER-NYL-%d-%d', $match->id, $user->id),
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

    private function upsertUser(string $fullName): User
    {
        $slug = Str::slug($fullName, '.');
        $email = $slug . '@peregrine-nylstroom.deadcenter.local';

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
