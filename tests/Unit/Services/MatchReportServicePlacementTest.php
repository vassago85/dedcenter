<?php

use App\Services\MatchReportService;

/*
|--------------------------------------------------------------------------
| Placement summary + ordinal helpers
|--------------------------------------------------------------------------
| The shooter report previously rendered every placement as "(top X%)" with
| X = rank/total*100. Mathematically that's a real percentile-position
| number, but the *copy* reads like a flex when it isn't — rank 32 of 47
| came out as "(top 68%)" which sounds like an achievement but actually
| means below average.
|
| The new rule:
|   - Top half (rank/total <= 0.5)  → "top X%" (the smaller, flattering number)
|   - Bottom half                   → "beat N shooters" (honest, unambiguous)
|   - Last place                    → '' (nothing to brag about)
|
| And the buggy ordinal `match` (which gave "32th" instead of "32nd") is
| replaced with a proper English-suffix helper.
*/

describe('MatchReportService::placementSummary', function () {
    it('renders top finishers as a flattering "top X%"', function () {
        expect(MatchReportService::placementSummary(1, 47))->toBe('top 2%');
        expect(MatchReportService::placementSummary(5, 47))->toBe('top 11%');
        expect(MatchReportService::placementSummary(23, 47))->toBe('top 49%');
    });

    it('switches to "beat N shooters" once you fall below the median', function () {
        // The bug the user reported — 32 of 47 used to read "(top 68%)".
        expect(MatchReportService::placementSummary(32, 47))->toBe('beat 15 shooters');
        expect(MatchReportService::placementSummary(40, 47))->toBe('beat 7 shooters');
    });

    it('uses singular "shooter" when only one was beaten', function () {
        expect(MatchReportService::placementSummary(46, 47))->toBe('beat 1 shooter');
    });

    it('returns empty string when nothing is worth saying', function () {
        // Last place — nothing flattering, no one beaten.
        expect(MatchReportService::placementSummary(47, 47))->toBe('');
        // Degenerate inputs.
        expect(MatchReportService::placementSummary(0, 0))->toBe('');
        expect(MatchReportService::placementSummary(1, 0))->toBe('');
    });

    it('never reports "top 0%" — clamps to a minimum of 1%', function () {
        // 1 of 1000 = 0.1%, would round to 0 and read like a bug.
        expect(MatchReportService::placementSummary(1, 1000))->toBe('top 1%');
    });
});

/*
 * placementSummaryShort() is the same rule as placementSummary() but
 * trimmed for chips / pills / stat-card badges. The dashboard's per-org
 * "Best finishes" card uses this so we don't show a fat sentence inside
 * a tiny pill (and so we never render the misleading "Top 68%" again).
 */
describe('MatchReportService::placementSummaryShort', function () {
    it('renders top-half finishes as "Top X%" (Title Case, no noun)', function () {
        expect(MatchReportService::placementSummaryShort(1, 47))->toBe('Top 2%');
        expect(MatchReportService::placementSummaryShort(5, 47))->toBe('Top 11%');
        expect(MatchReportService::placementSummaryShort(23, 47))->toBe('Top 49%');
    });

    it('renders bottom-half finishes as just "Beat N" (the chip context implies "shooters")', function () {
        // The exact bug from the dashboard screenshot — "32 of 47" used to
        // read "Top 69%" inside the chip; now reads "Beat 15".
        expect(MatchReportService::placementSummaryShort(32, 47))->toBe('Beat 15');
        expect(MatchReportService::placementSummaryShort(32, 51))->toBe('Beat 19');
        expect(MatchReportService::placementSummaryShort(46, 47))->toBe('Beat 1');
    });

    it('returns empty string for last place / unranked', function () {
        expect(MatchReportService::placementSummaryShort(47, 47))->toBe('');
        expect(MatchReportService::placementSummaryShort(0, 0))->toBe('');
    });
});

describe('MatchReportService::ordinalSuffix', function () {
    it('handles the single-digit ordinals', function () {
        expect(MatchReportService::ordinalSuffix(1))->toBe('st');
        expect(MatchReportService::ordinalSuffix(2))->toBe('nd');
        expect(MatchReportService::ordinalSuffix(3))->toBe('rd');
        expect(MatchReportService::ordinalSuffix(4))->toBe('th');
    });

    it('handles the 11/12/13 teens correctly (always "th")', function () {
        // Old code got these right by accident (default => 'th').
        expect(MatchReportService::ordinalSuffix(11))->toBe('th');
        expect(MatchReportService::ordinalSuffix(12))->toBe('th');
        expect(MatchReportService::ordinalSuffix(13))->toBe('th');
    });

    it('handles the 21+ ordinals — this is what the old `match` got wrong', function () {
        // The bug: old `match` only matched 1/2/3 literally, so 22 → "th"
        // (rendered as "22th"). These four are the regression cases.
        expect(MatchReportService::ordinalSuffix(21))->toBe('st');
        expect(MatchReportService::ordinalSuffix(22))->toBe('nd');
        expect(MatchReportService::ordinalSuffix(23))->toBe('rd');
        expect(MatchReportService::ordinalSuffix(32))->toBe('nd');
        expect(MatchReportService::ordinalSuffix(43))->toBe('rd');
        expect(MatchReportService::ordinalSuffix(101))->toBe('st');
        expect(MatchReportService::ordinalSuffix(112))->toBe('th');
        expect(MatchReportService::ordinalSuffix(113))->toBe('th');
    });
});
