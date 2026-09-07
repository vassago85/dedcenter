<?php

use App\Concerns\HandlesMatchLifecycleTransitions;
use App\Enums\MatchStatus;
use App\Models\ShootingMatch;
use App\Services\MatchReportService;
use Flux\Flux;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/*
|--------------------------------------------------------------------------
| Match Control Center → Reports tab (platform admin context).
|--------------------------------------------------------------------------
| Mirror of the org reports page using admin route names. The `admin`
| middleware on the route gates access — no extra check needed here.
|
| Layout is a per-type dispatcher (Phase 1 of the "reports per match
| type" refactor). Shared surfaces stay at the top of the shell:
|   1. Pre-Active banner (if the match hasn't started scoring yet)
|   2. Public scoreboard tile + Full match report tile
|   3. Shooter match reports section (preview + send-now)
| Then the per-type download catalogue includes one of:
|   resources/views/reports/panels/{standard|royal-flush|prs|elr|alrha}.blade.php
| Each panel decides which CSV / PDF / HTML report tiles are relevant
| for its type and in what order, so the ELR MD doesn't see empty
| "Standings PDF" tiles that pull from a table ELR doesn't populate,
| and the ALRHA MD sees per-class messaging on the ALRHA prize-book
| callout instead of the generic "Standings PDF" copy.
*/

new #[Layout('components.layouts.app')]
class extends Component
{
    use HandlesMatchLifecycleTransitions;

    public ShootingMatch $match;

    public function mount(ShootingMatch $match): void
    {
        $this->match = $match;
    }

    public function getTitle(): string
    {
        return $this->match->name . ' — Reports';
    }

    public function sendMatchReports(): void
    {
        $service = app(MatchReportService::class);
        $shooters = $service->getEmailableShooters($this->match);

        if ($shooters->isEmpty()) {
            Flux::toast('No shooters with linked email addresses found — nothing to send.', variant: 'warning');
            return;
        }

        foreach ($shooters as $shooter) {
            $report = $service->generateReport($this->match, $shooter);
            Mail::to($shooter->user->email)
                ->queue(new \App\Mail\ShooterMatchReport($report));
        }

        Flux::toast("Match reports queued for {$shooters->count()} shooters.", variant: 'success');
    }

    public function with(): array
    {
        $status = $this->match->status;

        $scoringType = $this->match->scoring_type ?? 'standard';
        $isPrs = $scoringType === 'prs';
        $isElr = $scoringType === 'elr';
        $isAlrha = $scoringType === 'alrha';
        $isRoyalFlush = ! $isPrs && ! $isElr && ! $isAlrha && (bool) $this->match->royal_flush_enabled;

        // Panel dispatch. Order matters: ALRHA/PRS/ELR override any
        // Royal Flush overlay because the RF flag can only meaningfully
        // apply to a Standard-scoring match. RF wins over Standard.
        $reportsVariant = match (true) {
            $isAlrha       => 'alrha',
            $isElr         => 'elr',
            $isPrs         => 'prs',
            $isRoyalFlush  => 'royal-flush',
            default        => 'standard',
        };

        $shooterCount = $this->match->shooters()->count();
        $emailableCount = app(MatchReportService::class)->getEmailableShooters($this->match)->count();

        // Route-name map. The org version of this page builds the same
        // shape with org.* names so both surfaces can @include the same
        // reports.panels.* partials without duplication.
        $urls = [
            'csv' => [
                'standings' => route('admin.matches.export.standings', $this->match),
                'detailed'  => route('admin.matches.export.detailed', $this->match),
                'rfShots'   => route('matches.report.royal-flush', $this->match),
            ],
            'pdf' => [
                'standings'        => route('admin.matches.export.pdf-standings', $this->match),
                'detailed'         => route('admin.matches.export.pdf-detailed', $this->match),
                'postMatch'        => route('admin.matches.export.pdf-post-match', $this->match),
                'executiveSummary' => route('admin.matches.export.pdf-executive-summary', $this->match),
            ],
            'reports' => [
                'royalFlushHtml' => route('matches.report.royal-flush', $this->match),
            ],
            'elr' => $isElr ? [
                'rankingsOverall'   => route('admin.matches.export.elr-rankings', $this->match).'?view=overall',
                'rankingsTeams'     => route('admin.matches.export.elr-rankings', $this->match).'?view=teams',
                'rankingsDivisions' => route('admin.matches.export.elr-rankings', $this->match).'?view=divisions',
                'shotsTemplate'     => route('scoreboard.export.elr-shots', $this->match),
                'pdfRankings'       => route('admin.matches.export.pdf-elr-rankings', $this->match),
            ] : null,
        ];

        return [
            'status' => $status,
            'isPreActive' => $status->ordinal() < MatchStatus::Active->ordinal(),
            'isCompleted' => $status === MatchStatus::Completed,
            'reportsVariant' => $reportsVariant,
            'shooterCount' => $shooterCount,
            'emailableCount' => $emailableCount,
            'scoreboardUrl' => route('scoreboard', $this->match),
            'fullMatchReportUrl' => route('admin.matches.full-match-report', $this->match),
            'previewReportUrl' => route('admin.matches.report.preview', $this->match),
            'urls' => $urls,
        ];
    }
}; ?>

<div>
    <x-match-control-shell :match="$match">

        @if($isPreActive)
            <div class="mb-4 flex items-start gap-3 rounded-xl border border-zinc-500/30 bg-zinc-500/8 p-4">
                <x-icon name="clock" class="mt-0.5 h-5 w-5 shrink-0 text-zinc-300" />
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-zinc-200">Reports come alive once scoring starts</p>
                    <p class="mt-0.5 text-xs text-zinc-400">
                        Match is in <span class="font-semibold">{{ $status->label() }}</span>. CSV / PDF downloads will populate as scores are captured.
                    </p>
                </div>
            </div>
        @endif

        <section class="grid gap-4 lg:grid-cols-2">
            <article class="rounded-2xl border border-border bg-surface p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-amber-400/25 bg-amber-400/10 text-amber-300">
                        <x-icon name="trophy" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-semibold text-primary">Public scoreboard</h3>
                        <p class="mt-0.5 text-xs text-muted">Where shooters and spectators see the live standings.</p>
                    </div>
                </div>
                <a
                    href="{{ $scoreboardUrl }}"
                    target="_blank"
                    rel="noopener"
                    class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-amber-500 px-4 py-3 text-sm font-bold text-zinc-950 transition-colors hover:bg-amber-400"
                >
                    <x-icon name="external-link" class="h-4 w-4" />
                    Open Scoreboard
                </a>
            </article>

            <article class="rounded-2xl border border-border bg-surface p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-accent/30 bg-accent/10 text-accent">
                        <x-icon name="file-text" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-semibold text-primary">Full match report</h3>
                        <p class="mt-0.5 text-xs text-muted">The long-form post-match read: podium, per-stage breakdowns, badges, RF highlights.</p>
                    </div>
                </div>
                <a
                    href="{{ $fullMatchReportUrl }}"
                    target="_blank"
                    rel="noopener"
                    class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-accent px-4 py-3 text-sm font-bold text-white transition-colors hover:bg-accent-hover"
                >
                    <x-icon name="external-link" class="h-4 w-4" />
                    Open Full Report
                </a>
            </article>
        </section>

        <section class="mt-4 rounded-2xl border border-border bg-surface p-5 sm:p-6">
            <div class="flex items-start gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-emerald-500/25 bg-emerald-500/10 text-emerald-300">
                    <x-icon name="inbox" class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-semibold text-primary">Shooter match reports</h3>
                    <p class="mt-0.5 text-xs text-muted">
                        Personal post-match email with their score, placement, badges earned, and a shareable link. Auto-sent 1 hour after the match is marked Completed.
                    </p>
                    <p class="mt-1.5 text-[11px] text-muted">
                        <span class="font-semibold tabular-nums text-secondary">{{ $emailableCount }}</span>
                        of <span class="tabular-nums">{{ $shooterCount }}</span> shooters have a linked account email.
                    </p>
                </div>
            </div>
            <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                <a
                    href="{{ $previewReportUrl }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg border border-border bg-surface-2 px-4 py-2.5 text-sm font-bold text-secondary transition-colors hover:border-accent/50 hover:text-primary"
                >
                    <x-icon name="eye" class="h-4 w-4" />
                    Preview shooter report
                </a>
                <button
                    type="button"
                    wire:click="sendMatchReports"
                    wire:confirm="Send the shooter report email NOW to all linked recipients? Reports are also sent automatically 1 hour after the match is marked Completed — only send manually if you've delayed completion or want to resend."
                    @disabled($emailableCount === 0)
                    class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    <x-icon name="share" class="h-4 w-4" />
                    Send Reports Now
                </button>
            </div>
        </section>

        {{-- Per-type report catalogue. Panel decides which downloads to
             show and in what order. --}}
        @include('reports.panels.' . $reportsVariant, ['urls' => $urls])

        <section class="mt-4">
            <x-match-corrections-feed :match="$match" variant="full" :limit="50" />
        </section>

    </x-match-control-shell>
</div>
