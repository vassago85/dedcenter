<?php

use App\Concerns\HandlesMatchLifecycleTransitions;
use App\Enums\MatchStatus;
use App\Models\Organization;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Services\MatchDashboardService;
use App\Services\MatchStandingsService;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    class extends Component {
        use HandlesMatchLifecycleTransitions;

        public Organization $organization;
        public ShootingMatch $match;

        public function mount(Organization $organization, ShootingMatch $match): void
        {
            if (! $match->userCanEditInOrg(auth()->user())) {
                abort(403, 'You are not authorized to manage this match.');
            }

            $this->organization = $organization;
            $this->match = $match;
        }

        public function getTitle(): string
        {
            return $this->match->name;
        }

        /**
         * Post-match attendance correction. Matches the admin hub behavior so org
         * owners can flip accidentally-scored shooters to no_show / withdrawn and
         * get the statistics to recompute without touching recorded scores.
         */
        public function setShooterStatus(int $shooterId, string $status): void
        {
            if (! $this->match->userCanEditInOrg(auth()->user())) {
                Flux::toast('Not authorized.', variant: 'danger');
                return;
            }

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

        public function toggleScoresPublished(): void
        {
            if (! $this->match->userCanEditInOrg(auth()->user())) {
                Flux::toast('Not authorized.', variant: 'danger');
                return;
            }

            $newValue = ! $this->match->scores_published;
            $this->match->update(['scores_published' => $newValue]);

            if ($newValue) {
                try {
                    \App\Services\AchievementService::evaluateMatchCompletion($this->match);
                    if ($this->match->royal_flush_enabled) {
                        \App\Services\AchievementService::evaluateRoyalFlushCompletion($this->match);
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Achievement evaluation failed on publish', ['match' => $this->match->id, 'error' => $e->getMessage()]);
                }
            }

            Flux::toast($newValue ? 'Scores are now live.' : 'Scores hidden from public.', variant: 'success');
        }

        public function with(): array
        {
            $status = $this->match->status;

            $topStandings = collect();
            if (in_array($status, [MatchStatus::Active, MatchStatus::Completed], true)) {
                if (! $this->match->isPrs() && ! $this->match->isElr()) {
                    $topStandings = (new MatchStandingsService())->standardStandings($this->match)
                        ->filter(fn ($r) => $r->rank !== null)
                        ->take(5);
                }
            }

            // Attendance roster — every shooter in the match with status + score count.
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

            $sideBetBoughtIn = 0;
            if ($this->match->side_bet_enabled) {
                $sideBetBoughtIn = $this->match->sideBetShooters()->count();
            }

            $user = auth()->user();
            $canExport = $user && (
                $user->isAdmin()
                || $user->isOrgMatchDirector($this->organization)
            );

            $dashboard = (new MatchDashboardService())->build($this->match);

            return [
                'status' => $status,
                'dashboard' => $dashboard,
                'canExport' => $canExport,
                'topStandings' => $topStandings,
                'allShooters' => $allShooters,
                'attendanceCounts' => $attendanceCounts,
                'sideBetBoughtIn' => $sideBetBoughtIn,
                'isRoyalFlush' => (bool) $this->match->royal_flush_enabled,
                'isCompleted' => $status === MatchStatus::Completed,
                'isPreActive' => $status->ordinal() < MatchStatus::Active->ordinal(),
                'matchesIndexUrl' => route('org.matches.index', $this->organization),
                'setupUrl' => route('org.matches.edit', [$this->organization, $this->match]),
                'squaddingUrl' => route('org.matches.squadding', [$this->organization, $this->match]),
                'scoringUrl' => route('org.matches.scoring', [$this->organization, $this->match]),
                'scoreboardUrl' => route('scoreboard', $this->match),
                'rankingsUrl' => $this->match->isElr() ? url('/score/'.$this->match->id.'/rankings') : null,
                'reportsUrl' => route('org.matches.reports', [$this->organization, $this->match]),
                'sideBetBuyInUrl' => $this->match->side_bet_enabled ? route('org.matches.side-bet', [$this->organization, $this->match]) : null,
                'sideBetReportUrl' => $this->match->side_bet_enabled ? route('org.matches.side-bet-report', [$this->organization, $this->match]) : null,
            ];
        }
    }; ?>

<div>
    <x-match-control-shell :match="$match" :organization="$organization">

        <x-match-dashboard
            :match="$match"
            :dashboard="$dashboard"
            :setup-url="$setupUrl"
            :squadding-url="$squaddingUrl"
            :scoring-url="$scoringUrl"
            :scoreboard-url="$scoreboardUrl"
            :reports-url="$reportsUrl"
            :can-export="$canExport"
            export-prefix="org.matches.export"
            :organization="$organization"
            :rankings-url="$rankingsUrl"
            :side-bet-report-url="$sideBetReportUrl"
        />

        {{-- ─── Royal Flush highlights (auto-hides for non-RF/incomplete) ─ --}}
        <div class="mt-4">
            <x-royal-flush-highlights :match="$match" :organization="$organization" />
        </div>

        {{-- ─── Top 5 standings — only Active/Completed standard matches ─ --}}
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
                                    <a href="{{ route('org.matches.export.pdf-shooter-report', [$organization, $match, $row->shooter_id]) }}"
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

        {{-- ─── Attendance correction — collapsed by default until match is ─ --}}
        {{-- ─── Active or Completed (the only states where it matters).      --}}
        @if($allShooters->isNotEmpty() && ! $isPreActive)
            <section class="mt-4 overflow-hidden rounded-2xl border border-border bg-surface" x-data="{ collapsed: {{ $isCompleted ? 'false' : 'true' }} }">
                <button type="button" @click="collapsed = !collapsed"
                        class="flex w-full items-center justify-between gap-3 border-b border-border bg-surface-2/30 px-5 py-3 text-left">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-primary">Attendance &amp; status</h2>
                        <p class="text-xs text-muted">
                            Flip any shooter to <span class="text-zinc-300">No-Show</span> / <span class="text-amber-300">Withdrawn</span> to correct post-match statistics.
                            No-shows stay listed but are excluded from ranking, hit rate, and field averages.
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
