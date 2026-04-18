<?php

use App\Enums\MatchStatus;
use App\Models\ShootingMatch;
use App\Services\MatchStandingsService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    class extends Component {
    public ShootingMatch $match;

    public function mount(ShootingMatch $match): void
    {
        $this->match = $match;
    }

    public function getTitle(): string
    {
        return 'Admin — '.$this->match->name;
    }

    public function with(): array
    {
        $status = $this->match->status;

        $topStandings = collect();
        if (! $this->match->isPrs() && ! $this->match->isElr()) {
            $topStandings = (new MatchStandingsService())->standardStandings($this->match)
                ->filter(fn ($r) => $r->status !== 'dq')
                ->take(5);
        }

        return [
            'status' => $status,
            'topStandings' => $topStandings,
            'registrationsCount' => $this->match->registrations()->count(),
            'shootersCount' => $this->match->shooters()->count(),
            'scoresCount' => \App\Models\Score::whereIn('shooter_id', $this->match->shooters()->pluck('shooters.id'))->count(),
            'isCompleted' => $status === MatchStatus::Completed,
            'isRoyalFlush' => (bool) $this->match->royal_flush_enabled,
        ];
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-muted">
                <a href="{{ route('admin.matches.index') }}" class="hover:text-accent">Matches</a>
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
            <p class="mt-1 text-sm text-muted">{{ $match->date?->format('l, d M Y') ?? 'No date' }} &bull; Admin view</p>
        </div>
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('admin.matches.edit', $match) }}" variant="ghost" size="sm">
                <x-icon name="pencil-square" class="mr-1.5 h-4 w-4" />
                Edit Match
            </flux:button>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 border-b border-border pb-0">
        <a href="{{ route('admin.matches.hub', $match) }}"
           class="rounded-t-lg border-b-2 border-accent bg-surface px-4 py-2 text-sm font-semibold text-primary">Overview</a>
        <a href="{{ route('admin.matches.edit', $match) }}"
           class="rounded-t-lg border-b-2 border-transparent px-4 py-2 text-sm font-medium text-muted hover:text-primary">Configuration</a>
        <a href="{{ route('admin.matches.squadding', $match) }}"
           class="rounded-t-lg border-b-2 border-transparent px-4 py-2 text-sm font-medium text-muted hover:text-primary">Squadding</a>
        <a href="{{ route('scoreboard', $match) }}"
           class="rounded-t-lg border-b-2 border-transparent px-4 py-2 text-sm font-medium text-muted hover:text-primary">Scoreboard</a>
        @if($match->side_bet_enabled)
            <a href="{{ route('admin.matches.side-bet-report', $match) }}"
               class="rounded-t-lg border-b-2 border-transparent px-4 py-2 text-sm font-medium text-muted hover:text-primary">Side Bet</a>
        @endif
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-border bg-surface p-4">
            <div class="text-xs uppercase tracking-wide text-muted">Registrations</div>
            <div class="mt-1 text-2xl font-bold text-primary tabular-nums">{{ $registrationsCount }}</div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4">
            <div class="text-xs uppercase tracking-wide text-muted">Shooters</div>
            <div class="mt-1 text-2xl font-bold text-primary tabular-nums">{{ $shootersCount }}</div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4">
            <div class="text-xs uppercase tracking-wide text-muted">Shots recorded</div>
            <div class="mt-1 text-2xl font-bold text-primary tabular-nums">{{ $scoresCount }}</div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4">
            <div class="text-xs uppercase tracking-wide text-muted">Scores published</div>
            <div class="mt-1 text-2xl font-bold text-primary">{{ $match->scores_published ? 'Yes' : 'No' }}</div>
        </div>
    </div>

    <div>
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted">Actions</h2>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ route('admin.matches.squadding', $match) }}" class="group rounded-xl border border-border bg-surface p-5 transition hover:border-accent">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-500/15 text-blue-400"><x-icon name="users" class="h-5 w-5" /></div>
                    <div><div class="font-semibold text-primary">Squadding</div><div class="text-xs text-muted">Manage squads</div></div>
                </div>
            </a>
            <a href="{{ route('scoreboard', $match) }}" class="group rounded-xl border border-border bg-surface p-5 transition hover:border-accent">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-500/15 text-green-400"><x-icon name="chart-bar" class="h-5 w-5" /></div>
                    <div><div class="font-semibold text-primary">Scoreboard</div><div class="text-xs text-muted">Public standings</div></div>
                </div>
            </a>
            <a href="{{ route('admin.matches.export.standings', $match) }}" class="group rounded-xl border border-border bg-surface p-5 transition hover:border-accent">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-500/15 text-slate-400"><x-icon name="arrow-down-tray" class="h-5 w-5" /></div>
                    <div><div class="font-semibold text-primary">Export Standings</div><div class="text-xs text-muted">CSV</div></div>
                </div>
            </a>
            <a href="{{ route('admin.matches.export.pdf-standings', $match) }}" class="group rounded-xl border border-border bg-surface p-5 transition hover:border-accent">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-500/15 text-red-400"><x-icon name="document-text" class="h-5 w-5" /></div>
                    <div><div class="font-semibold text-primary">Standings PDF</div><div class="text-xs text-muted">Printable</div></div>
                </div>
            </a>
            @if($isCompleted)
                <a href="{{ route('admin.matches.export.pdf-executive-summary', $match) }}" class="group rounded-xl border border-emerald-600/40 bg-emerald-900/10 p-5 transition hover:border-emerald-500">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-400"><x-icon name="document-check" class="h-5 w-5" /></div>
                        <div><div class="font-semibold text-primary">Executive Summary (PDF)</div><div class="text-xs text-muted">All shooters · heatmap</div></div>
                    </div>
                </a>
            @endif
            @if($match->side_bet_enabled)
                <a href="{{ route('admin.matches.side-bet-report', $match) }}" class="group rounded-xl border border-amber-600/40 bg-amber-900/10 p-5 transition hover:border-amber-500">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500/20 text-amber-400"><x-icon name="trophy" class="h-5 w-5" /></div>
                        <div><div class="font-semibold text-primary">Side Bet Report</div><div class="text-xs text-muted">Winner + cascade</div></div>
                    </div>
                </a>
            @endif
        </div>
    </div>

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
                                font-bold text-sm">{{ $row->rank }}</div>
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
                                <a href="{{ route('admin.matches.export.pdf-shooter-report', [$match, $row->shooter_id]) }}"
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
