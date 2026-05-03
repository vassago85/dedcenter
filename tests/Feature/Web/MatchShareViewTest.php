<?php

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\Organization;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Mobile-first shooter share view
|--------------------------------------------------------------------------
| Locks the contract that:
|   - GET /matches/{match}/my-report   → HTML mobile share view
|   - GET /matches/{match}/my-report.pdf → PDF download
|   - The HTML view advertises Web Share + WhatsApp + Copy + PDF links
|
| Regression baseline: previously /my-report streamed the PDF directly,
| which was a terrible experience to share on a phone — opening the link
| on iOS would dump a PDF blob into Safari with no share affordances.
| The new view is a proper share card with native share, WhatsApp deep
| link, copy URL, and a clearly-marked PDF download.
*/

it('renders the mobile share view at /matches/{match}/my-report', function () {
    [$user, $match] = makeShooterMatch();

    $res = $this->actingAs($user)->get(route('matches.my-report', $match));

    $res->assertOk();
    // It's HTML, not a PDF stream.
    expect($res->headers->get('Content-Type'))->toStartWith('text/html');
    // The hero, identity, and share-bar pieces all rendered.
    $res->assertSee('Match Report', false);
    $res->assertSee('DEAD', false);
    // WhatsApp deep link is present.
    $res->assertSee('https://wa.me/?text=', false);
    // Download PDF action points at the new .pdf endpoint.
    $res->assertSee(route('matches.my-report.pdf', $match), false);
});

it('still serves the PDF at /matches/{match}/my-report.pdf', function () {
    [$user, $match] = makeShooterMatch();

    $res = $this->actingAs($user)->get(route('matches.my-report.pdf', $match));

    $res->assertOk();
    // Either gotenberg-rendered or dompdf-rendered, but it MUST be a PDF.
    expect($res->headers->get('Content-Type'))->toBe('application/pdf');
});

/*
| pdfMode flag — the share view is now the single source of truth for both
| the on-screen render AND the downloadable PDF. When pdfMode=true the
| template skips the sticky share bar, the JSON island, and the share
| handler script (so Gotenberg renders a clean print-friendly artifact),
| and swaps the @vite include for a relative <link href="app.css">
| (which the renderer attaches as a sibling Gotenberg file). Locks both
| of those branches so a future contributor doesn't accidentally leave
| the share bar inside the PDF output.
*/
it('strips share bar + JS when rendered with pdfMode=true', function () {
    [$user, $match] = makeShooterMatch();
    $shooter = \App\Models\Shooter::where('user_id', $user->id)->first();
    $report = (new \App\Services\MatchReportService)->generateReport($match, $shooter);

    $html = view('pages.match-share', [
        'report'   => $report,
        'shareUrl' => 'https://example.test/share',
        'pdfUrl'   => null,
        'pdfMode'  => true,
    ])->render();

    // No interactive bits.
    expect($html)->not->toContain('data-share-btn');
    expect($html)->not->toContain('data-copy-btn');
    expect($html)->not->toContain('navigator.share');
    expect($html)->not->toContain('match-share-data');
    // CSS is the sibling-file path Gotenberg expects, not a Vite include.
    expect($html)->toContain('<link rel="stylesheet" href="app.css">');
    expect($html)->not->toContain('@vite');
    // PDF-only @page rule was emitted.
    expect($html)->toContain('size: 90mm auto');
});

it('keeps share bar + Vite include for the on-screen route', function () {
    [$user, $match] = makeShooterMatch();

    $res = $this->actingAs($user)->get(route('matches.my-report', $match));

    $res->assertOk();
    // The on-screen render still includes the interactive share bar...
    $res->assertSee('data-share-btn', false);
    // ...and does not flip into PDF mode.
    expect($res->getContent())->not->toContain('size: 90mm auto');
});

/*
| Badges section regression — the on-screen share view used to render
| every badge as the literal placeholder string "Badge" because the Blade
| was reading nonexistent keys (`title`, `tier_label`) instead of the
| actual `label` / `tier` fields produced by MatchReportService. We now
| read the right keys, so a real badge label should appear in the HTML.
*/
it('renders badges with their real labels in the share view', function () {
    [$user, $match] = makeShooterMatch();

    $achievement = \App\Models\Achievement::create([
        'slug' => 'iron-shooter',
        'label' => 'Iron Shooter',
        'description' => 'Stayed on the line all match.',
        'category' => 'lifetime',
        'competition_type' => 'standard',
        'is_active' => true,
        'sort_order' => 0,
    ]);
    \App\Models\UserAchievement::create([
        'user_id' => $user->id,
        'achievement_id' => $achievement->id,
        'match_id' => $match->id,
        'awarded_at' => now(),
    ]);

    $res = $this->actingAs($user)->get(route('matches.my-report', $match));

    $res->assertOk();
    $res->assertSee('Iron Shooter', false);
    $res->assertDontSee('>Badge<', false); // i.e. the old placeholder string
});

it('404s the share view if the user is not a linked shooter in the match', function () {
    [, $match] = makeShooterMatch();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('matches.my-report', $match))
        ->assertNotFound();
});

it('redirects unauthenticated users away from the share view', function () {
    [, $match] = makeShooterMatch();

    $res = $this->get(route('matches.my-report', $match));

    // 302 to /login (auth middleware) — anything but a 200 is fine; the
    // important thing is we don't render the report for an anonymous user.
    expect($res->status())->not->toBe(200);
});

/**
 * Spin up a tiny completed match with one squad, a shooter linked to a
 * fresh user, a single hit on a single gong. Smaller than the PRS / RF
 * fixtures elsewhere because the share view doesn't care about scoring
 * type — only that placement + summary render.
 *
 * @return array{0: User, 1: ShootingMatch}
 */
function makeShooterMatch(): array
{
    $user = User::factory()->create();
    $org = Organization::factory()->create();

    $match = ShootingMatch::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
        'scoring_type' => 'standard',
        'status' => MatchStatus::Completed,
    ]);

    $ts = TargetSet::create([
        'match_id' => $match->id,
        'label' => '500m',
        'distance_meters' => 500,
        'distance_multiplier' => 1.0,
        'sort_order' => 1,
    ]);
    $gong = Gong::create([
        'target_set_id' => $ts->id,
        'number' => 1,
        'label' => 'G1',
        'multiplier' => '1.00',
    ]);

    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Squad A', 'sort_order' => 1]);
    $shooter = Shooter::factory()->create([
        'squad_id' => $squad->id,
        'user_id' => $user->id,
        'status' => 'active',
    ]);
    Score::create([
        'shooter_id' => $shooter->id,
        'gong_id' => $gong->id,
        'is_hit' => true,
        'recorded_at' => now(),
    ]);

    return [$user, $match];
}
