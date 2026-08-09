<?php

namespace App\Http\Controllers;

use App\Mail\ShooterMatchReport;
use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\Shooter;
use App\Services\MatchReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MatchReportController extends Controller
{
    public function __construct(
        private MatchReportService $reportService,
    ) {}

    /**
     * Authenticated preview — org/admin staff viewing shooter reports.
     *
     * Shared by the org.* and admin.* route groups. The org group's URI has
     * a `{organization}` segment which route-model binds into $organization
     * (matched by name, so signature order doesn't matter); the admin group
     * has no such segment so $organization stays null and we emit the
     * admin-scoped download URL. The returned view is the email template
     * with $showActions=true, which renders a Download PDF button above the
     * report.
     */
    public function preview(Request $request, Organization $organization, ShootingMatch $match)
    {
        return $this->renderPreview($request, $match, $organization);
    }

    /**
     * Admin-scoped preview. Separate entry point because Laravel binds
     * controller arguments POSITIONALLY by URI-segment order, and the admin
     * route has no `{organization}` segment — so a shared signature can't
     * satisfy both groups. Delegates to the same renderer with a null org.
     */
    public function adminPreview(Request $request, ShootingMatch $match)
    {
        return $this->renderPreview($request, $match, null);
    }

    private function renderPreview(Request $request, ShootingMatch $match, ?Organization $organization)
    {
        // Bind the {organization} segment to the match so an org admin can't
        // preview another org's shooter reports through a crafted URL, and
        // require staff view rights on the match itself.
        if ($organization instanceof Organization && $organization->exists) {
            abort_unless($match->organization_id === $organization->id, 404);
        }
        abort_unless($request->user()->can('view', $match), 403, 'You are not authorized to view reports for this match.');

        $shooter = $this->resolveShooter($request, $match);

        if (! $shooter) {
            abort(404, 'No active shooters in this match.');
        }

        $report = $this->reportService->generateReport($match, $shooter);

        $isOrgScope = $organization instanceof Organization && $organization->exists;
        $downloadUrl = $isOrgScope
            ? route('org.matches.export.pdf-shooter-report', [$organization, $match, $shooter])
            : route('admin.matches.export.pdf-shooter-report', [$match, $shooter]);

        return view('emails.shooter-match-report', [
            'report' => $report,
            'showActions' => true,
            'downloadUrl' => $downloadUrl,
        ]);
    }

    /**
     * Public per-shooter match report — linked from the scoreboard.
     *
     * Anyone can view the HTML report for a completed match (the scoreboard
     * itself is public), but the Download PDF action is gated behind auth:
     * the per-user my-shooter-report route exists for authenticated shooters
     * and the org/admin-scoped PDF routes exist for staff. For public
     * viewers with no elevated context we hide the button entirely so they
     * still get a responsive read-only report.
     */
    public function publicPreview(Request $request, ShootingMatch $match, Shooter $shooter)
    {
        abort_unless(
            $shooter->squad && $shooter->squad->match_id === $match->id,
            404,
            'Shooter does not belong to this match.',
        );

        // Respect the MD's "hide scores" toggle on this public HTML report too
        // — otherwise unpublished results leak through the per-shooter share
        // link. Staff (admin / org MD) and the linked shooter themself may
        // still preview before publishing.
        if (! $match->scoresArePublic()) {
            $user = $request->user();
            $mayPreview = $user && (
                $user->isAdmin()
                || ($match->organization_id && $user->isOrgMatchDirector($match->organization))
                || $shooter->user_id === $user->id
            );
            abort_unless($mayPreview, 404, 'Results for this match have not been published yet.');
        }

        $report = $this->reportService->generateReport($match, $shooter);

        // PDF download is gated by who's looking — staff get the official
        // export, the linked shooter gets the my-report PDF, everyone else
        // sees no PDF button (they can still share the URL itself).
        $pdfUrl = $this->resolvePublicDownloadUrl($request, $match, $shooter);

        return view('pages.match-share', [
            'report'   => $report,
            'shareUrl' => route('scoreboard.matches.report.view', [$match, $shooter]),
            'pdfUrl'   => $pdfUrl,
        ]);
    }

    private function resolveShooter(Request $request, ShootingMatch $match): ?Shooter
    {
        $shooterId = $request->query('shooter');

        if ($shooterId) {
            // Scope to this match — never resolve a shooter id from another
            // match into this report (IDOR).
            return Shooter::whereHas('squad', fn ($q) => $q->where('match_id', $match->id))
                ->findOrFail($shooterId);
        }

        return $match->squads()->with('shooters')->get()
            ->flatMap->shooters
            ->where('status', 'active')
            ->first();
    }

    /**
     * Pick the best PDF download URL for the public preview:
     *   - platform admin + org match director see the staff PDF export
     *   - the linked shooter themself sees the member "my shooter report"
     *   - everyone else gets null (Download PDF button is hidden)
     */
    private function resolvePublicDownloadUrl(Request $request, ShootingMatch $match, Shooter $shooter): ?string
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        if ($user->isAdmin()) {
            return route('admin.matches.export.pdf-shooter-report', [$match, $shooter]);
        }

        if ($match->organization_id && $user->isOrgMatchDirector($match->organization)) {
            return route('org.matches.export.pdf-shooter-report', [$match->organization, $match, $shooter]);
        }

        if ($shooter->user_id === $user->id) {
            // The shooter themself viewing their own scoreboard preview →
            // PDF endpoint for the my-report flow (the HTML view IS the
            // page they're already on).
            return route('matches.my-report.pdf', $match);
        }

        return null;
    }

    public function send(Request $request, Organization $organization, ShootingMatch $match)
    {
        abort_unless($match->organization_id === $organization->id, 404);

        return $this->queueReports($request, $match);
    }

    /**
     * Admin-scoped send — see adminPreview() for why this is a separate
     * entry point (positional arg binding + no `{organization}` segment).
     */
    public function adminSend(Request $request, ShootingMatch $match)
    {
        return $this->queueReports($request, $match);
    }

    private function queueReports(Request $request, ShootingMatch $match)
    {
        // Blasting reports to every shooter's inbox is a match-lifecycle
        // action → match-director bar (not any range officer).
        abort_unless($request->user()->can('manage', $match), 403, 'Only match directors can send reports to all shooters.');

        $shooters = $this->reportService->getEmailableShooters($match);

        if ($shooters->isEmpty()) {
            return back()->with('error', 'No shooters with email addresses found.');
        }

        $sent = 0;
        foreach ($shooters as $shooter) {
            $report = $this->reportService->generateReport($match, $shooter);
            Mail::to($shooter->user->email)->queue(new ShooterMatchReport($report));
            $sent++;
        }

        return back()->with('success', "Match reports queued for {$sent} shooters.");
    }

    public function download(Request $request, ShootingMatch $match)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $shooter = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->where('shooters.user_id', $user->id)
            ->where('shooters.status', 'active')
            ->select('shooters.*')
            ->first();

        abort_unless($shooter, 404, 'You did not participate in this match.');

        $pdfBytes = $this->reportService->generatePdfBytes($match, $shooter);
        $filename = Str::slug($match->name) . '-report.pdf';

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
