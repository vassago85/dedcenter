<?php

use App\Concerns\HandlesMatchLifecycleTransitions;
use App\Enums\MatchStatus;
use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Services\AchievementService;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/*
|--------------------------------------------------------------------------
| Match Control Center → Scoring tab.
|--------------------------------------------------------------------------
| Single home for everything that has to do with capturing, publishing,
| and correcting scores during/after the match. Replaces the scattered
| "scoring app" / "publish scores" / "correct scores" buttons that were
| previously sprinkled across the Overview tiles, the bottom of the
| Configuration tab, and the per-squad list inside Squadding.
|
| Lifecycle behaviour:
|   - Pre-Active stages → page surfaces a "scoring is locked" banner +
|     an inert preview. The Open Scoring App button still works (so the
|     MD can open the PWA on a tablet to verify the match downloaded),
|     but the live score visibility toggle and the correction list are
|     hidden until there's actually scoring data to manage.
|   - Active           → primary focus. Open scoring, publish/hide live,
|                        per-squad corrections all foregrounded.
|   - Completed        → corrections + reopen-match are foregrounded
|                        (the "scores are locked unless reopened" message
|                        is the page's primary copy).
*/

new #[Layout('components.layouts.app')]
    class extends Component
    {
        use HandlesMatchLifecycleTransitions;

        public Organization $organization;
        public ShootingMatch $match;

        public function mount(Organization $organization, ShootingMatch $match): void
        {
            // Authorisation mirrors the org hub — anyone who can edit the
            // match in the org context (owner / MD / RO via pivot, or the
            // platform owner / creator) can manage scoring on it.
            if (! $match->userCanEditInOrg(auth()->user())) {
                abort(403, 'You are not authorized to manage scoring for this match.');
            }

            $this->organization = $organization;
            $this->match = $match;
        }

        public function getTitle(): string
        {
            return $this->match->name . ' — Scoring';
        }

        /**
         * Toggle the match's `scores_published` flag — the same logic the
         * old Configuration tab's "Publish Scores / Hide Scores" button
         * had, lifted here so the MD doesn't have to dig through Setup
         * just to flip live-scoreboard visibility. Re-flipping to public
         * also re-runs achievement evaluation in case the MD had to hide
         * scores while fixing a recording error and then re-publish.
         */
        public function toggleScoresPublished(): void
        {
            $newValue = ! $this->match->scores_published;
            $this->match->update(['scores_published' => $newValue]);

            if ($newValue) {
                try {
                    AchievementService::evaluateMatchCompletion($this->match);
                    if ($this->match->royal_flush_enabled) {
                        AchievementService::evaluateRoyalFlushCompletion($this->match);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Achievement evaluation failed', ['error' => $e->getMessage()]);
                }

                // Schedule the post-match notification job ONLY if the
                // match is already Completed — the job emails shooter
                // reports an hour later. If the match hasn't been marked
                // Completed yet, the lifecycle transition into Completed
                // is what schedules notifications, not the publish toggle.
                if ($this->match->status === MatchStatus::Completed) {
                    \App\Jobs\SendPostMatchNotifications::dispatch($this->match)->delay(now()->addHour());
                }
            }

            Flux::toast(
                $newValue ? 'Scores are now live on the public scoreboard.' : 'Scores hidden from public.',
                variant: 'success',
            );
        }

        public function with(): array
        {
            $status = $this->match->status;

            // Headline counts so the page can surface "are we actually
            // recording anything yet?" feedback without making the MD
            // open the scoreboard in another tab to check.
            $shooterIds = $this->match->shooters()->pluck('shooters.id');
            $scoresCount = \App\Models\Score::whereIn('shooter_id', $shooterIds)->count();
            $prsScoresCount = \App\Models\PrsShotScore::whereIn('shooter_id', $shooterIds)->count();
            $elrShotsCount = \App\Models\ElrShot::whereIn('shooter_id', $shooterIds)->count();
            $totalShotsRecorded = $scoresCount + $prsScoresCount + $elrShotsCount;

            $shootersTotal = $this->match->shooters()->count();
            $shootersScored = \App\Models\Shooter::query()
                ->whereIn('id', $shooterIds)
                ->where(function ($q) use ($shooterIds) {
                    $q->whereExists(fn ($s) => $s->select(\DB::raw(1))->from('scores')->whereColumn('scores.shooter_id', 'shooters.id'))
                      ->orWhereExists(fn ($s) => $s->select(\DB::raw(1))->from('prs_shot_scores')->whereColumn('prs_shot_scores.shooter_id', 'shooters.id'))
                      ->orWhereExists(fn ($s) => $s->select(\DB::raw(1))->from('elr_shots')->whereColumn('elr_shots.shooter_id', 'shooters.id'));
                })
                ->count();

            // Squad list for the per-squad correction surface. Only
            // surfaced for standard scoring matches — PRS and ELR have
            // their own correction flows inside the scoring app and the
            // squad-correction Volt page is built for the standard
            // hit/miss matrix only.
            $squads = $this->match->squads()
                ->with('shooters')
                ->orderBy('relay_number')
                ->orderBy('name')
                ->get();

            return [
                'status' => $status,
                'isPreActive' => $status->ordinal() < MatchStatus::Active->ordinal(),
                'isActive' => $status === MatchStatus::Active,
                'isCompleted' => $status === MatchStatus::Completed,
                'isStandardScoring' => ($this->match->scoring_type ?? 'standard') === 'standard',
                'totalShotsRecorded' => $totalShotsRecorded,
                'shootersScored' => $shootersScored,
                'shootersTotal' => $shootersTotal,
                'squads' => $squads,
                'scoresPublished' => (bool) $this->match->scores_published,
                'scoringAppUrl' => url('/score/' . $this->match->id),
                'scoreboardUrl' => route('scoreboard', $this->match),
                'reportsUrl' => route('org.matches.reports', [$this->organization, $this->match]),
            ];
        }
    }; ?>

<div>
    <x-match-control-shell :match="$match" :organization="$organization">

        {{-- ─── Lifecycle context banner ─────────────────────────────── --}}
        @if($isPreActive)
            <div class="mb-4 flex items-start gap-3 rounded-xl border border-amber-500/30 bg-amber-500/8 p-4">
                <x-icon name="lock" class="mt-0.5 h-5 w-5 shrink-0 text-amber-300" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-amber-200">Scoring is locked</p>
                    <p class="mt-0.5 text-xs text-amber-100/75">
                        The match is in <span class="font-semibold">{{ $status->label() }}</span>. The scoring app stays locked until you advance the lifecycle to <span class="font-semibold">Ready</span> (so tablets can download the match) or <span class="font-semibold">Active</span> (live scoring opens). Use the lifecycle stepper above when you're ready.
                    </p>
                </div>
            </div>
        @elseif($isCompleted)
            <div class="mb-4 flex items-start gap-3 rounded-xl border border-zinc-500/30 bg-zinc-500/8 p-4">
                <x-icon name="lock" class="mt-0.5 h-5 w-5 shrink-0 text-zinc-300" />
                <div class="min-w-0 flex-1 sm:flex sm:items-start sm:justify-between sm:gap-4">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-zinc-200">Match is finalised — scores are locked</p>
                        <p class="mt-0.5 text-xs text-zinc-400">
                            New scores can't be captured until you reopen the match. Per-squad corrections below stay available so you can fix recording errors without rolling the whole match back.
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="reopenMatch"
                        wire:confirm="Reopen this match? The scoring app will accept new scores again. Achievements already awarded and emails already sent stay in place."
                        class="mt-3 inline-flex shrink-0 items-center gap-2 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3.5 py-2 text-xs font-bold uppercase tracking-wider text-amber-200 transition-colors hover:border-amber-400 hover:bg-amber-500/20 sm:mt-0"
                    >
                        <x-icon name="refresh-cw" class="h-3.5 w-3.5" />
                        Reopen Match
                    </button>
                </div>
            </div>
        @endif

        {{-- ─── Score capture status strip ───────────────────────────── --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-border bg-surface p-4">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-muted">Shots recorded</p>
                <p class="mt-1 text-2xl font-bold text-primary tabular-nums">{{ number_format($totalShotsRecorded) }}</p>
                <p class="mt-1 text-[11px] text-muted">across all stages</p>
            </div>
            <div class="rounded-xl border border-border bg-surface p-4">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-muted">Shooters scored</p>
                <p class="mt-1 text-2xl font-bold text-primary tabular-nums">{{ $shootersScored }}<span class="text-base text-muted"> / {{ $shootersTotal }}</span></p>
                <p class="mt-1 text-[11px] text-muted">at least one shot logged</p>
            </div>
            <div class="rounded-xl border border-border bg-surface p-4 sm:col-span-1 col-span-2">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-muted">Public scoreboard</p>
                <p class="mt-1 text-2xl font-bold tabular-nums {{ $scoresPublished ? 'text-emerald-300' : 'text-amber-300' }}">
                    {{ $scoresPublished ? 'Live' : 'Hidden' }}
                </p>
                <p class="mt-1 text-[11px] text-muted">{{ $scoresPublished ? 'visible to spectators' : 'staff-only' }}</p>
            </div>
        </div>

        {{-- ─── Primary scoring actions ──────────────────────────────── --}}
        <section class="mt-4 grid gap-4 lg:grid-cols-2">
            {{-- Open scoring app card --}}
            <article class="rounded-2xl border border-border bg-surface p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-accent/30 bg-accent/10 text-accent">
                        <x-icon name="target" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-semibold text-primary">Scoring app</h3>
                        <p class="mt-0.5 text-xs text-muted">The PWA range officers use to capture hits, misses, and not-takens on the line.</p>
                    </div>
                </div>
                <a
                    href="{{ $scoringAppUrl }}"
                    target="_blank"
                    rel="noopener"
                    class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-accent px-4 py-3 text-sm font-bold text-white transition-colors hover:bg-accent-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/60"
                >
                    <x-icon name="external-link" class="h-4 w-4" />
                    Open Scoring App
                </a>
                @if($isPreActive)
                    <p class="mt-2 text-[11px] text-muted">App opens but won't accept new scores until the match is Active.</p>
                @endif
            </article>

            {{-- Score visibility card --}}
            <article class="rounded-2xl border border-border bg-surface p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-sky-400/25 bg-sky-400/10 text-sky-300">
                        <x-icon name="{{ $scoresPublished ? 'eye' : 'eye-off' }}" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-semibold text-primary">Live scoreboard visibility</h3>
                        <p class="mt-0.5 text-xs text-muted">
                            {{ $scoresPublished
                                ? 'Spectators on the public scoreboard see live scores as they\'re captured.'
                                : 'Scores are hidden from spectators. Only staff can see the leaderboard.' }}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="toggleScoresPublished"
                    wire:confirm="{{ $scoresPublished ? 'Hide scores from the public scoreboard?' : 'Publish scores live to the public scoreboard?' }}"
                    class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg border px-4 py-3 text-sm font-bold transition-colors focus:outline-none focus-visible:ring-2 {{ $scoresPublished
                        ? 'border-amber-500/40 bg-amber-500/10 text-amber-200 hover:bg-amber-500/20 focus-visible:ring-amber-400/60'
                        : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200 hover:bg-emerald-500/20 focus-visible:ring-emerald-400/60' }}"
                >
                    <x-icon name="{{ $scoresPublished ? 'eye-off' : 'eye' }}" class="h-4 w-4" />
                    {{ $scoresPublished ? 'Hide Scores' : 'Publish Scores' }}
                </button>
                <a
                    href="{{ $scoreboardUrl }}"
                    target="_blank"
                    rel="noopener"
                    class="mt-2 inline-flex w-full items-center justify-center gap-1.5 text-xs font-semibold text-muted transition-colors hover:text-primary"
                >
                    Preview public scoreboard
                    <x-icon name="external-link" class="h-3 w-3" />
                </a>
            </article>
        </section>

        {{-- ─── Score corrections (per-squad) ────────────────────────── --}}
        @if($isStandardScoring && ($isActive || $isCompleted))
            <section class="mt-4 rounded-2xl border border-border bg-surface p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="flex items-center gap-2 text-base font-semibold text-primary">
                            <x-icon name="wrench" class="h-4 w-4 text-muted" />
                            Score corrections
                        </h3>
                        <p class="mt-0.5 text-xs text-muted">
                            Edit the hit/miss matrix for a squad without re-opening the match. Every change writes an audit entry tied to your account.
                        </p>
                    </div>
                </div>

                @if($squads->isEmpty())
                    <div class="mt-4 rounded-lg border border-dashed border-border bg-surface-2/40 px-4 py-6 text-center text-xs text-muted">
                        No squads exist for this match yet.
                    </div>
                @else
                    <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($squads as $squad)
                            <a
                                href="{{ route('org.matches.squad-correction', [$organization, $match, $squad]) }}"
                                wire:navigate
                                class="group flex items-center justify-between gap-3 rounded-xl border border-border bg-surface-2/40 px-3.5 py-3 transition-colors hover:border-accent/50 hover:bg-surface-2"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-primary">{{ $squad->name }}</p>
                                    <p class="mt-0.5 text-[11px] text-muted">
                                        Relay {{ $squad->relay_number ?? '—' }} · {{ $squad->shooters->count() }} {{ \Illuminate\Support\Str::plural('shooter', $squad->shooters->count()) }}
                                    </p>
                                </div>
                                <x-icon name="arrow-right" class="h-4 w-4 shrink-0 text-muted transition-transform group-hover:translate-x-0.5 group-hover:text-accent" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        @elseif(! $isStandardScoring && ($isActive || $isCompleted))
            <section class="mt-4 rounded-2xl border border-border bg-surface p-5 sm:p-6">
                <h3 class="flex items-center gap-2 text-base font-semibold text-primary">
                    <x-icon name="wrench" class="h-4 w-4 text-muted" />
                    Score corrections
                </h3>
                <p class="mt-1.5 text-xs text-muted">
                    {{ strtoupper($match->scoring_type) }} scoring uses per-shooter corrections directly inside the scoring app — open the app on a tablet or in your browser to amend a shot.
                </p>
            </section>
        @endif

        {{-- ─── Live corrections feed ─────────────────────────────────
             Live during the match so the MD knows the moment an SO files
             a correction. Same component is rendered on the Reports tab
             with `variant=full` for the permanent post-match record. --}}
        @if($isActive || $isCompleted)
            <div class="mt-4">
                <x-match-corrections-feed :match="$match" variant="compact" :limit="15" />
            </div>
        @endif

        {{-- ─── Completed → next step pointer ────────────────────────── --}}
        @if($isCompleted)
            <section class="mt-4 rounded-2xl border border-border bg-surface p-5 sm:p-6">
                <div class="sm:flex sm:items-center sm:justify-between sm:gap-4">
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-primary">Ready to publish results?</h3>
                        <p class="mt-0.5 text-xs text-muted">Head over to Reports to download standings, preview the shooter report, or send post-match emails.</p>
                    </div>
                    <a
                        href="{{ $reportsUrl }}"
                        wire:navigate
                        class="mt-3 inline-flex items-center justify-center gap-2 rounded-lg bg-accent px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-accent-hover sm:mt-0"
                    >
                        <x-icon name="file-text" class="h-4 w-4" />
                        Open Reports
                    </a>
                </div>
            </section>
        @endif

    </x-match-control-shell>
</div>
