<?php

use App\Concerns\HandlesMatchLifecycleTransitions;
use App\Enums\MatchStatus;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Services\MatchStandingsService;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    class extends Component {
        use HandlesMatchLifecycleTransitions;

        public ShootingMatch $match;

        public function mount(ShootingMatch $match): void
        {
            $this->match = $match;
        }

        public function getTitle(): string
        {
            return 'Admin — '.$this->match->name;
        }

        /**
         * Flip a shooter's attendance status post-match.
         *
         * Admins commonly score a full sheet of misses for a shooter who never
         * actually showed up. Flipping them to "no_show" keeps the row visible
         * (with a clear badge) while pulling them out of ranking + field stats
         * so the executive summary / shooter reports are accurate.
         *
         * DQ is deliberately NOT handled here — it goes through the existing
         * DisqualificationController so the audit trail stays intact.
         */
        public function setShooterStatus(int $shooterId, string $status): void
        {
            if (! in_array($status, ['active', 'no_show', 'withdrawn'], true)) {
                Flux::toast('Invalid status.', variant: 'danger');
                return;
            }

            $shooter = Shooter::findOrFail($shooterId);

            if ($shooter->squad?->match_id !== $this->match->id) {
                Flux::toast('Shooter is not part of this match.', variant: 'danger');
                return;
            }

            if ($shooter->isDq()) {
                Flux::toast("{$shooter->name} is disqualified — revoke the DQ first.", variant: 'danger');
                return;
            }

            $shooter->update(['status' => $status]);

            $label = match ($status) {
                'active' => 'marked as present',
                'no_show' => 'marked as no-show — excluded from ranking and field stats',
                'withdrawn' => 'marked as withdrawn',
            };

            Flux::toast("{$shooter->name} {$label}.", variant: $status === 'active' ? 'success' : 'warning');
        }

        public function with(): array
        {
            $status = $this->match->status;

            $standings = collect();
            if (! $this->match->isPrs() && ! $this->match->isElr()) {
                $standings = (new MatchStandingsService())->standardStandings($this->match);
            }

            $topStandings = $standings->filter(fn ($r) => $r->rank !== null)->take(5);

            $allShooters = Shooter::query()
                ->join('squads', 'shooters.squad_id', '=', 'squads.id')
                ->leftJoin('scores', 'shooters.id', '=', 'scores.shooter_id')
                ->where('squads.match_id', $this->match->id)
                ->select('shooters.*', 'squads.name as squad_name')
                ->selectRaw('COUNT(scores.id) as scored_shots')
                ->groupBy('shooters.id', 'squads.name')
                ->orderBy('squads.name')
                ->orderBy('shooters.sort_order')
                ->get();

            $attendanceCounts = [
                'active' => $allShooters->where('status', 'active')->count(),
                'no_show' => $allShooters->where('status', 'no_show')->count(),
                'withdrawn' => $allShooters->where('status', 'withdrawn')->count(),
                'dq' => $allShooters->where('status', 'dq')->count(),
            ];

            $squadsCount = $this->match->squads()->count();
            $sideBetBoughtIn = $this->match->side_bet_enabled ? $this->match->sideBetShooters()->count() : 0;

            return [
                'status' => $status,
                'topStandings' => $topStandings,
                'allShooters' => $allShooters,
                'attendanceCounts' => $attendanceCounts,
                'registrationsCount' => $this->match->registrations()->count(),
                'shootersCount' => $this->match->shooters()->count(),
                'scoresCount' => \App\Models\Score::whereIn('shooter_id', $this->match->shooters()->pluck('shooters.id'))->count(),
                'squadsCount' => $squadsCount,
                'sideBetBoughtIn' => $sideBetBoughtIn,
                'isCompleted' => $status === MatchStatus::Completed,
                'isPreActive' => $status->ordinal() < MatchStatus::Active->ordinal(),
                'isRoyalFlush' => (bool) $this->match->royal_flush_enabled,
                'setupUrl' => route('admin.matches.edit', $match),
                'squaddingUrl' => route('admin.matches.squadding', $match),
                'scoringUrl' => route('admin.matches.scoring', $match),
                'reportsUrl' => route('admin.matches.reports', $match),
                'sideBetReportUrl' => $match->side_bet_enabled ? route('admin.matches.side-bet-report', $match) : null,
            ];
        }
    }; ?>

<div>
    <x-match-control-shell :match="$match">

        {{-- ─── Key stats ─────────────────────────────────────────────── --}}
        <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-border bg-surface p-4">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-muted">Registrations</p>
                <p class="mt-1 text-2xl font-bold text-primary tabular-nums">{{ $registrationsCount }}</p>
                <p class="mt-1 text-[11px] text-muted">total sign-ups</p>
            </div>
            <div class="rounded-xl border border-border bg-surface p-4">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-muted">Shooters</p>
                <p class="mt-1 text-2xl font-bold text-primary tabular-nums">{{ $shootersCount }}</p>
                <p class="mt-1 text-[11px] text-muted">{{ $squadsCount }} {{ \Illuminate\Support\Str::plural('squad', $squadsCount) }}</p>
            </div>
            <div class="rounded-xl border border-border bg-surface p-4">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-muted">Shots recorded</p>
                <p class="mt-1 text-2xl font-bold text-primary tabular-nums">{{ $scoresCount }}</p>
                <p class="mt-1 text-[11px] text-muted">{{ $match->scores_published ? 'live to public' : 'staff-only' }}</p>
            </div>
            @if($match->side_bet_enabled)
                <a href="{{ $sideBetReportUrl }}" wire:navigate
                   class="rounded-xl border border-amber-500/40 bg-amber-500/8 p-4 transition-colors hover:border-amber-400 hover:bg-amber-500/15">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-300">Side Bet Buy-In</p>
                    <p class="mt-1 text-2xl font-bold text-amber-200 tabular-nums">{{ $sideBetBoughtIn }}</p>
                    <p class="mt-1 text-[11px] text-amber-200/70">of {{ $shootersCount }} shooters</p>
                </a>
            @else
                <div class="rounded-xl border border-border bg-surface p-4">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-muted">Attendance</p>
                    <p class="mt-1 text-2xl font-bold text-primary tabular-nums">{{ $attendanceCounts['active'] }}</p>
                    <p class="mt-1 text-[11px] text-muted">
                        @if($attendanceCounts['no_show']) {{ $attendanceCounts['no_show'] }} no-show · @endif
                        @if($attendanceCounts['dq']) {{ $attendanceCounts['dq'] }} DQ · @endif
                        active
                    </p>
                </div>
            @endif
        </section>

        {{-- ─── Lifecycle-aware quick actions ─────────────────────────── --}}
        @php
            $cards = [];

            if ($status === MatchStatus::Draft || $status === MatchStatus::PreRegistration) {
                $cards[] = ['icon' => 'settings',  'title' => 'Finish Setup',     'subtitle' => 'Stages, squads capacity, scoring, fees.', 'href' => $setupUrl,    'tone' => 'accent'];
            }

            if (in_array($status, [MatchStatus::RegistrationOpen, MatchStatus::RegistrationClosed], true)) {
                $cards[] = ['icon' => 'settings',  'title' => 'Review Setup',     'subtitle' => 'Tweak stages or scoring before squadding.', 'href' => $setupUrl,  'tone' => 'muted'];
                $cards[] = ['icon' => 'users',     'title' => 'Plan Squadding',   'subtitle' => 'Build relays and capacity for the day.',    'href' => $squaddingUrl, 'tone' => 'accent'];
            }

            if (in_array($status, [MatchStatus::SquaddingOpen, MatchStatus::SquaddingClosed, MatchStatus::Ready], true)) {
                $cards[] = ['icon' => 'users',     'title' => 'Squadding',        'subtitle' => 'Squads, walk-ins, randomize relays.',       'href' => $squaddingUrl, 'tone' => 'accent'];
                $cards[] = ['icon' => 'target',    'title' => 'Scoring',          'subtitle' => 'Open the scoring app + lock visibility.',   'href' => $scoringUrl,   'tone' => 'muted'];
            }

            if ($status === MatchStatus::Active) {
                $cards[] = ['icon' => 'target',    'title' => 'Scoring',          'subtitle' => 'Open the app, publish/hide live scores.',  'href' => $scoringUrl, 'tone' => 'accent'];
                $cards[] = ['icon' => 'users',     'title' => 'Squadding',        'subtitle' => 'Late additions / move shooters mid-match.', 'href' => $squaddingUrl, 'tone' => 'muted'];
            }

            if ($status === MatchStatus::Completed) {
                $cards[] = ['icon' => 'file-text', 'title' => 'Reports',          'subtitle' => 'Send shooter emails, download CSVs / PDFs.', 'href' => $reportsUrl,  'tone' => 'accent'];
                $cards[] = ['icon' => 'target',    'title' => 'Score corrections','subtitle' => 'Fix recording errors after the fact.',       'href' => $scoringUrl,  'tone' => 'muted'];
            }

            if ($match->side_bet_enabled && $status->ordinal() >= MatchStatus::SquaddingOpen->ordinal()) {
                $cards[] = ['icon' => 'trophy', 'title' => $status === MatchStatus::Completed ? 'Side Bet Result' : 'Side Bet Buy-In', 'subtitle' => $status === MatchStatus::Completed ? 'Winner + cascade payouts.' : "{$sideBetBoughtIn} in the pot so far.", 'href' => $sideBetReportUrl, 'tone' => 'amber'];
            }
        @endphp

        @if(! empty($cards))
            <section class="mt-4">
                <h2 class="mb-3 text-[11px] font-bold uppercase tracking-wider text-muted">What's next</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($cards as $card)
                        @php
                            $cardCls = match ($card['tone']) {
                                'accent' => 'border-accent/40 bg-accent/5 hover:border-accent hover:bg-accent/10',
                                'amber'  => 'border-amber-500/40 bg-amber-500/5 hover:border-amber-400 hover:bg-amber-500/10',
                                default  => 'border-border bg-surface hover:border-accent/40 hover:bg-surface-2/40',
                            };
                            $iconCls = match ($card['tone']) {
                                'accent' => 'border-accent/30 bg-accent/15 text-accent',
                                'amber'  => 'border-amber-500/30 bg-amber-500/15 text-amber-300',
                                default  => 'border-border bg-surface-2 text-muted',
                            };
                        @endphp
                        <a href="{{ $card['href'] }}" wire:navigate
                           class="group flex items-center gap-3 rounded-xl border p-4 transition-colors {{ $cardCls }}">
                            <div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg border {{ $iconCls }}">
                                <x-icon name="{{ $card['icon'] }}" class="h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-primary truncate">{{ $card['title'] }}</p>
                                <p class="mt-0.5 text-[11px] text-muted truncate">{{ $card['subtitle'] }}</p>
                            </div>
                            <x-icon name="arrow-right" class="h-4 w-4 shrink-0 text-muted transition-transform group-hover:translate-x-0.5 group-hover:text-accent" />
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ─── Top 5 standings ───────────────────────────────────────── --}}
        @if($topStandings->isNotEmpty())
            <section class="mt-4 rounded-2xl border border-border bg-surface overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b border-border bg-surface-2/30 px-5 py-3">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-primary">Top 5 — weighted standings</h2>
                        <p class="text-xs text-muted">Same formula as the detailed scoreboard and PDF exports.</p>
                    </div>
                    <a href="{{ route('scoreboard', $match) }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-2/60 px-3 py-1.5 text-xs font-semibold text-secondary transition-colors hover:border-accent/50 hover:text-primary">
                        Full Scoreboard
                        <x-icon name="external-link" class="h-3 w-3" />
                    </a>
                </div>
                <div class="divide-y divide-border/40">
                    @foreach($topStandings as $row)
                        <div wire:key="top-{{ $row->shooter_id }}" class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full font-bold text-sm
                                    {{ $row->rank === 1 ? 'bg-amber-500 text-zinc-950' : ($row->rank === 2 ? 'bg-zinc-400 text-zinc-950' : ($row->rank === 3 ? 'bg-orange-700 text-white' : 'bg-surface-2 text-muted')) }}">
                                    {{ $row->rank }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-primary truncate">{{ $row->name }}</p>
                                    <p class="text-xs text-muted truncate">{{ $row->squad }} · {{ $row->hits }} hits / {{ $row->misses }} misses</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 text-right">
                                <span class="text-lg font-bold text-primary tabular-nums">{{ number_format($row->total_score, 1) }}</span>
                                @if($isCompleted)
                                    <a href="{{ route('admin.matches.export.pdf-shooter-report', [$match, $row->shooter_id]) }}"
                                       title="Download individual shooter PDF"
                                       class="inline-flex items-center gap-1 rounded-lg border border-border bg-surface-2/50 px-2 py-1 text-xs text-muted transition hover:border-accent hover:text-primary">
                                        <x-icon name="file-text" class="h-3.5 w-3.5" />
                                        PDF
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ─── Attendance correction ─────────────────────────────────── --}}
        @if($allShooters->isNotEmpty() && ! $isPreActive)
            <section class="mt-4 overflow-hidden rounded-2xl border border-border bg-surface" x-data="{ collapsed: {{ $isCompleted ? 'false' : 'true' }} }">
                <button type="button" @click="collapsed = !collapsed"
                        class="flex w-full items-center justify-between gap-3 border-b border-border bg-surface-2/30 px-5 py-3 text-left">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-primary">Attendance &amp; status</h2>
                        <p class="text-xs text-muted">
                            Flip any shooter to <span class="text-zinc-300">No-Show</span> / <span class="text-amber-300">Withdrawn</span> to correct post-match statistics.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="hidden items-center gap-2 text-[11px] font-medium sm:flex">
                            <span class="rounded-full bg-emerald-500/10 px-2 py-0.5 text-emerald-300">{{ $attendanceCounts['active'] }} present</span>
                            @if($attendanceCounts['no_show'] > 0)
                                <span class="rounded-full bg-zinc-500/10 px-2 py-0.5 text-zinc-300">{{ $attendanceCounts['no_show'] }} no-show</span>
                            @endif
                            @if($attendanceCounts['withdrawn'] > 0)
                                <span class="rounded-full bg-amber-500/10 px-2 py-0.5 text-amber-300">{{ $attendanceCounts['withdrawn'] }} withdrawn</span>
                            @endif
                            @if($attendanceCounts['dq'] > 0)
                                <span class="rounded-full bg-red-500/10 px-2 py-0.5 text-red-300">{{ $attendanceCounts['dq'] }} DQ</span>
                            @endif
                        </span>
                        <x-icon name="chevron-down" class="h-4 w-4 text-muted transition" x-bind:class="collapsed ? '' : 'rotate-180'" />
                    </div>
                </button>
                <div x-show="!collapsed" x-cloak x-transition.duration.150ms>
                    <div class="divide-y divide-border/40">
                        @foreach($allShooters as $sh)
                            <div wire:key="attn-{{ $sh->id }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-primary truncate">{{ $sh->name }}</span>
                                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium {{ $sh->statusBadgeClasses() }}">{{ $sh->statusLabel() }}</span>
                                        @if($sh->scored_shots > 0 && ($sh->isNoShow() || $sh->isWithdrawn()))
                                            <span class="inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[10px] font-medium text-amber-300" title="Scores recorded for a shooter marked as absent">
                                                <x-icon name="triangle-alert" class="h-3 w-3" />
                                                {{ $sh->scored_shots }} scores on record
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mt-0.5 text-xs text-muted">{{ $sh->squad_name }} · {{ $sh->scored_shots }} shots scored</div>
                                </div>
                                <div class="flex items-center gap-1">
                                    @if($sh->isDq())
                                        <span class="text-xs italic text-muted">DQ — revoke via Setup → DQ list</span>
                                    @else
                                        @if(! $sh->isActive())
                                            <button wire:click="setShooterStatus({{ $sh->id }}, 'active')"
                                                    class="rounded-md border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-[11px] font-medium text-emerald-300 transition hover:border-emerald-400 hover:bg-emerald-500/20">
                                                Mark Present
                                            </button>
                                        @endif
                                        @if(! $sh->isNoShow())
                                            <button wire:click="setShooterStatus({{ $sh->id }}, 'no_show')"
                                                    wire:confirm="Mark {{ $sh->name }} as a no-show? They'll be excluded from ranking and field stats. Existing scores are preserved."
                                                    class="rounded-md border border-zinc-500/30 bg-zinc-500/10 px-2.5 py-1 text-[11px] font-medium text-zinc-300 transition hover:border-zinc-400 hover:bg-zinc-500/20">
                                                No-Show
                                            </button>
                                        @endif
                                        @if(! $sh->isWithdrawn())
                                            <button wire:click="setShooterStatus({{ $sh->id }}, 'withdrawn')"
                                                    wire:confirm="Mark {{ $sh->name }} as withdrawn?"
                                                    class="rounded-md border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-[11px] font-medium text-amber-300 transition hover:border-amber-400 hover:bg-amber-500/20">
                                                Withdraw
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

    </x-match-control-shell>
</div>
