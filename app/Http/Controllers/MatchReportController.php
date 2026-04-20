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
     * $orgOrAdmin is either an Organization (org.* routes) or the literal
     * 'admin' string placeholder from the admin.* routes — in both cases we
     * only care about the bound ShootingMatch for data. The returned view
     * is the email template with $showActions=true, which renders a
     * Download PDF button above the report.
     */
    public function preview(Request $request, $orgOrAdmin, ShootingMatch $match)
    {
        $shooter = $this->resolveShooter($request, $match);

        if (! $shooter) {
            abort(404, 'No active shooters in this match.');
        }

        $report = $this->reportService->generateReport($match, $shooter);

        $isOrgScope = $orgOrAdmin instanceof Organization;
        $downloadUrl = $isOrgScope
            ? route('org.matches.export.pdf-shooter-report', [$orgOrAdmin, $match, $shooter])
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

        $report = $this->reportService->generateReport($match, $shooter);

        $downloadUrl = $this->resolvePublicDownloadUrl($request, $match, $shooter);

        return view('emails.shooter-match-report', [
            'report' => $report,
            'showActions' => true,
            'downloadUrl' => $downloadUrl,
        ]);
    }

    private function resolveShooter(Request $request, ShootingMatch $match): ?Shooter
    {
        $shooterId = $request->query('shooter');

        if ($shooterId) {
            return Shooter::findOrFail($shooterId);
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
            return route('matches.my-report', $match);
        }

        return null;
    }

    public function send(Request $request, $orgOrAdmin, ShootingMatch $match)
    {
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
