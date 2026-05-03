<?php

use App\Concerns\HandlesMatchLifecycleTransitions;
use App\Enums\MatchStatus;
use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Services\MatchReportService;
use Flux\Flux;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/*
|--------------------------------------------------------------------------
| Match Control Center → Reports tab.
|--------------------------------------------------------------------------
| Becomes the page's primary focus once the match is Completed: every
| post-match deliverable lives here — public scoreboard link, CSV
| downloads (standings, full results, RF shots), PDF downloads
| (standings PDF, full match report HTML, executive summary, post-match
| narrative), shooter-report preview + send-now action, and the
| auto-send status indicator.
|
| Pre-Completed matches still get the page (so the MD can preview a
| "what would the report look like right now?" rendering mid-match) but
| the destructive Send Reports Now action is gated behind a Completed-
| or-confirmed warning.
*/

new #[Layout('components.layouts.app')]
    class extends Component
    {
        use HandlesMatchLifecycleTransitions;

        public Organization $organization;
        public ShootingMatch $match;

        public function mount(Organization $organization, ShootingMatch $match): void
        {
            if (! $match->userCanEditInOrg(auth()->user())) {
                abort(403, 'You are not authorized to manage reports for this match.');
            }

            $this->organization = $organization;
            $this->match = $match;
        }

        public function getTitle(): string
        {
            return $this->match->name . ' — Reports';
        }

        /**
         * Manual trigger for the post-match shooter report email blast.
         * Lifted from the old Configuration tab so the action lives on
         * the page that's actually called Reports. Uses the existing
         * MatchReportService::getEmailableShooters() resolver — same set
         * of recipients the auto-send (1 hour after Completed) targets.
         */
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

            $isPrs = ($this->match->scoring_type ?? 'standard') === 'prs';
            $isElr = ($this->match->scoring_type ?? 'standard') === 'elr';
            $isStandard = ! $isPrs && ! $isElr;

            $shooterCount = $this->match->shooters()->count();
            $emailableCount = app(MatchReportService::class)->getEmailableShooters($this->match)->count();

            return [
                'status' => $status,
                'isPreActive' => $status->ordinal() < MatchStatus::Active->ordinal(),
                'isCompleted' => $status === MatchStatus::Completed,
                'isPrs' => $isPrs,
                'isElr' => $isElr,
                'isStandard' => $isStandard,
                'isRoyalFlush' => (bool) $this->match->royal_flush_enabled,
                'shooterCount' => $shooterCount,
                'emailableCount' => $emailableCount,
                'scoreboardUrl' => route('scoreboard', $this->match),
                'fullMatchReportUrl' => route('org.matches.full-match-report', [$this->organization, $this->match]),
                'previewReportUrl' => route('org.matches.report.preview', [$this->organization, $this->match]),
                'csv' => [
                    'standings' => route('org.matches.export.standings', [$this->organization, $this->match]),
                    'detailed'  => route('org.matches.export.detailed', [$this->organization, $this->match]),
                    'rfShots'   => route('matches.report.royal-flush', $this->match),
                ],
                'pdf' => [
                    'standings'        => route('org.matches.export.pdf-standings', [$this->organization, $this->match]),
                    'detailed'         => route('org.matches.export.pdf-detailed', [$this->organization, $this->match]),
                    'postMatch'        => route('org.matches.export.pdf-post-match', [$this->organization, $this->match]),
                    'executiveSummary' => route('org.matches.export.pdf-executive-summary', [$this->organization, $this->match]),
                ],
            ];
        }
    }; ?>

<div>
    <x-match-control-shell :match="$match" :organization="$organization">

        {{-- ─── Pre-active context banner ────────────────────────────── --}}
        @if($isPreActive)
            <div class="mb-4 flex items-start gap-3 rounded-xl border border-zinc-500/30 bg-zinc-500/8 p-4">
                <x-icon name="clock" class="mt-0.5 h-5 w-5 shrink-0 text-zinc-300" />
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-zinc-200">Reports come alive once scoring starts</p>
                    <p class="mt-0.5 text-xs text-zinc-400">
                        Match is in <span class="font-semibold">{{ $status->label() }}</span>. CSV / PDF downloads will populate as scores are captured. Previews still work — they'll just show empty results.
                    </p>
                </div>
            </div>
        @endif

        {{-- ─── Headline: scoreboard + full match report ─────────────── --}}
        <section class="grid gap-4 lg:grid-cols-2">
            {{-- Public scoreboard --}}
            <article class="rounded-2xl border border-border bg-surface p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-amber-400/25 bg-amber-400/10 text-amber-300">
                        <x-icon name="trophy" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-semibold text-primary">Public scoreboard</h3>
                        <p class="mt-0.5 text-xs text-muted">Where shooters and spectators see the live standings. Share the link or QR.</p>
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

            {{-- Full match report (HTML) --}}
            <article class="rounded-2xl border border-border bg-surface p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-accent/30 bg-accent/10 text-accent">
                        <x-icon name="file-text" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-semibold text-primary">Full match report</h3>
                        <p class="mt-0.5 text-xs text-muted">The long-form post-match read: podium, per-stage breakdowns, badges, RF highlights when applicable.</p>
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

        {{-- ─── Shooter emails ───────────────────────────────────────── --}}
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

        {{-- ─── Downloads (CSV) ──────────────────────────────────────── --}}
        <section class="mt-4 rounded-2xl border border-border bg-surface p-5 sm:p-6">
            <h3 class="flex items-center gap-2 text-base font-semibold text-primary">
                <x-icon name="file-down" class="h-4 w-4 text-muted" />
                CSV downloads
            </h3>
            <p class="mt-0.5 text-xs text-muted">Spreadsheets for season standings, archive, post-event analysis.</p>

            <div class="mt-4 grid gap-2 sm:grid-cols-3">
                <a
                    href="{{ $csv['standings'] }}"
                    class="group flex items-center gap-3 rounded-xl border border-border bg-surface-2/40 px-3.5 py-3 transition-colors hover:border-accent/50 hover:bg-surface-2"
                >
                    <x-icon name="download" class="h-4 w-4 shrink-0 text-muted group-hover:text-accent" />
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-primary">Standings</p>
                        <p class="text-[11px] text-muted">final placements only</p>
                    </div>
                </a>
                <a
                    href="{{ $csv['detailed'] }}"
                    class="group flex items-center gap-3 rounded-xl border border-border bg-surface-2/40 px-3.5 py-3 transition-colors hover:border-accent/50 hover:bg-surface-2"
                >
                    <x-icon name="download" class="h-4 w-4 shrink-0 text-muted group-hover:text-accent" />
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-primary">Full results</p>
                        <p class="text-[11px] text-muted">per-stage breakdown</p>
                    </div>
                </a>
                @if($isRoyalFlush)
                    <a
                        href="{{ $csv['rfShots'] }}"
                        class="group flex items-center gap-3 rounded-xl border border-border bg-surface-2/40 px-3.5 py-3 transition-colors hover:border-accent/50 hover:bg-surface-2"
                    >
                        <x-icon name="download" class="h-4 w-4 shrink-0 text-muted group-hover:text-accent" />
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-primary">RF shots</p>
                            <p class="text-[11px] text-muted">per-shot Royal Flush</p>
                        </div>
                    </a>
                @endif
            </div>
        </section>

        {{-- ─── Downloads (PDF) ──────────────────────────────────────── --}}
        <section class="mt-4 rounded-2xl border border-border bg-surface p-5 sm:p-6">
            <h3 class="flex items-center gap-2 text-base font-semibold text-primary">
                <x-icon name="file-text" class="h-4 w-4 text-muted" />
                PDF downloads
            </h3>
            <p class="mt-0.5 text-xs text-muted">Print-ready PDFs for the prize-giving table, sponsor packs, and post-match comms.</p>

            <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <a
                    href="{{ $pdf['standings'] }}"
                    class="group flex items-center gap-3 rounded-xl border border-border bg-surface-2/40 px-3.5 py-3 transition-colors hover:border-accent/50 hover:bg-surface-2"
                >
                    <x-icon name="file-text" class="h-4 w-4 shrink-0 text-muted group-hover:text-accent" />
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-primary">Standings PDF</p>
                        <p class="text-[11px] text-muted">single-page leaderboard</p>
                    </div>
                </a>
                <a
                    href="{{ $pdf['detailed'] }}"
                    class="group flex items-center gap-3 rounded-xl border border-border bg-surface-2/40 px-3.5 py-3 transition-colors hover:border-accent/50 hover:bg-surface-2"
                >
                    <x-icon name="file-text" class="h-4 w-4 shrink-0 text-muted group-hover:text-accent" />
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-primary">Detailed PDF</p>
                        <p class="text-[11px] text-muted">per-stage detail</p>
                    </div>
                </a>
                <a
                    href="{{ $pdf['postMatch'] }}"
                    class="group flex items-center gap-3 rounded-xl border border-border bg-surface-2/40 px-3.5 py-3 transition-colors hover:border-accent/50 hover:bg-surface-2"
                >
                    <x-icon name="file-text" class="h-4 w-4 shrink-0 text-muted group-hover:text-accent" />
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-primary">Post-match narrative</p>
                        <p class="text-[11px] text-muted">storytelling format</p>
                    </div>
                </a>
                <a
                    href="{{ $pdf['executiveSummary'] }}"
                    class="group flex items-center gap-3 rounded-xl border border-border bg-surface-2/40 px-3.5 py-3 transition-colors hover:border-accent/50 hover:bg-surface-2"
                >
                    <x-icon name="file-text" class="h-4 w-4 shrink-0 text-muted group-hover:text-accent" />
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-primary">Executive summary</p>
                        <p class="text-[11px] text-muted">sponsor / board pack</p>
                    </div>
                </a>
            </div>
        </section>

    </x-match-control-shell>
</div>
