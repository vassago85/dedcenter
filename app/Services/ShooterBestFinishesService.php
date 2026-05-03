<?php

namespace App\Services;

use App\Enums\MatchStatus;
use App\Models\Organization;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\User;
use Illuminate\Support\Collection;

/*
 * NOTE on scoring-type coverage:
 * Standard matches use MatchStandingsService directly (cheap aggregate query).
 * PRS / ELR matches use MatchReportService::generateReport() so we get the
 * exact same rank that the per-shooter PDF and the in-app match report
 * surface — no tie-breaker logic duplicated in two places. The cost of
 * generateReport() (full per-shooter payload incl. stages/fun-facts/badges)
 * is only paid for matches the user actually shot, and only on dashboard
 * load. If that ever becomes a perf concern we can add a leaner
 * `MatchReportService::placementOnly($match, $shooter)` and switch.
 */

/**
 * Builds a per-organization "best finish" card for the shooter dashboard.
 *
 * A user's "best finish" in an org is the lowest rank they achieved in ANY
 * completed match of that org (or any child, for leagues). We also surface
 * the percentile (rank / field_size) so shooters who finished 8th of 50 can
 * brag about a top-16% finish, not just the raw rank.
 *
 * Cross-org comparison is explicitly avoided — a Royal Flush win is not
 * directly comparable to a PRS win, so we always present stats per-org.
 */
class ShooterBestFinishesService
{
    public function __construct(
        private ?MatchReportService $reportService = null,
    ) {
        // The DI container fills $reportService in production; the legacy
        // tests `new ShooterBestFinishesService()` so default-construct via
        // app() to keep the existing test signatures working.
        $this->reportService ??= app(MatchReportService::class);
    }

    /**
     * Return a collection of best-finish rows for a user, one per organization
     * they have ever competed in, sorted by rank (best first).
     *
     * Each row is a plain object with:
     *   - organization (Organization)     : logo + name
     *   - best_rank (int)                 : lowest rank in any completed match
     *   - field_size (int)                : number of ranked shooters in the match that produced the best rank
     *   - percentile (int)                : 1..100 (lower is better, e.g. 5 = top 5%)
     *   - best_match (ShootingMatch|null) : the match that produced the best rank (for deep-linking)
     *   - matches_shot (int)              : total completed matches in this org
     *
     * @return Collection<int, object>
     */
    public function forUser(User $user): Collection
    {
        // Every completed match where the user was a linked shooter.
        $shooters = Shooter::query()
            ->with(['squad.match.organization'])
            ->where('user_id', $user->id)
            ->whereHas('squad.match', fn ($q) => $q->where('status', MatchStatus::Completed))
            ->get()
            ->filter(fn (Shooter $s) => $s->squad?->match?->organization !== null);

        if ($shooters->isEmpty()) {
            return collect();
        }

        $standingsService = new MatchStandingsService;

        $rowsByOrg = [];

        foreach ($shooters as $shooter) {
            $match = $shooter->squad->match;
            $org = $match->organization;

            $rankInfo = $this->rankForShooter($standingsService, $match, $shooter);
            if ($rankInfo === null) {
                // DQ, no-show, or unranked (e.g. PRS without results) — skip for ranking but still count.
                $rowsByOrg[$org->id] ??= [
                    'organization' => $org,
                    'best_rank' => null,
                    'field_size' => 0,
                    'best_match' => null,
                    'matches_shot' => 0,
                ];
                $rowsByOrg[$org->id]['matches_shot']++;

                continue;
            }

            [$rank, $fieldSize] = $rankInfo;

            $existing = $rowsByOrg[$org->id] ?? null;
            if ($existing === null || $existing['best_rank'] === null || $rank < $existing['best_rank']) {
                $rowsByOrg[$org->id] = [
                    'organization' => $org,
                    'best_rank' => $rank,
                    'field_size' => $fieldSize,
                    'best_match' => $match,
                    'matches_shot' => ($existing['matches_shot'] ?? 0) + 1,
                ];
            } else {
                $rowsByOrg[$org->id]['matches_shot']++;
            }
        }

        return collect($rowsByOrg)
            ->map(function (array $row) {
                $percentile = null;
                if ($row['best_rank'] !== null && $row['field_size'] > 0) {
                    // Top percentile: e.g. rank 3 of 40 = 8 (top 8%). Minimum 1.
                    $percentile = max(1, (int) ceil(($row['best_rank'] / $row['field_size']) * 100));
                }

                return (object) [
                    'organization' => $row['organization'],
                    'best_rank' => $row['best_rank'],
                    'field_size' => $row['field_size'],
                    'percentile' => $percentile,
                    'best_match' => $row['best_match'],
                    'matches_shot' => $row['matches_shot'],
                ];
            })
            ->sortBy(fn ($row) => $row->best_rank ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * Return [rank, fieldSize] for a given shooter within a match, or null if
     * the shooter wasn't ranked (DQ / no-show / no scoring data yet).
     *
     * Standard matches go through MatchStandingsService directly. PRS / ELR
     * matches defer to MatchReportService so the dashboard "best finish"
     * matches the rank shown on the per-shooter report (same tie-breaker
     * rules, same field-size definition).
     *
     * @return array{0: int, 1: int}|null
     */
    private function rankForShooter(MatchStandingsService $service, ShootingMatch $match, Shooter $shooter): ?array
    {
        if ($match->isPrs() || $match->isElr()) {
            return $this->prsOrElrRank($match, $shooter);
        }

        $standings = $service->standardStandings($match);
        $row = $standings->firstWhere('shooter_id', $shooter->id);

        if ($row === null || $row->rank === null) {
            return null;
        }

        $fieldSize = $standings->filter(fn ($r) => $r->rank !== null)->count();

        return [(int) $row->rank, $fieldSize];
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function prsOrElrRank(ShootingMatch $match, Shooter $shooter): ?array
    {
        try {
            $report = $this->reportService->generateReport($match, $shooter);
        } catch (\Throwable $e) {
            // A report can fail for matches with no scored stages yet (e.g.
            // a PRS match marked Completed but with empty PrsStageResults).
            // Treat that as "unranked" rather than 500-ing the dashboard.
            return null;
        }

        $rank = $report['placement']['rank'] ?? null;
        $total = $report['placement']['total'] ?? 0;

        if (! $rank || $total <= 0) {
            return null;
        }

        return [(int) $rank, (int) $total];
    }
}
