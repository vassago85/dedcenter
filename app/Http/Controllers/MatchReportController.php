<?php

namespace App\Http\Controllers;

use App\Mail\ShooterMatchReport;
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

    public function preview(Request $request, $orgOrAdmin, ShootingMatch $match)
    {
        $shooterId = $request->query('shooter');

        $shooter = $shooterId
            ? Shooter::findOrFail($shooterId)
            : $match->squads()->with('shooters')->get()
                ->flatMap->shooters
                ->where('status', 'active')
                ->first();

        if (! $shooter) {
            abort(404, 'No active shooters in this match.');
        }

        $report = $this->reportService->generateReport($match, $shooter);

        return view('emails.shooter-match-report', ['report' => $report]);
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
