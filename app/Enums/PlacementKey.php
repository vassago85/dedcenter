<?php

namespace App\Enums;

enum PlacementKey: string
{
    // Platform-level (global)
    case GlobalLeaderboard = 'global_leaderboard';
    case GlobalResults = 'global_results';
    case GlobalScoring = 'global_scoring';
    case GlobalExports = 'global_exports';
    case GlobalMatchbook = 'global_matchbook';

    // Match-level
    case MatchLeaderboard = 'match_leaderboard';
    case MatchResults = 'match_results';
    case MatchScoring = 'match_scoring';
    case MatchExports = 'match_exports';
    case MatchMatchbook = 'match_matchbook';

    // Matchbook-specific placements
    case MatchbookCover = 'matchbook_cover';
    case MatchbookFooter = 'matchbook_footer';
    case MatchbookInsideCover = 'matchbook_inside_cover';
    case MatchbookResultsSection = 'matchbook_results_section';

    // Brand info page (legacy)
    case SponsorInfoFeature = 'sponsor_info_feature';

    /**
     * Get a business-friendly label for the placement.
     */
    public function label(): string
    {
        return match ($this) {
            self::GlobalLeaderboard => 'Leaderboard (Platform Default)',
            self::GlobalResults => 'Results (Platform Default)',
            self::GlobalScoring => 'Scoring (Platform Default)',
            self::GlobalExports => 'Exports (Platform Default)',
            self::GlobalMatchbook => 'Match Book (Platform Default)',
            self::MatchLeaderboard => 'Leaderboard',
            self::MatchResults => 'Results',
            self::MatchScoring => 'Scoring',
            self::MatchExports => 'Exports',
            self::MatchMatchbook => 'Match Book',
            self::MatchbookCover => 'Match Book Cover',
            self::MatchbookFooter => 'Match Book Footer',
            self::MatchbookInsideCover => 'Match Book Inside Cover',
            self::MatchbookResultsSection => 'Match Book Results Section',
            self::SponsorInfoFeature => 'Brand Info Page Feature',
        };
    }

    /**
     * Group placements by display surface for admin UI.
     */
    public function surface(): string
    {
        return match ($this) {
            self::GlobalLeaderboard, self::MatchLeaderboard => 'leaderboard',
            self::GlobalResults, self::MatchResults => 'results',
            self::GlobalScoring, self::MatchScoring => 'scoring',
            self::GlobalExports, self::MatchExports => 'exports',
            self::GlobalMatchbook, self::MatchMatchbook,
            self::MatchbookCover, self::MatchbookFooter,
            self::MatchbookInsideCover, self::MatchbookResultsSection => 'matchbook',
            self::SponsorInfoFeature => 'brand_info',
        };
    }

    /**
     * Get the corresponding global key for a match-level placement.
     */
    public function globalEquivalent(): ?self
    {
        return match ($this) {
            self::MatchLeaderboard => self::GlobalLeaderboard,
            self::MatchResults => self::GlobalResults,
            self::MatchScoring => self::GlobalScoring,
            self::MatchExports => self::GlobalExports,
            self::MatchMatchbook => self::GlobalMatchbook,
            default => null,
        };
    }

    /**
     * Whether this placement is platform-level (global).
     */
    public function isGlobal(): bool
    {
        return str_starts_with($this->value, 'global_');
    }

    /**
     * Whether this placement is match-level.
     */
    public function isMatchLevel(): bool
    {
        return str_starts_with($this->value, 'match_') && ! str_starts_with($this->value, 'matchbook_');
    }

    /**
     * Whether this placement is matchbook-specific.
     */
    public function isMatchbookLevel(): bool
    {
        return str_starts_with($this->value, 'matchbook_');
    }

    /**
     * The 3 active advertising placements for the current version.
     */
    public static function advertisingPlacements(): array
    {
        return [
            self::MatchLeaderboard,
            self::MatchResults,
            self::MatchScoring,
        ];
    }

    /**
     * "Powered by" label for feature-based display.
     */
    public function poweredByLabel(): string
    {
        return match ($this) {
            self::MatchLeaderboard, self::GlobalLeaderboard => 'Leaderboard',
            self::MatchResults, self::GlobalResults => 'Results',
            self::MatchScoring, self::GlobalScoring => 'Scoring',
            default => 'Feature',
        };
    }

    /**
     * Get placements available for match director assignment.
     */
    public static function matchDirectorPlacements(): array
    {
        return [
            self::MatchLeaderboard,
            self::MatchResults,
            self::MatchScoring,
            self::MatchExports,
            self::MatchMatchbook,
        ];
    }

    /**
     * Get placements available for platform admin assignment.
     */
    public static function platformPlacements(): array
    {
        return [
            self::GlobalLeaderboard,
            self::GlobalResults,
            self::GlobalScoring,
            self::GlobalExports,
            self::GlobalMatchbook,
            self::SponsorInfoFeature,
        ];
    }

    /**
     * Get placements available for matchbook-level assignment.
     */
    public static function matchbookPlacements(): array
    {
        return [
            self::MatchbookCover,
            self::MatchbookFooter,
            self::MatchbookInsideCover,
            self::MatchbookResultsSection,
        ];
    }
}
