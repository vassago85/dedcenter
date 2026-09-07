<?php

/**
 * Reports tab per-type panel dispatch.
 *
 * Pins that the admin Reports page picks the right per-type panel
 * (resources/views/reports/panels/{standard,royal-flush,prs,elr,alrha}.blade.php)
 * based on scoring_type + royal_flush_enabled — the "reports one-size-
 * fits-all" bug the user flagged after the scoreboard dispatcher split.
 *
 * Each test picks a signature string only that panel prints so a
 * regression that swaps two variants (e.g. RF -> Standard because the
 * royal_flush_enabled branch got dropped) fails loudly.
 *
 *   standard      → "Standings PDF" tile with "single-page leaderboard"
 *   royal-flush   → RF report section headline "Royal Flush report"
 *   prs           → PRS panel's Phase-3 callout "dedicated PRS score-sheet PDF"
 *   elr           → "ELR rankings" section header (only rendered on ELR)
 *   alrha         → ALRHA Prize Book callout "ALRHA Prize Book PDF"
 */

use App\Enums\MatchStatus;
use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\User;
use Livewire\Volt\Volt;

function reportsAdmin(): User
{
    return User::factory()->create(['role' => 'owner']);
}

function reportsMatch(string $scoringType, bool $royalFlush = false): ShootingMatch
{
    $org = Organization::factory()->create();

    return ShootingMatch::factory()->create([
        'scoring_type' => $scoringType,
        'royal_flush_enabled' => $royalFlush,
        'status' => MatchStatus::Completed,
        'organization_id' => $org->id,
    ]);
}

it('renders the standard panel for a plain standard match', function () {
    $this->actingAs(reportsAdmin());
    $match = reportsMatch('standard');

    Volt::test('admin.matches.reports', ['match' => $match])
        // Standard-only copy: the "single-page leaderboard" subtitle
        // lives on the standard panel's Standings PDF tile and nowhere
        // else in the reports panel set.
        ->assertSee('single-page leaderboard')
        // Must NOT show the RF headline or the ALRHA/PRS callouts.
        ->assertDontSee('Royal Flush report')
        ->assertDontSee('ALRHA Prize Book PDF')
        ->assertDontSee('dedicated PRS score-sheet PDF')
        ->assertDontSee('ELR rankings');
});

it('renders the royal-flush panel for a standard match with RF overlay', function () {
    $this->actingAs(reportsAdmin());
    $match = reportsMatch('standard', royalFlush: true);

    Volt::test('admin.matches.reports', ['match' => $match])
        // The RF section is prepended above the Standard catalogue.
        ->assertSee('Royal Flush report')
        ->assertSee('RF shots CSV')
        // Standard section still renders (RF panel @includes it).
        ->assertSee('single-page leaderboard');
});

it('renders the prs panel with the Phase-3 score-sheet callout', function () {
    $this->actingAs(reportsAdmin());
    $match = reportsMatch('prs');

    Volt::test('admin.matches.reports', ['match' => $match])
        ->assertSee('dedicated PRS score-sheet PDF')
        // PRS panel's Full Match Report tile copy — "podium, stage
        // stats, tiebreaker" — only lives in the PRS panel.
        ->assertSee('PRS podium, stage stats, tiebreaker')
        ->assertDontSee('Royal Flush report')
        ->assertDontSee('ALRHA Prize Book PDF');
});

it('renders the elr panel with the Rankings section as the headline', function () {
    $this->actingAs(reportsAdmin());
    $match = reportsMatch('elr');

    Volt::test('admin.matches.reports', ['match' => $match])
        ->assertSee('ELR rankings')
        ->assertSee('Rankings PDF')
        ->assertSee('Shots template CSV')
        // ELR panel omits the generic Standings PDF tile (the ELR
        // exports live inside the Rankings PDF).
        ->assertDontSee('single-page leaderboard')
        ->assertDontSee('Royal Flush report');
});

it('renders the alrha panel with the Prize Book Phase-3 callout', function () {
    $this->actingAs(reportsAdmin());
    $match = reportsMatch('alrha');

    Volt::test('admin.matches.reports', ['match' => $match])
        ->assertSee('ALRHA Prize Book PDF')
        // ALRHA Standings PDF tile subtitle is class-specific copy.
        ->assertSee('per-class prize tables')
        ->assertDontSee('Royal Flush report')
        ->assertDontSee('ELR rankings')
        ->assertDontSee('dedicated PRS score-sheet PDF');
});

it('ALRHA + royal_flush_enabled still dispatches to alrha (type wins over RF flag)', function () {
    // Guard against a future dispatcher regression where ALRHA gets
    // the RF panel because someone reordered the match() arms. RF only
    // applies to Standard scoring; on ALRHA the flag is a no-op.
    $this->actingAs(reportsAdmin());
    $match = reportsMatch('alrha', royalFlush: true);

    Volt::test('admin.matches.reports', ['match' => $match])
        ->assertSee('ALRHA Prize Book PDF')
        ->assertDontSee('Royal Flush report');
});
