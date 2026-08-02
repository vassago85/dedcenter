<?php

namespace Database\Seeders;

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\MatchCategory;
use App\Models\MatchDivision;
use App\Models\Organization;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\StageTarget;
use App\Models\TargetSet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * PPRC Centerfire Club Match — Saturday, 1 August 2026 — Legends Adventure
 * Farm, Rayton.
 *
 * PRS scoring: 1 point per impact, no partial points, match total 42.
 * Par time 105 s on every stage. Stage 4 ("The Piano") is the timed
 * tie-breaker — equal impact totals are ranked by lower Stage 4 time, so it
 * is the only stage flagged is_timed_stage / is_tiebreaker.
 *
 * The DeadCenter PRS model treats each Gong as a single shot slot in the
 * stage's shot sequence, so a stage's total_shots always equals its gong
 * count. That maps the course of fire exactly:
 *   Stage 1 ladder (1+2+3+4)         = 10 shots  → 10 gongs
 *   Stages 2–5                       =  8 shots  →  8 gongs each
 *   Match total                      = 42 shots
 * Each gong carries the physical target it represents (shape, mm size,
 * distance, mil) so the on-phone stage reference shows the real COF.
 *
 * Roster: 30 shooters across 4 squads (Coriolis, Mirage, Parallax, Spin
 * Drift), squadded exactly per the Impact squadding screenshot. Each
 * shooter's division comes from the entries CSV (Open / Factory / Limited /
 * Senior / Ladies) and links to an existing DeadCenter account by email
 * where one exists. No categories are seeded — the export has none.
 *
 * Re-running wipes and rebuilds this match's divisions, squads, roster and
 * target sets so the seeder is idempotent. It never touches scores.
 */
class PprcCenterfireLegends20260801Seeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'paul@charsley.co.za')->first()
            ?? User::where('role', 'owner')->first();

        if (! $owner) {
            $this->command?->error('No paul@charsley.co.za or owner user found; aborting.');
            return;
        }

        $org = Organization::firstOrCreate(
            ['slug' => 'pretoria-precision-rifle-club'],
            [
                'name' => 'Pretoria Precision Rifle Club',
                'type' => 'club',
                'status' => 'active',
                'created_by' => $owner->id,
                'province' => 'Gauteng',
            ]
        );

        $org->admins()->syncWithoutDetaching([
            $owner->id => [
                'is_owner' => true,
                'is_match_director' => true,
                'is_range_officer' => true,
                'is_shooter' => false,
            ],
        ]);

        $matchName = 'PPRC Centerfire Club Match — 1 August 2026';

        DB::transaction(function () use ($owner, $org, $matchName) {
            $match = ShootingMatch::firstOrNew([
                'organization_id' => $org->id,
                'name' => $matchName,
            ]);

            $isNew = ! $match->exists;

            $match->date = '2026-08-01';
            $match->location = 'Legends Adventure Farm, Rayton';
            $match->status = MatchStatus::Draft;
            $match->scoring_type = 'prs';
            $match->scores_published = false;
            $match->concurrent_relays = 1;
            $match->max_squad_size = 8;
            $match->entry_fee = 0;
            $match->self_squadding_enabled = false;
            $match->royal_flush_enabled = false;
            $match->side_bet_enabled = false;
            $match->notes = 'PPRC Centerfire Club Match at Legends Adventure Farm, Rayton. PRS scoring — 1 point per impact, no partial points, match total 42. Par time 105 s on all stages. Stage 4 "The Piano" is the timed tie-breaker: equal impact totals are ranked by lower Stage 4 time. Roster imported from Impact squadding.';
            $match->created_by = $owner->id;
            $match->save();

            $this->command?->info(($isNew ? 'Created' : 'Updated')." match [{$match->id}] {$match->name}.");

            // Wipe the roster + classification so re-seeding is idempotent.
            // Order matters: shooters FK both squads and divisions, so they
            // must go before either is removed.
            foreach ($match->squads()->get() as $existingSquad) {
                $existingSquad->shooters()->delete();
            }
            $match->squads()->delete();
            MatchDivision::where('match_id', $match->id)->delete();
            MatchCategory::where('match_id', $match->id)->delete();

            // Divisions exactly as registered — the entries CSV is the
            // authoritative source (the squadding screenshot's division
            // labels are stale). Categories are intentionally left empty:
            // the export carries no category data.
            $divisionIds = [];
            foreach (['Open' => 1, 'Factory' => 2, 'Limited' => 3, 'Senior' => 4, 'Ladies' => 5] as $name => $order) {
                $divisionIds[$name] = MatchDivision::create([
                    'match_id' => $match->id,
                    'name' => $name,
                    'sort_order' => $order,
                ])->id;
            }

            // Wipe & rebuild stages so re-seeding is idempotent.
            foreach ($match->targetSets as $existing) {
                $existing->gongs()->delete();
                $existing->delete();
            }

            foreach ($this->stages() as $i => $stage) {
                $ts = TargetSet::create([
                    'match_id' => $match->id,
                    'label' => $stage['label'],
                    'distance_meters' => 0,
                    'distance_multiplier' => 1,
                    'sort_order' => $i + 1,
                    'stage_number' => $i + 1,
                    'total_shots' => count($stage['gongs']),
                    'par_time_seconds' => 105.0,
                    'is_timed_stage' => $stage['timed'] ?? false,
                    'is_tiebreaker' => $stage['tiebreaker'] ?? false,
                    'notes' => $stage['brief'],
                ]);

                foreach ($stage['gongs'] as $j => $g) {
                    Gong::create([
                        'target_set_id' => $ts->id,
                        'number' => $j + 1,
                        'label' => $g['label'],
                        'multiplier' => 1.00,
                        'distance_meters' => $g['dist'],
                        'target_size_mm' => $g['mm'],
                        'target_size' => "{$g['mm']} mm {$g['shape']} @ {$g['dist']} m · {$g['mil']} mil",
                    ]);

                    // Mirror each shot slot as a StageTarget so the scoring
                    // app's per-shot "Target Info" reference shows the real COF
                    // (target name, distance, size, mil) on the scorer's phone.
                    StageTarget::create([
                        'stage_id' => $ts->id,
                        'sequence_number' => $j + 1,
                        'target_name' => $g['label'],
                        'target_reference' => $g['shape'],
                        'distance_meters' => $g['dist'],
                        'target_size_mm' => $g['mm'],
                        'target_size_mrad' => $g['mil'],
                    ]);
                }
            }

            $total = $match->targetSets()->sum('total_shots');
            $this->command?->info("5 stages seeded, {$total} shots total (Stage 4 = timed tie-breaker).");

            // Squads + roster. Squad layout comes from the Impact squadding
            // screenshot; each shooter's division comes from the entries CSV.
            // Where a DeadCenter account already exists for the shooter's
            // registered email we link it, so scoring, claims and post-match
            // reports resolve to the real user; walk-ins stay unlinked.
            $linked = 0;
            foreach ($this->roster() as $squadIndex => $squadData) {
                $squad = Squad::create([
                    'match_id' => $match->id,
                    'name' => $squadData['name'],
                    'sort_order' => $squadIndex + 1,
                    'max_capacity' => 8,
                ]);

                foreach ($squadData['shooters'] as $sIndex => $sh) {
                    $userId = User::whereRaw('LOWER(email) = ?', [strtolower($sh['email'])])->value('id');
                    if ($userId) {
                        $linked++;
                    } else {
                        // Every shooter must resolve to a user so podium /
                        // achievement badges have somewhere to land. When
                        // no real account matches, attach an import
                        // placeholder — it surfaces as "awaiting claim" and
                        // transfers to the real profile once claimed.
                        $userId = $this->placeholderUser($match, $sh['name'], $squadIndex * 100 + $sIndex)->id;
                    }

                    Shooter::create([
                        'squad_id' => $squad->id,
                        'name' => $sh['name'],
                        'match_division_id' => $divisionIds[$sh['division']] ?? null,
                        'user_id' => $userId,
                        'sort_order' => $sIndex + 1,
                        'status' => 'active',
                    ]);
                }
            }

            $shooterCount = Shooter::whereHas('squad', fn ($q) => $q->where('match_id', $match->id))->count();
            $this->command?->info("{$shooterCount} shooters seeded across {$match->squads()->count()} squads ({$linked} linked to existing accounts).");
            $this->command?->info("Scoreboard URL: /scoreboard/{$match->id}");
        });
    }

    /**
     * Create (or reuse) an import placeholder account for a shooter with no
     * real DeadCenter login yet. Uses the platform-standard @import.invalid
     * email so isImportPlaceholder() reports true, the scoreboard keeps
     * showing "Claim your result", and any badges transfer to the real
     * account on claim approval.
     */
    private function placeholderUser(ShootingMatch $match, string $name, int $index): User
    {
        $hash = substr(hash('sha256', $match->id.'|'.$name.'|'.$index), 0, 20);
        $email = sprintf('pprc.m%d.n%s%s', $match->id, $hash, User::IMPORT_PLACEHOLDER_EMAIL_SUFFIX);

        $user = User::where('email', $email)->first();
        if ($user) {
            return $user;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(40)),
            'role' => 'shooter',
            'accepted_terms_at' => now(),
        ]);
        $user->forceFill(['email_verified_at' => null])->save();

        return $user;
    }

    /**
     * Squad roster for the match. Squad membership + order mirror the Impact
     * squadding screenshot; each shooter's `division` is taken from the
     * entries CSV (the authoritative source), and `email` is used to link an
     * existing DeadCenter account when one is present.
     *
     * @return array<int,array{name:string,shooters:array<int,array{name:string,division:string,email:string}>}>
     */
    private function roster(): array
    {
        return [
            [
                'name' => 'Coriolis - 1',
                'shooters' => [
                    ['name' => 'Abdul Aziz Amod', 'division' => 'Open', 'email' => 'abdul.amod0710@gmail.com'],
                    ['name' => 'Chris Pretorius', 'division' => 'Factory', 'email' => 'golfpretorius8@gmail.com'],
                    ['name' => 'Christiaan Klopper', 'division' => 'Limited', 'email' => 'btklopper@gmail.com'],
                    ['name' => 'Hendrik petrus Van Zyl', 'division' => 'Limited', 'email' => 'hendrikvz07@gmail.com'],
                    ['name' => 'Jc Robbertson', 'division' => 'Limited', 'email' => 'jcrobster@gmail.com'],
                    ['name' => 'Johan Nel', 'division' => 'Open', 'email' => 'nel.johan.2005@gmail.com'],
                    ['name' => 'Marcel Steyn', 'division' => 'Open', 'email' => 'marcelsteyn686@gmail.com'],
                    ['name' => 'Ruan du Plessis', 'division' => 'Open', 'email' => 'ruandup@rocketmail.com'],
                ],
            ],
            [
                'name' => 'Mirage - 2',
                'shooters' => [
                    ['name' => 'Chris Davies', 'division' => 'Open', 'email' => 'chris.davies.sa@gmail.com'],
                    ['name' => 'Etienne De Waal', 'division' => 'Senior', 'email' => 'etienne.dewaal@gmail.com'],
                    ['name' => 'Henri Klopper', 'division' => 'Open', 'email' => 'klopper.henri@gmail.com'],
                    ['name' => 'Jaco van Tonder', 'division' => 'Open', 'email' => 'jacovtonder7@gmail.com'],
                    ['name' => 'Pieter Neethling', 'division' => 'Open', 'email' => 'info@venaticsgear.net'],
                    ['name' => 'Stephan van der Merwe', 'division' => 'Open', 'email' => 'stepvandermerwe@gmail.com'],
                    ['name' => 'Trevor Graham', 'division' => 'Senior', 'email' => 'trevorgraham@live.co.za'],
                ],
            ],
            [
                'name' => 'Parallax - 3',
                'shooters' => [
                    ['name' => 'Donovan Cook', 'division' => 'Open', 'email' => 'truelineprecision@gmail.com'],
                    ['name' => 'Franco Ferreira', 'division' => 'Open', 'email' => 'francof1701@googlemail.com'],
                    ['name' => 'Jac Van Zyl', 'division' => 'Limited', 'email' => 'jvanzyl1@sars.gov.za'],
                    ['name' => 'Justin Le Roux', 'division' => 'Open', 'email' => 'jjleroux507@gmail.com'],
                    ['name' => 'Rob Jatho', 'division' => 'Limited', 'email' => 'robjatho@gmail.com'],
                    ['name' => 'Sean ONeill', 'division' => 'Limited', 'email' => 'oneills76@yahoo.com'],
                    ['name' => 'Tiaan Wessels', 'division' => 'Factory', 'email' => 'tiaanwessels@gmail.com'],
                ],
            ],
            [
                'name' => 'Spin Drift - 4',
                'shooters' => [
                    ['name' => 'Coenie Van Tonder', 'division' => 'Open', 'email' => 'coenievt@gmail.com'],
                    ['name' => 'Danie Du Preez', 'division' => 'Senior', 'email' => 'drdp@absamail.co.za'],
                    ['name' => 'Dumisani Shabangu', 'division' => 'Open', 'email' => 'dumisani888@gmail.com'],
                    ['name' => 'Natasha Britnell', 'division' => 'Ladies', 'email' => 'natasha@britplast.co.za'],
                    ['name' => "O'Kennedy Smit", 'division' => 'Open', 'email' => 'okennedy.smit@gmail.com'],
                    ['name' => 'Schalk van der Merwe', 'division' => 'Open', 'email' => 'vandermerwes1988@gmail.com'],
                    ['name' => 'Stelios Christofi', 'division' => 'Open', 'email' => 'stelios@kizotrading.co.za'],
                    ['name' => 'Warren Britnell', 'division' => 'Open', 'email' => 'warren@britplast.co.za'],
                ],
            ],
        ];
    }

    /**
     * Course of fire. Each gong is one shot slot in the stage sequence.
     * shape/mm/dist/mil describe the physical target that slot is fired at.
     *
     * @return array<int,array{label:string,timed?:bool,tiebreaker?:bool,brief:string,gongs:array<int,array{label:string,shape:string,mm:int,dist:int,mil:float}>}>
     */
    private function stages(): array
    {
        // Stage 1 target catalogue (all Ref 3 @ 485 m).
        $s1 = fn (int $k) => [
            1 => ['label' => 'KYL1', 'shape' => 'square', 'mm' => 440, 'dist' => 485, 'mil' => 0.91],
            2 => ['label' => 'KYL2', 'shape' => 'square', 'mm' => 360, 'dist' => 485, 'mil' => 0.74],
            3 => ['label' => 'KYL3', 'shape' => 'square', 'mm' => 270, 'dist' => 485, 'mil' => 0.56],
            4 => ['label' => 'KYL4', 'shape' => 'square', 'mm' => 180, 'dist' => 485, 'mil' => 0.37],
        ][$k];

        // Build the Stage 1 ladder: P1=K1, P2=K1-2, P3=K1-3, P4=K1-4 (10 shots).
        $s1Gongs = [];
        foreach ([1, 2, 3, 4] as $position) {
            foreach (range(1, $position) as $k) {
                $t = $s1($k);
                $s1Gongs[] = ['label' => "P{$position} · {$t['label']}"] + $t;
            }
        }

        $ref11 = ['label' => 'Ref 11', 'shape' => 'circle', 'mm' => 200, 'dist' => 398, 'mil' => 0.50];
        $s4Gongs = [];
        foreach (range(1, 8) as $p) {
            $s4Gongs[] = ['label' => "P{$p} · Ref 11"] + $ref11;
        }

        return [
            [
                'label' => 'Stage 1 — Climbing the Ladder',
                'brief' => "A-Frame & X-Barricade — 10 rounds. Positions: any 4, min 2 per prop, none repeated. Round ladder: P1=1rd (KYL1), P2=2rds (KYL1-2), P3=3rds (KYL1-3), P4=4rds (KYL1-4). Targets all Ref 3 @ 485 m: KYL1 square 440 mm (0.91), KYL2 square 360 mm (0.74), KYL3 square 270 mm (0.56), KYL4 square 180 mm (0.37). Par 105 s.",
                'gongs' => $s1Gongs,
            ],
            [
                'label' => 'Stage 2 — Heavy Rotation',
                'brief' => "Earthmover Tyres — 8 rounds, single position. Sequence: Ref5, Ref5, 19Big, 19Small, 21Big, 21Small, 12Big, 12Small. Par 105 s.",
                'gongs' => [
                    ['label' => 'Ref 5',        'shape' => 'square', 'mm' => 400, 'dist' => 687, 'mil' => 0.58],
                    ['label' => 'Ref 5',        'shape' => 'square', 'mm' => 400, 'dist' => 687, 'mil' => 0.58],
                    ['label' => 'Ref 19 Big',   'shape' => 'square', 'mm' => 400, 'dist' => 576, 'mil' => 0.69],
                    ['label' => 'Ref 19 Small', 'shape' => 'square', 'mm' => 240, 'dist' => 576, 'mil' => 0.42],
                    ['label' => 'Ref 21 Big',   'shape' => 'square', 'mm' => 300, 'dist' => 543, 'mil' => 0.55],
                    ['label' => 'Ref 21 Small', 'shape' => 'square', 'mm' => 150, 'dist' => 543, 'mil' => 0.28],
                    ['label' => 'Ref 12 Big',   'shape' => 'circle', 'mm' => 250, 'dist' => 575, 'mil' => 0.43],
                    ['label' => 'Ref 12 Small', 'shape' => 'circle', 'mm' => 150, 'dist' => 575, 'mil' => 0.26],
                ],
            ],
            [
                'label' => 'Stage 3 — Three Legs Good',
                'brief' => "Stepped Rail Frame — 8 rounds. Any 4 positions, none repeated; TRIPOD COMPULSORY at the rear position (P4). Targets Ref 8 @ 394 m: Big square 250 mm (0.63), Small diamond 150 mm (0.38). Sequence per position: Big, Small × 4 positions. Par 105 s.",
                'gongs' => $this->stage3Gongs(),
            ],
            [
                'label' => 'Stage 4 — The Piano',
                'timed' => true,
                'tiebreaker' => true,
                'brief' => "Staggered Post Barricade — 8 rounds — TIMED TIE-BREAKER. Any 8 positions, none repeated, 1 rd per position. Target Ref 11 circle 200 mm @ 398 m (0.50). Sequence: T1 × 8. Par 105 s; time defaults to 105.00 on a time-out.",
                'gongs' => $s4Gongs,
            ],
            [
                'label' => 'Stage 5 — Raise the Roof',
                'brief' => "Rooftop Ramp — 8 rounds, single position fully on the roof (rifle may be staged; climb on start signal). Sequence: 17K1, 17K2, 17K3, 17K1, NK1, NK2, NK3, NK4. Par 105 s.",
                'gongs' => [
                    ['label' => 'Ref 17 KYL1', 'shape' => 'square', 'mm' => 400, 'dist' => 510, 'mil' => 0.78],
                    ['label' => 'Ref 17 KYL2', 'shape' => 'square', 'mm' => 300, 'dist' => 510, 'mil' => 0.59],
                    ['label' => 'Ref 17 KYL3', 'shape' => 'square', 'mm' => 150, 'dist' => 510, 'mil' => 0.29],
                    ['label' => 'Ref 17 KYL1', 'shape' => 'square', 'mm' => 400, 'dist' => 510, 'mil' => 0.78],
                    ['label' => 'NoRef KYL1',  'shape' => 'square', 'mm' => 350, 'dist' => 504, 'mil' => 0.69],
                    ['label' => 'NoRef KYL2',  'shape' => 'square', 'mm' => 220, 'dist' => 504, 'mil' => 0.44],
                    ['label' => 'NoRef KYL3',  'shape' => 'circle', 'mm' => 250, 'dist' => 504, 'mil' => 0.50],
                    ['label' => 'NoRef KYL4',  'shape' => 'circle', 'mm' => 150, 'dist' => 504, 'mil' => 0.30],
                ],
            ],
        ];
    }

    /** Stage 3: Big/Small pair fired at each of 4 positions (P4 = tripod rear). */
    private function stage3Gongs(): array
    {
        $big   = ['shape' => 'square',  'mm' => 250, 'dist' => 394, 'mil' => 0.63];
        $small = ['shape' => 'diamond', 'mm' => 150, 'dist' => 394, 'mil' => 0.38];
        $gongs = [];
        foreach (range(1, 4) as $p) {
            $suffix = $p === 4 ? ' (tripod)' : '';
            $gongs[] = ['label' => "P{$p} · Big{$suffix}"] + $big;
            $gongs[] = ['label' => "P{$p} · Small{$suffix}"] + $small;
        }
        return $gongs;
    }
}
