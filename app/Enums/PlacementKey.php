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

    // Organization public portal (platform or org-controlled when entitled)
    case PortalHomeHero = 'portal_home_hero';
    case PortalHomeStrip = 'portal_home_strip';
    case PortalMatchesSidebar = 'portal_matches_sidebar';
    case PortalLeaderboardStrip = 'portal_leaderboard_strip';
    case PortalMatchDetailBanner = 'portal_match_detail_banner';

    // Site-wide marketing landing (monthly flagship — manual renewal)
    case LandingHeroMonthly = 'landing_hero_monthly';
    case LandingStripMonthly = 'landing_strip_monthly';

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
            self::PortalHomeHero => 'Portal — Home hero',
            self::PortalHomeStrip => 'Portal — Home strip',
            self::PortalMatchesSidebar => 'Portal — Matches sidebar',
            self::PortalLeaderboardStrip => 'Portal — Leaderboard strip',
            self::PortalMatchDetailBanner => 'Portal — Match detail banner',
            self::LandingHeroMonthly => 'Landing — Hero (monthly flagship)',
            self::LandingStripMonthly => 'Landing — Strip (monthly flagship)',
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
            self::PortalHomeHero, self::PortalHomeStrip, self::PortalMatchesSidebar,
            self::PortalLeaderboardStrip, self::PortalMatchDetailBanner => 'portal',
            self::LandingHeroMonthly, self::LandingStripMonthly => 'landing',
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

    public function isPortalPlacement(): bool
    {
        return str_starts_with($this->value, 'portal_');
    }

    public function isLandingPlacement(): bool
    {
        return str_starts_with($this->value, 'landing_');
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
     * Public-facing attribution line before the sponsor name (no trailing punctuation).
     * Keeps platform / portal ads distinct from official event or club sponsors.
     */
    public function publicPoweredByPrefix(): string
    {
        return match ($this) {
            self::PortalHomeHero,
            self::PortalHomeStrip,
            self::PortalMatchesSidebar,
            self::PortalMatchDetailBanner,
            self::LandingHeroMonthly,
            self::LandingStripMonthly => 'This page is powered by',

            self::PortalLeaderboardStrip => 'Leaderboard powered by',

            self::MatchLeaderboard,
            self::GlobalLeaderboard => 'Leaderboard powered by',

            self::MatchResults,
            self::GlobalResults => 'Results powered by',

            self::MatchScoring,
            self::GlobalScoring => 'Scoring powered by',

            self::MatchExports,
            self::GlobalExports => 'Exports powered by',

            self::MatchMatchbook,
            self::GlobalMatchbook => 'Match book powered by',

            self::MatchbookCover => 'Cover presentation powered by',
            self::MatchbookFooter,
            self::MatchbookInsideCover => 'This publication is powered by',
            self::MatchbookResultsSection => 'Results section powered by',

            self::SponsorInfoFeature => 'Presented by',
        };
    }

    /**
     * Get placements available for match director assignment.
     */
    public static function matchDirectorPlacements(): array
    {
        $placements = [
            self::MatchLeaderboard,
            self::MatchResults,
            self::MatchScoring,
            self::MatchExports,
            self::MatchMatchbook,
        ];

        if (! config('deadcenter.matchbook_enabled')) {
            return array_values(array_filter(
                $placements,
                fn (self $p) => $p !== self::MatchMatchbook
            ));
        }

        return $placements;
    }

    /**
     * Get placements available for platform admin assignment.
     */
    public static function platformPlacements(): array
    {
        $placements = [
            self::GlobalLeaderboard,
            self::GlobalResults,
            self::GlobalScoring,
            self::GlobalExports,
            self::GlobalMatchbook,
            self::SponsorInfoFeature,
            self::PortalHomeHero,
            self::PortalHomeStrip,
            self::PortalMatchesSidebar,
            self::PortalLeaderboardStrip,
            self::PortalMatchDetailBanner,
            self::LandingHeroMonthly,
            self::LandingStripMonthly,
        ];

        if (! config('deadcenter.matchbook_enabled')) {
            return array_values(array_filter(
                $placements,
                fn (self $p) => $p !== self::GlobalMatchbook
            ));
        }

        return $placements;
    }

    /**
     * Portal placements an organization may assign when it has portal advertising rights.
     *
     * @return list<self>
     */
    public static function organizationPortalPlacements(): array
    {
        return [
            self::PortalHomeHero,
            self::PortalHomeStrip,
            self::PortalMatchesSidebar,
            self::PortalLeaderboardStrip,
            self::PortalMatchDetailBanner,
        ];
    }

    /**
     * Site-wide landing placements (platform admin only).
     *
     * @return list<self>
     */
    public static function landingPlacements(): array
    {
        return [
            self::LandingHeroMonthly,
            self::LandingStripMonthly,
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
