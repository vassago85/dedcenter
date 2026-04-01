<?php

namespace App\Services;

use App\Enums\PlacementKey;
use App\Enums\SponsorScope;
use App\Models\SponsorAssignment;
use Illuminate\Support\Collection;

class SponsorPlacementResolver
{
    /**
     * Resolve the highest-priority sponsor assignment for a placement.
     *
     * Hierarchy:
     *   1. Matchbook-specific assignment (if matchBookId provided)
     *   2. Match-specific assignment (if matchId provided)
     *   3. Platform-level (global) assignment
     *   4. null
     */
    public function resolve(PlacementKey|string $placementKey, ?int $matchId = null, ?int $matchBookId = null): ?SponsorAssignment
    {
        $placementKey = $placementKey instanceof PlacementKey ? $placementKey : PlacementKey::from($placementKey);

        // 1. Matchbook-specific (only for matchbook placements)
        if ($matchBookId && $placementKey->isMatchbookLevel()) {
            $assignment = $this->findAssignment($placementKey, SponsorScope::Matchbook, $matchBookId);
            if ($assignment) {
                return $assignment;
            }
        }

        // 2. Match-level
        if ($matchId) {
            $matchPlacementKey = $this->toMatchLevel($placementKey);
            if ($matchPlacementKey) {
                $assignment = $this->findAssignment($matchPlacementKey, SponsorScope::Match, $matchId);
                if ($assignment) {
                    return $assignment;
                }
            }
        }

        // 3. Platform-level (global)
        $globalKey = $this->toGlobalLevel($placementKey);
        if ($globalKey) {
            $assignment = $this->findAssignment($globalKey, SponsorScope::Platform, null);
            if ($assignment) {
                return $assignment;
            }
        }

        return null;
    }

    /**
     * Resolve all valid sponsor assignments for a placement, using the hierarchy.
     * Returns the collection from the highest-priority level that has assignments.
     */
    public function resolveAll(PlacementKey|string $placementKey, ?int $matchId = null, ?int $matchBookId = null): Collection
    {
        $placementKey = $placementKey instanceof PlacementKey ? $placementKey : PlacementKey::from($placementKey);

        // 1. Matchbook-specific
        if ($matchBookId && $placementKey->isMatchbookLevel()) {
            $assignments = $this->findAssignments($placementKey, SponsorScope::Matchbook, $matchBookId);
            if ($assignments->isNotEmpty()) {
                return $assignments;
            }
        }

        // 2. Match-level
        if ($matchId) {
            $matchPlacementKey = $this->toMatchLevel($placementKey);
            if ($matchPlacementKey) {
                $assignments = $this->findAssignments($matchPlacementKey, SponsorScope::Match, $matchId);
                if ($assignments->isNotEmpty()) {
                    return $assignments;
                }
            }
        }

        // 3. Platform-level
        $globalKey = $this->toGlobalLevel($placementKey);
        if ($globalKey) {
            $assignments = $this->findAssignments($globalKey, SponsorScope::Platform, null);
            if ($assignments->isNotEmpty()) {
                return $assignments;
            }
        }

        return collect();
    }

    /**
     * Find a single valid assignment, with the sponsor eagerly loaded.
     */
    protected function findAssignment(PlacementKey $key, SponsorScope $scope, ?int $scopeId): ?SponsorAssignment
    {
        return $this->baseQuery($key, $scope, $scopeId)
            ->orderBy('display_order')
            ->first();
    }

    /**
     * Find all valid assignments for a scope, with sponsors eagerly loaded.
     */
    protected function findAssignments(PlacementKey $key, SponsorScope $scope, ?int $scopeId): Collection
    {
        return $this->baseQuery($key, $scope, $scopeId)
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Base query that filters by placement, scope, and validates both the assignment and sponsor are active/current.
     */
    protected function baseQuery(PlacementKey $key, SponsorScope $scope, ?int $scopeId)
    {
        $now = now();

        return SponsorAssignment::query()
            ->with('sponsor')
            ->whereHas('sponsor', function ($q) use ($now) {
                $q->where('active', true)
                    ->where(fn ($q2) => $q2->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                    ->where(fn ($q2) => $q2->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
            })
            ->where('placement_key', $key)
            ->where('scope_type', $scope)
            ->where(fn ($q) => $scopeId
                ? $q->where('scope_id', $scopeId)
                : $q->whereNull('scope_id')
            )
            ->where('active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    /**
     * Map any placement key to its match-level equivalent.
     */
    protected function toMatchLevel(PlacementKey $key): ?PlacementKey
    {
        if ($key->isMatchLevel()) {
            return $key;
        }

        return match ($key) {
            PlacementKey::GlobalLeaderboard, PlacementKey::MatchLeaderboard => PlacementKey::MatchLeaderboard,
            PlacementKey::GlobalResults, PlacementKey::MatchResults => PlacementKey::MatchResults,
            PlacementKey::GlobalScoring, PlacementKey::MatchScoring => PlacementKey::MatchScoring,
            PlacementKey::GlobalExports, PlacementKey::MatchExports => PlacementKey::MatchExports,
            PlacementKey::GlobalMatchbook, PlacementKey::MatchMatchbook,
            PlacementKey::MatchbookCover, PlacementKey::MatchbookFooter,
            PlacementKey::MatchbookInsideCover, PlacementKey::MatchbookResultsSection => PlacementKey::MatchMatchbook,
            default => null,
        };
    }

    /**
     * Map any placement key to its global-level equivalent.
     */
    protected function toGlobalLevel(PlacementKey $key): ?PlacementKey
    {
        if ($key->isGlobal()) {
            return $key;
        }

        return match ($key) {
            PlacementKey::MatchLeaderboard => PlacementKey::GlobalLeaderboard,
            PlacementKey::MatchResults => PlacementKey::GlobalResults,
            PlacementKey::MatchScoring => PlacementKey::GlobalScoring,
            PlacementKey::MatchExports => PlacementKey::GlobalExports,
            PlacementKey::MatchMatchbook,
            PlacementKey::MatchbookCover, PlacementKey::MatchbookFooter,
            PlacementKey::MatchbookInsideCover, PlacementKey::MatchbookResultsSection => PlacementKey::GlobalMatchbook,
            default => null,
        };
    }
}
