<?php

use App\Enums\MatchStatus;
use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Services\MatchStandingsService;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    class extends Component {
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

    public function with(): array
    {
        $status = $this->match->status;

        $registrationsCount = $this->match->registrations()->count();
        $shootersCount = $this->match->shooters()->count();
        $scoresCount = \App\Models\Score::whereIn('shooter_id', $this->match->shooters()->pluck('shooters.id'))->count();
        $squadsCount = $this->match->squads()->count();

        $topStandings = collect();
        $podiumUserIds = [];
        if (in_array($status, [MatchStatus::Active, MatchStatus::Completed], true)) {
            if (! $this->match->isPrs() && ! $this->match->isElr()) {
                $topStandings = (new MatchStandingsService())->standardStandings($this->match)
                    ->filter(fn ($r) => $r->status !== 'dq')
                    ->take(5);

                $podiumShooterIds = (new MatchStandingsService())->podiumShooterIds($this->match, 3);
                if (! empty($podiumShooterIds)) {
                    $podiumUserIds = \App\Models\Shooter::whereIn('id', array_values($podiumShooterIds))
                        ->pluck('user_id', 'id')
                        ->toArray();
                }
            }
        }

        $sideBetBoughtIn = 0;
        if ($this->match->side_bet_enabled) {
            $sideBetBoughtIn = $this->match->sideBetShooters()->count();
        }

        return [
            'status' => $status,
            'registrationsCount' => $registrationsCount,
            'shootersCount' => $shootersCount,
            'squadsCount' => $squadsCount,
            'scoresCount' => $scoresCount,
            'topStandings' => $topStandings,
            'sideBetBoughtIn' => $sideBetBoughtIn,
            'isRoyalFlush' => (bool) $this->match->royal_flush_enabled,
            'isCompleted' => $status === MatchStatus::Completed,
        ];
    }
}; ?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-muted">
                <a href="{{ route('org.matches.index', $organization) }}" class="hover:text-accent">Matches</a>
                <span>›</span>
                <span>{{ $match->name }}</span>
            </div>
            <div class="mt-1 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-primary truncate">{{ $match->name }}</h1>
                <flux:badge size="md" color="{{ $status->color() }}">{{ $status->label() }}</flux:badge>
                @if($isRoyalFlush)
                    <flux:badge size="md" color="amber">Royal Flush</flux:badge>
                @endif
            </div>
            <p class="mt-1 text-sm text-muted">
                {{ $match->date?->format('l, d M Y') ?? 'No date' }}
                @if($match->location)
                    &bull; {{ $match->location }}
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('org.matches.edit', [$organization, $match]) }}" variant="ghost" size="sm">
                <x-icon name="pencil-square" class="mr-1.5 h-4 w-4" />
                Edit Match
            </flux:button>
        </div>
    </div>

    {{-- Status tabs (navigation) --}}
    <div class="flex flex-wrap items-center gap-2 border-b border-border pb-0">
        <a href="{{ route('org.matches.hub', [$organization, $match]) }}"
           class="rounded-t-lg border-b-2 border-accent bg-surface px-4 py-2 text-sm font-semibold text-primary">
            Overview
        </a>
        <a href="{{ route('org.matches.edit', [$organization, $match]) }}"
           class="rounded-t-lg border-b-2 border-transparent px-4 py-2 text-sm font-medium text-muted hover:text-primary">
            Configuration
        </a>
        <a href="{{ route('org.matches.squadding', [$organization, $match]) }}"
           class="rounded-t-lg border-b-2 border-transparent px-4 py-2 text-sm font-medium text-muted hover:text-primary">
            Squadding
        </a>
        <a href="{{ route('scoreboard', $match) }}"
           class="rounded-t-lg border-b-2 border-transparent px-4 py-2 text-sm font-medium text-muted hover:text-primary">
            Scoreboard
        </a>
        @if($match->side_bet_enabled)
            <a href="{{ route('org.matches.side-bet', [$organization, $match]) }}"
               class="rounded-t-lg border-b-2 border-transparent px-4 py-2 text-sm font-medium text-muted hover:text-primary">
                Side Bet
            </a>
        @endif
    </div>

    {{-- Quick stats --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-border bg-surface p-4">
            <div class="text-xs uppercase tracking-wide text-muted">Registrations</div>
            <div class="mt-1 text-2xl font-bold text-primary tabular-nums">{{ $registrationsCount }}</div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4">
            <div class="text-xs uppercase tracking-wide text-muted">Shooters</div>
            <div class="mt-1 text-2xl font-bold text-primary tabular-nums">{{ $shootersCount }}</div>
            <div class="mt-0.5 text-xs text-muted">{{ $squadsCount }} squad{{ $squadsCount === 1 ? '' : 's' }}</div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4">
            <div class="text-xs uppercase tracking-wide text-muted">Shots recorded</div>
            <div class="mt-1 text-2xl font-bold text-primary tabular-nums">{{ $scoresCount }}</div>
        </div>
        @if($match->side_bet_enabled)
            <div class="rounded-xl border border-amber-600/40 bg-amber-900/10 p-4">
                <div class="text-xs uppercase tracking-wide text-amber-400">Side Bet Buy-In</div>
                <div class="mt-1 text-2xl font-bold text-amber-300 tabular-nums">{{ $sideBetBoughtIn }}</div>
                <div class="mt-0.5 text-xs text-muted">of {{ $shootersCount }} shooters</div>
            </div>
        @else
            <div class="rounded-xl border border-border bg-surface p-4">
                <div class="text-xs uppercase tracking-wide text-muted">Scores published</div>
                <div class="mt-1 text-2xl font-bold text-primary">{{ $match->scores_published ? 'Yes' : 'No' }}</div>
            </div>
        @endif
    </div>

    {{-- Action tiles --}}
    <div>
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted">Actions</h2>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {{-- Squadding --}}
            <a href="{{ route('org.matches.squadding', [$organization, $match]) }}"
               class="group rounded-xl border border-border bg-surface p-5 transition hover:border-accent hover:bg-surface-2/40">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-500/15 text-blue-400">
                        <x-icon name="users" class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="font-semibold text-primary">Squadding</div>
                        <div class="text-xs text-muted">Assign shooters to squads</div>
                    </div>
                </div>
            </a>

            {{-- Scoreboard --}}
            <a href="{{ route('scoreboard', $match) }}"
               class="group rounded-xl border border-border bg-surface p-5 transition hover:border-accent hover:bg-surface-2/40">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-500/15 text-green-400">
                        <x-icon name="chart-bar" class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="font-semibold text-primary">Scoreboard</div>
                        <div class="text-xs text-muted">Public live standings</div>
                    </div>
                </div>
            </a>

            {{-- Scoring app --}}
            <a href="{{ url('/score/'.$match->id) }}" target="_blank" rel="noopener"
               class="group rounded-xl border border-border bg-surface p-5 transition hover:border-accent hover:bg-surface-2/40">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-500/15 text-purple-400">
                        <x-icon name="device-tablet" class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="font-semibold text-primary">Scoring App</div>
                        <div class="text-xs text-muted">Open PWA on tablet</div>
                    </div>
                </div>
            </a>

            {{-- Side Bet --}}
            @if($match->side_bet_enabled)
                <a href="{{ route('org.matches.side-bet', [$organization, $match]) }}"
                   class="group rounded-xl border border-amber-600/40 bg-amber-900/10 p-5 transition hover:border-amber-500 hover:bg-amber-900/20">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500/20 text-amber-400">
                            <x-icon name="currency-dollar" class="h-5 w-5" />
                        </div>
                        <div>
                            <div class="font-semibold text-primary">Side Bet Buy-In</div>
                            <div class="text-xs text-muted">{{ $sideBetBoughtIn }} in the pot</div>
                        </div>
                    </div>
                </a>

                <a href="{{ route('org.matches.side-bet-report', [$organization, $match]) }}"
                   class="group rounded-xl border border-border bg-surface p-5 transition hover:border-accent hover:bg-surface-2/40">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500/15 text-amber-400">
                            <x-icon name="trophy" class="h-5 w-5" />
                        </div>
                        <div>
                            <div class="font-semibold text-primary">Side Bet Report</div>
                            <div class="text-xs text-muted">Winner + cascade</div>
                        </div>
                    </div>
                </a>
            @endif

            {{-- Exports (always) --}}
            <a href="{{ route('org.matches.export.standings', [$organization, $match]) }}"
               class="group rounded-xl border border-border bg-surface p-5 transition hover:border-accent hover:bg-surface-2/40">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-500/15 text-slate-400">
                        <x-icon name="arrow-down-tray" class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="font-semibold text-primary">Export Standings (CSV)</div>
                        <div class="text-xs text-muted">Weighted leaderboard</div>
                    </div>
                </div>
            </a>

            <a href="{{ route('org.matches.export.detailed', [$organization, $match]) }}"
               class="group rounded-xl border border-border bg-surface p-5 transition hover:border-accent hover:bg-surface-2/40">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-500/15 text-slate-400">
                        <x-icon name="arrow-down-tray" class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="font-semibold text-primary">Detailed Export (CSV)</div>
                        <div class="text-xs text-muted">Per-stage hits</div>
                    </div>
                </div>
            </a>

            <a href="{{ route('org.matches.export.pdf-standings', [$organization, $match]) }}"
               class="group rounded-xl border border-border bg-surface p-5 transition hover:border-accent hover:bg-surface-2/40">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-500/15 text-red-400">
                        <x-icon name="document-text" class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="font-semibold text-primary">Standings PDF</div>
                        <div class="text-xs text-muted">Printable leaderboard</div>
                    </div>
                </div>
            </a>

            {{-- Executive Summary PDF (single page, all shooters heatmap) --}}
            @if($isCompleted)
                <a href="{{ route('org.matches.export.pdf-executive-summary', [$organization, $match]) }}"
                   class="group rounded-xl border border-emerald-600/40 bg-emerald-900/10 p-5 transition hover:border-emerald-500 hover:bg-emerald-900/20">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-400">
                            <x-icon name="document-check" class="h-5 w-5" />
                        </div>
                        <div>
                            <div class="font-semibold text-primary">Executive Summary (PDF)</div>
                            <div class="text-xs text-muted">All shooters · podium · heatmap · landscape</div>
                        </div>
                    </div>
                </a>
            @endif

            {{-- Match Report (per shooter) --}}
            <a href="{{ route('org.matches.report.preview', [$organization, $match]) }}"
               class="group rounded-xl border border-border bg-surface p-5 transition hover:border-accent hover:bg-surface-2/40">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-500/15 text-indigo-400">
                        <x-icon name="envelope" class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="font-semibold text-primary">Shooter Reports</div>
                        <div class="text-xs text-muted">Preview + email</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    {{-- Top standings preview --}}
    @if($topStandings->isNotEmpty())
        <div class="rounded-xl border border-border bg-surface overflow-hidden">
            <div class="flex items-center justify-between border-b border-border px-5 py-3 bg-surface-2/40">
                <div>
                    <h2 class="text-base font-semibold text-primary">Top 5 — Weighted Standings</h2>
                    <p class="text-xs text-muted">Same formula as the detailed scoreboard and PDF exports.</p>
                </div>
                <flux:button href="{{ route('scoreboard', $match) }}" size="sm" variant="ghost">Full Scoreboard</flux:button>
            </div>
            <div class="divide-y divide-border/40">
                @foreach($topStandings as $row)
                    <div wire:key="top-{{ $row->shooter_id }}" class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full
                                {{ $row->rank === 1 ? 'bg-amber-500 text-white' : ($row->rank === 2 ? 'bg-slate-400 text-white' : ($row->rank === 3 ? 'bg-orange-700 text-white' : 'bg-surface-2 text-muted')) }}
                                font-bold text-sm">
                                {{ $row->rank }}
                            </div>
                            <div class="min-w-0">
                                <div class="font-semibold text-primary truncate">{{ $row->name }}</div>
                                <div class="text-xs text-muted">{{ $row->squad }} &bull; {{ $row->hits }} hits / {{ $row->misses }} misses</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 text-right">
                            <div>
                                <div class="text-lg font-bold text-primary tabular-nums">{{ number_format($row->total_score, 1) }}</div>
                            </div>
                            @if($isCompleted)
                                <a href="{{ route('org.matches.export.pdf-shooter-report', [$organization, $match, $row->shooter_id]) }}"
                                   title="Download individual shooter PDF"
                                   class="inline-flex items-center gap-1 rounded-lg border border-border bg-surface-2/50 px-2 py-1 text-xs text-muted transition hover:border-accent hover:text-primary">
                                    <x-icon name="document-check" class="h-3.5 w-3.5" />
                                    PDF
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
