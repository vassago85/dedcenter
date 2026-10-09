<?php

namespace App\Services;

use App\Enums\ElrEngagementMode;
use App\Enums\ElrShotResult;
use App\Enums\MatchStatus;
use App\Models\ElrScoringProfile;
use App\Models\ElrShot;
use App\Models\ElrTeamStageEntry;
use App\Models\MatchRegistration;
use App\Models\PrsShotScore;
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
        $usesElrPipeline = $match->usesElrPipeline();
        $targetSets = $usesElrPipeline
            ? collect()
            : $match->targetSets()->orderBy('sort_order')->withCount('gongs')->get();
        $stagesCount = $usesElrPipeline
            ? $match->elrStages->count()
            : $targetSets->count();
        $teamStageEntries = $usesElrPipeline
            ? ElrTeamStageEntry::query()
                ->whereIn('elr_stage_id', $match->elrStages->pluck('id'))
                ->get(['elr_stage_id', 'completed_at', 'timed_out'])
                ->groupBy('elr_stage_id')
            : collect();

        $elrChecklist = $usesElrPipeline ? $this->elrChecklist($match) : null;
        $setupChecklist = ! $usesElrPipeline ? $this->standardChecklist($match, $targetSets, $shooters) : null;
        $elrStages = $usesElrPipeline ? $this->elrStageRows($match, $teamStageEntries) : collect();
        $standardStages = ! $usesElrPipeline ? $this->standardStageRows($match, $targetSets) : collect();
        $scoringProgress = $this->scoringProgress($match, $shooters, $teamStageEntries);
        $divisionMismatches = $this->registrationDivisionMismatches($match, $shooters);
        $teamsCount = $match->teams->count();

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
                'alrha' => $match->alrhaClass()?->label() ?? 'ALRHA',
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
        $rangeCount = $stages->sum(fn ($s) => $s->divisionRanges->count());
        $rangesOk = ! $isTeamSeq || ($hasDivisions && $rangeCount >= $stages->count() * $divisions->count());

        $teamsOk = ! $isTeamSeq || ! $match->team_event || $match->teams->isNotEmpty();
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
    private function standardChecklist(ShootingMatch $match, Collection $targetSets, Collection $shooters): array
    {
        $isPrs = $match->isPrs();
        $hasStage = $targetSets->isNotEmpty();

        $shootersSquadded = $shooters->count();

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

    private function elrStageRows(ShootingMatch $match, Collection $teamStageEntries): Collection
    {
        $teamCount = $match->teams->count();

        return $match->elrStages->map(function ($stage) use ($teamStageEntries, $teamCount) {
            $completed = $teamStageEntries->get($stage->id, collect())->whereNotNull('completed_at')->count();
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

    private function standardStageRows(ShootingMatch $match, Collection $targetSets): Collection
    {
        $isPrs = $match->isPrs();

        return $targetSets->map(fn ($ts) => [
            'id' => $ts->id,
            'label' => $ts->name,
            'type' => $isPrs && $ts->is_tiebreaker ? 'tiebreaker' : 'stage',
            'gong_count' => (int) $ts->gongs_count,
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
    private function registrationDivisionMismatches(ShootingMatch $match, Collection $shooters): array
    {
        $shootersByUser = $shooters->whereNotNull('user_id')->groupBy('user_id');
        if ($shootersByUser->isEmpty()) {
            return [];
        }

        $regs = MatchRegistration::query()
            ->where('match_id', $match->id)
            ->where('payment_status', 'confirmed')
            ->whereNotNull('division_id')
            ->whereIn('user_id', $shootersByUser->keys())
            ->with('division')
            ->get();

        $mismatches = [];
        foreach ($regs as $reg) {
            foreach ($shootersByUser->get($reg->user_id, []) as $shooter) {
                if ($shooter->match_division_id !== null && $shooter->match_division_id !== $reg->division_id) {
                    $mismatches[] = [
                        'shooter_name' => $shooter->name,
                        'registration_division' => $reg->division?->name ?? '—',
                        'shooter_division' => $shooter->division_name ?? '—',
                    ];
                }
            }
        }

        return $mismatches;
    }

    /**
     * Attendance roster for the match hub: every shooter plus how many
     * shots they have on file. Reads the type-correct shot store so
     * ALRHA / ELR rows don't sit at "0 shots scored" while Top-5
     * already shows 25 hits from `elr_shots`.
     */
    public function attendanceRoster(ShootingMatch $match): Collection
    {
        $shooters = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->select('shooters.*', 'squads.name as squad_name')
            ->orderBy('squads.name')
            ->orderBy('shooters.sort_order')
            ->get();

        $ids = $shooters->pluck('id');
        $counts = collect();

        if ($ids->isNotEmpty()) {
            if ($match->usesElrPipeline()) {
                $counts = ElrShot::query()
                    ->whereIn('shooter_id', $ids)
                    ->whereIn('result', [ElrShotResult::Hit, ElrShotResult::Miss])
                    ->selectRaw('shooter_id, COUNT(*) as scored_shots')
                    ->groupBy('shooter_id')
                    ->pluck('scored_shots', 'shooter_id');
            } elseif ($match->isPrs()) {
                $counts = PrsShotScore::query()
                    ->whereIn('shooter_id', $ids)
                    ->selectRaw('shooter_id, COUNT(*) as scored_shots')
                    ->groupBy('shooter_id')
                    ->pluck('scored_shots', 'shooter_id');
            } else {
                $counts = Score::query()
                    ->whereIn('shooter_id', $ids)
                    ->selectRaw('shooter_id, COUNT(*) as scored_shots')
                    ->groupBy('shooter_id')
                    ->pluck('scored_shots', 'shooter_id');
            }
        }

        $shooters->each(function ($shooter) use ($counts) {
            $shooter->scored_shots = (int) ($counts[$shooter->id] ?? 0);
        });

        return $shooters;
    }

    private function scoringProgress(ShootingMatch $match, Collection $shooters, Collection $teamStageEntries): array
    {
        // ALRHA and ELR both write shot rows to `elr_shots`, not to the
        // legacy `scores` table. Gate on `usesElrPipeline()` (which is
        // true for both) so ALRHA hubs stop reporting "0 shots
        // recorded" while the scoring app is happily saving hits.
        if ($match->usesElrPipeline()) {
            $resultCounts = ElrShot::query()
                ->whereIn('shooter_id', $shooters->pluck('id'))
                ->whereIn('result', [ElrShotResult::Hit->value, ElrShotResult::Miss->value])
                ->groupBy('result')
                ->selectRaw('result, COUNT(*) as aggregate')
                ->toBase()
                ->pluck('aggregate', 'result');
            $hits = (int) ($resultCounts[ElrShotResult::Hit->value] ?? 0);
            $misses = (int) ($resultCounts[ElrShotResult::Miss->value] ?? 0);
            $total = $hits + $misses;

            // Team-stage progress is ELR-only (team gong sequence). ALRHA
            // doesn't use ElrTeamStageEntry, so its stage_progress
            // stays as an empty collection — the hub already renders
            // the panel conditionally.
            $stageProgress = collect();
            if ($match->isElr()) {
                $teamCount = max(1, $match->teams->count());
                $stageProgress = $match->elrStages->map(function ($stage) use ($teamCount, $teamStageEntries) {
                    $entries = $teamStageEntries->get($stage->id, collect());
                    $completed = $entries->whereNotNull('completed_at')->count();
                    $timedOut = $entries->where('timed_out', true)->count();

                    return [
                        'stage_id' => $stage->id,
                        'label' => $stage->label,
                        'teams_completed' => $completed,
                        'teams_total' => $teamCount,
                        'timed_out' => $timedOut,
                    ];
                });
            }

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
