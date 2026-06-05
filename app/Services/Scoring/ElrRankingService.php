<?php

namespace App\Services\Scoring;

use App\Models\ElrTeamStageEntry;
use App\Models\ShootingMatch;

/**
 * Builds the three ELR ranking views (overall individual, team, division)
 * for the team gong-sequence mode.
 *
 * Rules (per spec):
 *  - Only stages a team has COMPLETED (elr_team_stage_entries.completed_at set)
 *    count toward that team's / its shooters' totals. Incomplete stages are
 *    excluded entirely — never projected or estimated.
 *  - Personal/division scores count only the gongs a shooter's division
 *    engaged, which the underlying ELRScoringService already enforces via the
 *    elr_division_targets whitelist.
 *  - Ties break on the most recent completed stage score (higher wins); if
 *    still tied the rows share a joint rank.
 */
class ElrRankingService
{
    public function __construct(private ELRScoringService $scoring) {}

    public function build(ShootingMatch $match): array
    {
        $base = $this->scoring->calculateStandings($match);
        $shooterRows = $base['standings'] ?? [];

        // (team_id => [stage_id => true]) for completed team stages only.
        $completedByTeam = [];
        $completedStageIds = [];
        ElrTeamStageEntry::query()
            ->whereHas('stage', fn ($q) => $q->where('match_id', $match->id))
            ->whereNotNull('completed_at')
            ->get(['team_id', 'elr_stage_id'])
            ->each(function ($entry) use (&$completedByTeam, &$completedStageIds) {
                $completedByTeam[$entry->team_id][$entry->elr_stage_id] = true;
                $completedStageIds[$entry->elr_stage_id] = true;
            });

        // Stage column meta: only stages at least one team has completed,
        // ordered as the match defines them.
        $stageColumns = collect($base['stages'] ?? [])
            ->filter(fn ($s) => isset($completedStageIds[$s['id']]))
            ->map(fn ($s) => ['stage_id' => $s['id'], 'label' => $s['label']])
            ->values()
            ->all();
        $orderedStageIds = array_column($stageColumns, 'stage_id');

        $overall = $this->buildIndividual($shooterRows, $completedByTeam, $orderedStageIds);
        $teams = $this->buildTeams($shooterRows, $completedByTeam, $orderedStageIds);
        $divisions = $this->buildDivisions($overall);

        return [
            'match' => [
                'id' => $match->id,
                'name' => $match->name,
                'scoring_type' => $match->scoring_type,
                'alternate_scoring' => (bool) $match->alternate_scoring,
            ],
            'stages' => $stageColumns,
            'overall' => $overall,
            'teams' => $teams,
            'divisions' => $divisions,
        ];
    }

    /**
     * Per-shooter stage points keyed by stage id, drawn from the already
     * division-whitelisted standings rows.
     */
    private function shooterStagePoints(array $row): array
    {
        $points = [];
        foreach ($row['stages'] ?? [] as $stage) {
            $points[$stage['stage_id']] = (float) $stage['points'];
        }

        return $points;
    }

    private function buildIndividual(array $shooterRows, array $completedByTeam, array $orderedStageIds): array
    {
        $rows = [];
        foreach ($shooterRows as $row) {
            $teamId = $row['team_id'] ?? null;
            $stagePoints = $this->shooterStagePoints($row);

            [$stageScores, $total, $lastScore] = $this->reduceStages(
                $teamId,
                $stagePoints,
                $completedByTeam,
                $orderedStageIds,
            );

            $rows[] = [
                'shooter_id' => $row['id'],
                'name' => $row['name'],
                'division_id' => $row['division_id'] ?? null,
                'division' => $row['division'] ?? null,
                'team_id' => $teamId,
                'team' => $row['team'] ?? null,
                'stage_scores' => $stageScores,
                'total_score' => round($total, 2),
                'last_stage_score' => $lastScore,
            ];
        }

        return $this->rankWithJoint($rows);
    }

    private function buildTeams(array $shooterRows, array $completedByTeam, array $orderedStageIds): array
    {
        $byTeam = [];
        foreach ($shooterRows as $row) {
            $teamId = $row['team_id'] ?? null;
            if ($teamId === null) {
                continue;
            }
            $byTeam[$teamId]['team'] = $row['team'] ?? ('Team ' . $teamId);
            $byTeam[$teamId]['members'][] = $row;
            $byTeam[$teamId]['divisions'][$row['division'] ?? '—'] = true;
        }

        $rows = [];
        foreach ($byTeam as $teamId => $team) {
            // Team stage points = sum of member personal points for that stage.
            $teamStagePoints = [];
            foreach ($team['members'] as $member) {
                foreach ($this->shooterStagePoints($member) as $stageId => $pts) {
                    $teamStagePoints[$stageId] = ($teamStagePoints[$stageId] ?? 0) + $pts;
                }
            }

            [$stageScores, $total, $lastScore] = $this->reduceStages(
                $teamId,
                $teamStagePoints,
                $completedByTeam,
                $orderedStageIds,
            );

            $rows[] = [
                'team_id' => $teamId,
                'team' => $team['team'],
                'division_composition' => implode('/', array_keys($team['divisions'])),
                'stage_scores' => $stageScores,
                'total_score' => round($total, 2),
                'last_stage_score' => $lastScore,
            ];
        }

        return $this->rankWithJoint($rows);
    }

    /**
     * One ranked list per division, drawn from the already-ranked individual
     * rows. A mixed-division shooter appears only in their own division.
     *
     * @return array<int, array{division_id:int|null, division:string, rows:array}>
     */
    private function buildDivisions(array $overall): array
    {
        $byDivision = [];
        foreach ($overall as $row) {
            $key = $row['division_id'] ?? 0;
            $byDivision[$key]['division_id'] = $row['division_id'] ?? null;
            $byDivision[$key]['division'] = $row['division'] ?? 'Unassigned';
            // Drop the global rank/joint; re-rank within the division.
            $clean = $row;
            unset($clean['rank'], $clean['joint']);
            $byDivision[$key]['rows'][] = $clean;
        }

        $out = [];
        foreach ($byDivision as $division) {
            $out[] = [
                'division_id' => $division['division_id'],
                'division' => $division['division'],
                'rows' => $this->rankWithJoint($division['rows']),
            ];
        }

        usort($out, fn ($a, $b) => strcasecmp((string) $a['division'], (string) $b['division']));

        return $out;
    }

    /**
     * Reduce a stage-points map to only the completed stages for a team,
     * preserving column order. Returns [stageScores, total, lastStageScore].
     * stageScores has an entry per ordered column: float for completed,
     * null for not-yet-completed.
     */
    private function reduceStages(?int $teamId, array $stagePoints, array $completedByTeam, array $orderedStageIds): array
    {
        $completed = $teamId !== null ? ($completedByTeam[$teamId] ?? []) : [];
        $stageScores = [];
        $total = 0.0;
        $lastScore = 0.0;

        foreach ($orderedStageIds as $stageId) {
            if (isset($completed[$stageId])) {
                $pts = round((float) ($stagePoints[$stageId] ?? 0), 2);
                $stageScores[$stageId] = $pts;
                $total += $pts;
                $lastScore = $pts; // ordered last completed wins
            } else {
                $stageScores[$stageId] = null;
            }
        }

        return [$stageScores, $total, $lastScore];
    }

    /**
     * Assign competition-style ranks (1,2,2,4) sorted by total desc then most
     * recent stage score desc. Rows sharing both keys get a joint flag.
     */
    private function rankWithJoint(array $rows): array
    {
        usort($rows, function ($a, $b) {
            if ($a['total_score'] !== $b['total_score']) {
                return $b['total_score'] <=> $a['total_score'];
            }

            return $b['last_stage_score'] <=> $a['last_stage_score'];
        });

        $count = count($rows);
        for ($i = 0; $i < $count; $i++) {
            $tiedWithPrev = $i > 0 && $this->sameRankKey($rows[$i], $rows[$i - 1]);
            $rows[$i]['rank'] = $tiedWithPrev ? $rows[$i - 1]['rank'] : $i + 1;
            $rows[$i]['joint'] = false;
        }

        // Flag every member of a shared rank.
        $rankCounts = [];
        foreach ($rows as $row) {
            $rankCounts[$row['rank']] = ($rankCounts[$row['rank']] ?? 0) + 1;
        }
        foreach ($rows as &$row) {
            if (($rankCounts[$row['rank']] ?? 0) > 1) {
                $row['joint'] = true;
            }
        }
        unset($row);

        return $rows;
    }

    private function sameRankKey(array $a, array $b): bool
    {
        return $a['total_score'] === $b['total_score']
            && $a['last_stage_score'] === $b['last_stage_score'];
    }
}
