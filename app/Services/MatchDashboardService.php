<?php

namespace App\Services;

use App\Enums\ElrEngagementMode;
use App\Enums\ElrShotResult;
use App\Enums\MatchStatus;
use App\Models\ElrScoringProfile;
use App\Models\ElrShot;
use App\Models\ElrStageDivisionRange;
use App\Models\ElrTeamStageEntry;
use App\Models\MatchRegistration;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Aggregates live dashboard data for the match hub (all scoring types).
 */
class MatchDashboardService
{
    public function build(ShootingMatch $match): array
    {
        $match->loadMissing([
            'divisions',
            'teams.shooters.division',
            'elrStages.targets',
            'elrStages.divisionRanges',
            'elrScoringProfile',
        ]);

        $shooters = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->leftJoin('match_divisions', 'shooters.match_division_id', '=', 'match_divisions.id')
            ->where('squads.match_id', $match->id)
            ->select('shooters.*', 'squads.name as squad_name', 'match_divisions.name as division_name')
            ->orderBy('squads.sort_order')
            ->orderBy('shooters.sort_order')
            ->get();

        $registrationsCount = $match->registrations()->count();
        $stagesCount = $match->isElr()
            ? $match->elrStages->count()
            : $match->targetSets()->count();

        $elrChecklist = $match->isElr() ? $this->elrChecklist($match) : null;
        $setupChecklist = ! $match->isElr() ? $this->standardChecklist($match) : null;
        $elrStages = $match->isElr() ? $this->elrStageRows($match) : collect();
        $standardStages = ! $match->isElr() ? $this->standardStageRows($match) : collect();
        $scoringProgress = $this->scoringProgress($match, $shooters);
        $divisionMismatches = $this->registrationDivisionMismatches($match);
        $teamsCount = $match->teams()->count();

        // Post-match "finish this match" data. Unclaimed = walk-ins / import
        // placeholders whose result isn't linked to a real account yet.
        $unclaimedCount = Shooter::query()
            ->whereHas('squad', fn ($q) => $q->where('match_id', $match->id))
            ->unclaimedResult()
            ->count();

        return [
            'type_label' => match ($match->scoring_type) {
                'elr' => 'ELR',
                'prs' => 'PRS',
                default => 'Standard',
            },
            'status_label' => match ($match->status) {
                MatchStatus::Draft, MatchStatus::PreRegistration => 'Draft',
                MatchStatus::RegistrationOpen, MatchStatus::RegistrationClosed,
                MatchStatus::SquaddingOpen, MatchStatus::SquaddingClosed, MatchStatus::Ready => 'Open',
                MatchStatus::Active => 'In Progress',
                MatchStatus::Completed => 'Complete',
                default => $match->status->label(),
            },
            'registrations_count' => $registrationsCount,
            'shooters_count' => $shooters->count(),
            'stages_count' => $stagesCount,
            'scores_published' => (bool) ($match->scores_published ?? true),
            'unclaimed_count' => $unclaimedCount,
            'royal_flush_enabled' => (bool) $match->royal_flush_enabled,
            'side_bet_enabled' => (bool) $match->side_bet_enabled,
            'elr_checklist' => $elrChecklist,
            'setup_checklist' => $setupChecklist,
            'elr_stages' => $elrStages,
            'standard_stages' => $standardStages,
            'teams_count' => $teamsCount,
            'shooters_by_division' => $shooters->groupBy(fn ($s) => $s->division_name ?? 'Unassigned')->map->count(),
            'team_composition' => $match->isElr() && $match->elrEngagementMode()?->isTeamSequence()
                ? $this->teamComposition($match)
                : [],
            'unassigned_shooters' => $shooters->filter(fn ($s) => $s->team_id === null || $s->squad_id === null)->count(),
            'division_mismatches' => $divisionMismatches,
            'scoring_progress' => $scoringProgress,
        ];
    }

    /**
     * @return array<int, array{key:string, label:string, done:bool, anchor:string}>
     */
    private function elrChecklist(ShootingMatch $match): array
    {
        $isTeamSeq = $match->elrEngagementMode()?->isTeamSequence() ?? false;
        $profile = $match->elrScoringProfile;
        $defaultMult = '1.00, 0.70, 0.50';
        $savedMult = $profile ? implode(', ', array_map(fn ($m) => number_format((float) $m, 2), $profile->multipliers ?? [])) : '';
        $profileConfigured = $profile !== null && $savedMult !== '' && $savedMult !== $defaultMult;

        $stages = $match->elrStages;
        $hasStage = $stages->isNotEmpty();
        $allTargetsHaveDistance = $hasStage && $stages->every(
            fn ($s) => $s->targets->isNotEmpty() && $s->targets->every(fn ($t) => (int) $t->distance_m > 0)
        );

        $divisions = $match->divisions;
        $hasDivisions = $divisions->isNotEmpty();
        $rangeCount = ElrStageDivisionRange::whereIn('elr_stage_id', $stages->pluck('id'))->count();
        $rangesOk = ! $isTeamSeq || ($hasDivisions && $rangeCount >= $stages->count() * $divisions->count());

        $teamsOk = ! $isTeamSeq || ! $match->team_event || $match->teams()->count() > 0;
        $timerOk = ! $isTeamSeq || $match->elr_team_time_limit_seconds !== null;
        // Explicitly set means not null — DB default false counts as explicit once migration ran.
        $distanceExplicit = $match->elr_distance_based_scoring !== null;

        $items = [
            ['key' => 'profile', 'label' => 'Scoring profile configured', 'done' => $profileConfigured, 'anchor' => '#elr-scoring-profile'],
            ['key' => 'engagement', 'label' => 'Engagement mode selected', 'done' => $match->elr_engagement_mode !== null, 'anchor' => '#elr-engagement'],
            ['key' => 'stages', 'label' => 'At least one stage added', 'done' => $hasStage, 'anchor' => '#elr-stages'],
            ['key' => 'targets', 'label' => 'Each stage has gongs with distances', 'done' => $allTargetsHaveDistance, 'anchor' => '#elr-stages'],
        ];

        if ($isTeamSeq) {
            $items[] = ['key' => 'divisions', 'label' => 'Divisions configured', 'done' => $hasDivisions, 'anchor' => '#match-divisions'];
            $items[] = ['key' => 'ranges', 'label' => 'Division gong ranges set per stage', 'done' => $rangesOk, 'anchor' => '#elr-stages'];
            $items[] = ['key' => 'teams', 'label' => 'Teams configured', 'done' => $teamsOk, 'anchor' => '#match-teams'];
            $items[] = ['key' => 'timer', 'label' => 'Team time limit set', 'done' => $timerOk, 'anchor' => '#elr-engagement'];
        }

        $items[] = [
            'key' => 'distance_scoring',
            'label' => 'Distance-based scoring explicitly set',
            'done' => $distanceExplicit,
            'anchor' => '#elr-engagement',
        ];

        return $items;
    }

    /**
     * Setup-readiness checklist for standard (relay) and PRS matches — the
     * standard-match equivalent of the ELR checklist, so a stand-in MD can
     * see at a glance what still needs configuring before going live.
     *
     * @return array<int, array{key:string, label:string, done:bool, anchor:string, target:string}>
     */
    private function standardChecklist(ShootingMatch $match): array
    {
        $isPrs = $match->isPrs();
        $targetSets = $match->targetSets()->withCount('gongs')->get();
        $hasStage = $targetSets->isNotEmpty();

        $shootersSquadded = Shooter::query()
            ->whereHas('squad', fn ($q) => $q->where('match_id', $match->id))
            ->count();

        $items = [
            [
                'key' => 'stages',
                'label' => $isPrs ? 'At least one stage added' : 'At least one target distance added',
                'done' => $hasStage,
                'anchor' => '#stages',
                'target' => 'setup',
            ],
        ];

        if (! $isPrs) {
            $allHaveGongs = $hasStage && $targetSets->every(fn ($ts) => (int) $ts->gongs_count > 0);
            $items[] = [
                'key' => 'gongs',
                'label' => 'Every distance has gongs',
                'done' => $allHaveGongs,
                'anchor' => '#stages',
                'target' => 'setup',
            ];
        }

        $items[] = [
            'key' => 'squads',
            'label' => 'Shooters added to squads',
            'done' => $shootersSquadded > 0,
            'anchor' => '',
            'target' => 'squadding',
        ];

        return $items;
    }

    private function elrStageRows(ShootingMatch $match): Collection
    {
        $teamCount = $match->teams()->count();
        $entries = ElrTeamStageEntry::query()
            ->whereIn('elr_stage_id', $match->elrStages->pluck('id'))
            ->whereNotNull('completed_at')
            ->get()
            ->groupBy('elr_stage_id');

        return $match->elrStages->map(function ($stage) use ($entries, $teamCount, $match) {
            $completed = ($entries[$stage->id] ?? collect())->count();
            $ranges = $stage->divisionRanges->map(fn ($r) => [
                'division_id' => $r->match_division_id,
                'gong_start' => $r->gong_start,
                'gong_end' => $r->gong_end,
            ]);

            return [
                'id' => $stage->id,
                'label' => $stage->label,
                'type' => $stage->stage_type->value,
                'gong_count' => $stage->targets->count(),
                'profile' => $stage->resolvedProfile()?->name ?? 'Default',
                'teams_completed' => $completed,
                'teams_total' => $teamCount,
                'division_ranges' => $ranges,
            ];
        });
    }

    private function standardStageRows(ShootingMatch $match): Collection
    {
        if ($match->isPrs()) {
            return $match->targetSets()->orderBy('sort_order')->get()->map(fn ($ts) => [
                'id' => $ts->id,
                'label' => $ts->name,
                'type' => $ts->is_tiebreaker ? 'tiebreaker' : 'stage',
                'gong_count' => $ts->gongs()->count(),
                'profile' => null,
                'teams_completed' => null,
                'teams_total' => null,
                'division_ranges' => collect(),
            ]);
        }

        return $match->targetSets()->orderBy('sort_order')->get()->map(fn ($ts) => [
            'id' => $ts->id,
            'label' => $ts->name,
            'type' => 'stage',
            'gong_count' => $ts->gongs()->count(),
            'profile' => null,
            'teams_completed' => null,
            'teams_total' => null,
            'division_ranges' => collect(),
        ]);
    }

    private function teamComposition(ShootingMatch $match): array
    {
        $counts = [];
        foreach ($match->teams as $team) {
            $label = $team->divisionCategoryLabel();
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array<int, array{registration_division:string, shooter_division:string, shooter_name:string}>
     */
    private function registrationDivisionMismatches(ShootingMatch $match): array
    {
        $regs = MatchRegistration::query()
            ->where('match_id', $match->id)
            ->where('payment_status', 'confirmed')
            ->whereNotNull('division_id')
            ->with('division')
            ->get();

        $mismatches = [];
        foreach ($regs as $reg) {
            if (! $reg->user_id) {
                continue;
            }
            $shooters = Shooter::query()
                ->whereHas('squad', fn ($q) => $q->where('match_id', $match->id))
                ->where('user_id', $reg->user_id)
                ->get();

            foreach ($shooters as $shooter) {
                if ($shooter->match_division_id !== null && $shooter->match_division_id !== $reg->division_id) {
                    $mismatches[] = [
                        'shooter_name' => $shooter->name,
                        'registration_division' => $reg->division?->name ?? '—',
                        'shooter_division' => $shooter->division?->name ?? '—',
                    ];
                }
            }
        }

        return $mismatches;
    }

    private function scoringProgress(ShootingMatch $match, Collection $shooters): array
    {
        if ($match->isElr()) {
            $shooterIds = $shooters->pluck('id');
            $shots = ElrShot::whereIn('shooter_id', $shooterIds)->get();
            $hits = $shots->where('result', ElrShotResult::Hit)->count();
            $misses = $shots->where('result', ElrShotResult::Miss)->count();
            $total = $hits + $misses;

            $teamCount = max(1, $match->teams()->count());
            $stageProgress = $match->elrStages->map(function ($stage) use ($teamCount) {
                $completed = ElrTeamStageEntry::where('elr_stage_id', $stage->id)
                    ->whereNotNull('completed_at')
                    ->count();
                $timedOut = ElrTeamStageEntry::where('elr_stage_id', $stage->id)
                    ->where('timed_out', true)
                    ->count();

                return [
                    'stage_id' => $stage->id,
                    'label' => $stage->label,
                    'teams_completed' => $completed,
                    'teams_total' => $teamCount,
                    'timed_out' => $timedOut,
                ];
            });

            return [
                'shots_recorded' => $total,
                'hits' => $hits,
                'misses' => $misses,
                'completion_pct' => $total > 0 ? round($hits / $total * 100, 1) : 0,
                'stage_progress' => $stageProgress,
            ];
        }

        $scoresCount = Score::whereIn('shooter_id', $shooters->pluck('id'))->count();

        return [
            'shots_recorded' => $scoresCount,
            'hits' => null,
            'misses' => null,
            'completion_pct' => null,
            'stage_progress' => collect(),
        ];
    }
}
