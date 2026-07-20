<?php

namespace App\Services\Scoring;

use App\Enums\AlrhaClass;
use App\Models\ShootingMatch;

/**
 * ALRHA scoring engine.
 *
 * Delegates the heavy lifting (shot loading, per-shot points, stage
 * hierarchy) to ELRScoringService, then post-processes the resulting
 * rows to enforce ALRHA-specific rules:
 *
 *  - The Cold Bore Challenge is scored as its own separate prize table
 *    and MUST NOT contribute to the match total (rules §6, §CBC).
 *  - Hunters is a team class; a team ranking is built from the pair.
 *  - Varmint is individual; each shooter ranks on their own points.
 *  - Coached shooters ({@see Shooter::$is_coached}) are still scored
 *    but are excluded from the prize-eligible standings (rules §4).
 *  - Categories (Open / Ladies / Junior) each get their own ranking
 *    so the MD can hand out per-category prizes (rules §Prizes).
 *
 * Ranking tie-breakers (in order):
 *   1. Total points (excluding CBC), desc.
 *   2. Number of first-round (impact-1) hits, desc.
 *   3. Furthest hit metres, desc.
 *   4. Alphabetical name (stable, so exports are deterministic).
 */
class AlrhaScoringService implements ScoringEngineInterface
{
    public function __construct(private ELRScoringService $elr) {}

    public function calculateStandings(ShootingMatch $match, array $filters = []): array
    {
        $base = $this->elr->calculateStandings($match, $filters);
        $class = $match->alrhaClass();

        // Which target ids are CBC — used to subtract CBC points/hits from
        // the match total for every shooter row. serializeStages() emits
        // target ids as 'id'; the per-row stage payload uses 'target_id'.
        $cbcTargetIds = [];
        foreach ($base['stages'] ?? [] as $stage) {
            foreach ($stage['targets'] ?? [] as $target) {
                $targetId = (int) ($target['id'] ?? $target['target_id'] ?? 0);
                if ($targetId === 0) {
                    continue;
                }
                $isCbc = ! empty($target['is_cold_bore'])
                    // Fallback when the ELR serializer does not include the
                    // flag: infer from the class' CBC distance + must-hit
                    // conventions. Kept safe by requiring an exact match on
                    // both distance and the reserved CBC label prefix.
                    || (
                        $class
                        && (int) ($target['distance_m'] ?? 0) === $class->coldBoreDistance()
                        && str_starts_with(strtolower((string) ($target['name'] ?? '')), 'cbc')
                    );
                if ($isCbc) {
                    $cbcTargetIds[$targetId] = true;
                }
            }
        }

        $ranked = $this->deriveRows($base['standings'] ?? [], $cbcTargetIds);
        $ranked = $this->applyFilters($ranked, $filters);
        $ranked = $this->rank($ranked, 'total_points');

        $cbc = $this->deriveCbcRows($base['standings'] ?? [], $cbcTargetIds);
        $cbc = $this->applyFilters($cbc, $filters);
        $cbc = $this->rank($cbc, 'cbc_points');

        return [
            'match' => array_merge($base['match'] ?? [], [
                'scoring_type' => 'alrha',
                'alrha_class' => $class?->value,
                'alrha_class_label' => $class?->label(),
            ]),
            'stages' => $base['stages'] ?? [],
            'standings' => $ranked,
            'teams' => $class === AlrhaClass::Hunters
                ? $this->buildTeamStandings($ranked)
                : [],
            'cbc' => $cbc,
            'categories' => $this->buildCategoryStandings($ranked, $class),
            'divisions' => $base['divisions'] ?? [],
            'active_division' => $base['active_division'] ?? null,
        ];
    }

    /**
     * Peel CBC contributions off each shooter row so the reported
     * total_points / total_hits reflect the class-total prize table.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, true>                  $cbcTargetIds
     */
    private function deriveRows(array $rows, array $cbcTargetIds): array
    {
        return array_map(function ($row) use ($cbcTargetIds) {
            [$cbcPoints, $cbcHits] = $this->cbcTotals($row, $cbcTargetIds);

            $row['cbc_points'] = round($cbcPoints, 2);
            $row['cbc_hits'] = $cbcHits;
            $row['total_points'] = round(max(0.0, (float) ($row['total_points'] ?? 0) - $cbcPoints), 2);
            $row['total_hits'] = max(0, (int) ($row['total_hits'] ?? 0) - $cbcHits);

            return $row;
        }, $rows);
    }

    /**
     * Build a CBC-only prize table. Rows keep their shooter identity but
     * total_points on the row is replaced with the CBC-only points so the
     * same UI can render either table.
     */
    private function deriveCbcRows(array $rows, array $cbcTargetIds): array
    {
        $out = [];
        foreach ($rows as $row) {
            [$cbcPoints, $cbcHits] = $this->cbcTotals($row, $cbcTargetIds);
            if ($cbcPoints <= 0 && $cbcHits <= 0) {
                continue;
            }
            $row['cbc_points'] = round($cbcPoints, 2);
            $row['cbc_hits'] = $cbcHits;
            $out[] = $row;
        }

        return $out;
    }

    private function cbcTotals(array $row, array $cbcTargetIds): array
    {
        $cbcPoints = 0.0;
        $cbcHits = 0;

        foreach ($row['stages'] ?? [] as $stage) {
            foreach ($stage['targets'] ?? [] as $target) {
                $targetId = (int) ($target['target_id'] ?? $target['id'] ?? 0);
                if (! isset($cbcTargetIds[$targetId])) {
                    continue;
                }
                foreach ($target['shots'] ?? [] as $shot) {
                    if (($shot['result'] ?? null) === 'hit') {
                        $cbcPoints += (float) ($shot['points'] ?? 0);
                        $cbcHits++;
                    }
                }
            }
        }

        return [$cbcPoints, $cbcHits];
    }

    /**
     * Ignore withdrawn / DQ / no-show rows for the ALRHA prize tables:
     * the printed prize giving only cares about active shooters. Coached
     * shooters are kept in the ranking payload but flagged so the UI can
     * show a "coached — not eligible" chip.
     */
    private function applyFilters(array $rows, array $filters): array
    {
        $includeCoached = (bool) ($filters['include_coached'] ?? true);

        return array_values(array_filter($rows, function ($row) use ($includeCoached) {
            if (! in_array($row['status'] ?? 'active', ['active', 'withdrawn'], true)) {
                return false;
            }
            if (! $includeCoached && ! empty($row['is_coached'])) {
                return false;
            }

            return true;
        }));
    }

    /**
     * Rank rows by the given points key with ALRHA tie-breakers applied
     * in order (see class docblock).
     *
     * @param  string  $pointsKey  'total_points' or 'cbc_points'.
     */
    private function rank(array $rows, string $pointsKey): array
    {
        usort($rows, function ($a, $b) use ($pointsKey) {
            $pa = (float) ($a[$pointsKey] ?? 0);
            $pb = (float) ($b[$pointsKey] ?? 0);
            if ($pa !== $pb) {
                return $pb <=> $pa;
            }
            $frA = (int) ($a['first_round_hits'] ?? 0);
            $frB = (int) ($b['first_round_hits'] ?? 0);
            if ($frA !== $frB) {
                return $frB <=> $frA;
            }
            $fhA = (int) ($a['furthest_hit_m'] ?? 0);
            $fhB = (int) ($b['furthest_hit_m'] ?? 0);
            if ($fhA !== $fhB) {
                return $fhB <=> $fhA;
            }

            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        $top = (float) ($rows[0][$pointsKey] ?? 0);
        foreach ($rows as $index => &$row) {
            $row['rank'] = $index + 1;
            $row['normalized_score'] = $top > 0
                ? round(((float) ($row[$pointsKey] ?? 0) / $top) * 100, 1)
                : 0.0;
        }
        unset($row);

        return $rows;
    }

    /**
     * Hunters is scored on the team total (sum of the pair's non-CBC
     * points). We roll the already-ranked individuals up by team_id so
     * both the pair and the individual leaderboards stay consistent.
     */
    private function buildTeamStandings(array $rows): array
    {
        $byTeam = [];
        foreach ($rows as $row) {
            $teamId = $row['team_id'] ?? null;
            if (! $teamId) {
                continue;
            }
            $byTeam[$teamId]['team_id'] = (int) $teamId;
            $byTeam[$teamId]['team'] = $row['team'] ?? ('Team ' . $teamId);
            $byTeam[$teamId]['members'][] = $row;
        }

        $teams = [];
        foreach ($byTeam as $team) {
            $members = collect($team['members'] ?? [])
                ->sortByDesc('total_points')
                ->values();
            $teams[] = [
                'team_id' => $team['team_id'],
                'team' => $team['team'],
                'team_total_points' => round($members->sum('total_points'), 2),
                'team_total_hits' => (int) $members->sum('total_hits'),
                'shooter_1_id' => $members[0]['id'] ?? null,
                'shooter_1_name' => $members[0]['name'] ?? null,
                'shooter_1_score' => (float) ($members[0]['total_points'] ?? 0),
                'shooter_2_id' => $members[1]['id'] ?? null,
                'shooter_2_name' => $members[1]['name'] ?? null,
                'shooter_2_score' => (float) ($members[1]['total_points'] ?? 0),
                'furthest_hit_m' => (int) $members->max('furthest_hit_m'),
                'first_round_hits' => (int) $members->sum('first_round_hits'),
            ];
        }

        usort($teams, function ($a, $b) {
            if ($a['team_total_points'] !== $b['team_total_points']) {
                return $b['team_total_points'] <=> $a['team_total_points'];
            }
            if ($a['first_round_hits'] !== $b['first_round_hits']) {
                return $b['first_round_hits'] <=> $a['first_round_hits'];
            }

            return $b['furthest_hit_m'] <=> $a['furthest_hit_m'];
        });

        foreach ($teams as $index => &$team) {
            $team['rank'] = $index + 1;
        }
        unset($team);

        return $teams;
    }

    /**
     * Sliced views of the ranked rows per category. Uses `categories`
     * from the row (populated by MatchCategory pivot) so a shooter that
     * qualifies for multiple categories (e.g. Junior + Ladies for a
     * varmint match) appears in each.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function buildCategoryStandings(array $rows, ?AlrhaClass $class): array
    {
        if (! $class) {
            return [];
        }

        $slugs = $class->categorySlugs();
        $labels = [
            'open' => 'Open',
            'ladies' => 'Ladies',
            'junior' => 'Junior',
        ];

        $out = [];
        foreach ($slugs as $slug) {
            $filtered = array_values(array_filter($rows, function ($row) use ($slug) {
                if (! empty($row['is_coached'])) {
                    // Coached shooters never win a category prize (§4).
                    return false;
                }
                $rowCats = array_map('strtolower', (array) ($row['category_slugs'] ?? []));

                // Rows without a category default to Open so the MD doesn't
                // have to tag every entry manually.
                if (empty($rowCats)) {
                    return $slug === 'open';
                }

                return in_array($slug, $rowCats, true);
            }));

            $out[] = [
                'slug' => $slug,
                'name' => $labels[$slug] ?? ucfirst($slug),
                'rows' => $this->rank($filtered, 'total_points'),
            ];
        }

        return $out;
    }
}
