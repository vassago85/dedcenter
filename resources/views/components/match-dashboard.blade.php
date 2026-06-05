@props([
    'match',
    'dashboard',
    'setupUrl',
    'squaddingUrl',
    'scoringUrl',
    'scoreboardUrl',
    'reportsUrl',
    'canExport' => false,
    'exportPrefix' => 'org.matches.export',
    'organization' => null,
    'rankingsUrl' => null,
])

@php
    $isElr = $match->isElr();
    $isCompleted = $match->status === \App\Enums\MatchStatus::Completed;
    $checklist = $dashboard['elr_checklist'] ?? null;
    $checklistReady = $checklist && collect($checklist)->every(fn ($i) => $i['done']);
    $stages = $isElr ? ($dashboard['elr_stages'] ?? collect()) : ($dashboard['standard_stages'] ?? collect());
    $progress = $dashboard['scoring_progress'] ?? [];
    $exportParams = $organization ? [$organization, $match] : [$match];
@endphp

<div class="space-y-4">
    {{-- Section 1: Match status bar --}}
    <section class="rounded-2xl border border-border bg-surface p-5 sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-xl font-bold text-primary truncate">{{ $match->name }}</h2>
                    <span class="inline-flex items-center rounded-full border border-accent/30 bg-accent/10 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-accent">
                        {{ $dashboard['type_label'] }}
                    </span>
                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold {{ $isCompleted ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-border bg-surface-2 text-secondary' }}">
                        {{ $dashboard['status_label'] }}
                    </span>
                </div>
                <p class="mt-1.5 text-sm text-muted">
                    {{ $match->date?->format('D j M Y') ?? 'Date TBC' }}
                    @if($match->location)
                        · {{ $match->location }}
                    @endif
                </p>
                <div class="mt-3 flex flex-wrap gap-4 text-sm">
                    <span class="text-secondary"><span class="font-semibold tabular-nums text-primary">{{ $dashboard['registrations_count'] }}</span> registered</span>
                    <span class="text-secondary"><span class="font-semibold tabular-nums text-primary">{{ $dashboard['shooters_count'] }}</span> shooters</span>
                    <span class="text-secondary"><span class="font-semibold tabular-nums text-primary">{{ $dashboard['stages_count'] }}</span> stages</span>
                    <span class="text-secondary">
                        Scores
                        <span class="font-semibold {{ $dashboard['scores_published'] ? 'text-emerald-300' : 'text-amber-300' }}">
                            {{ $dashboard['scores_published'] ? 'published' : 'hidden' }}
                        </span>
                    </span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ $setupUrl }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-2 px-3 py-2 text-xs font-semibold text-secondary transition hover:border-accent/50 hover:text-primary">
                    <x-icon name="settings" class="h-3.5 w-3.5" /> Edit Match
                </a>
                <a href="{{ $squaddingUrl }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-2 px-3 py-2 text-xs font-semibold text-secondary transition hover:border-accent/50 hover:text-primary">
                    <x-icon name="users" class="h-3.5 w-3.5" /> Manage Squadding
                </a>
                <a href="{{ $scoringUrl }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-lg bg-accent px-3 py-2 text-xs font-bold text-white transition hover:bg-accent-hover">
                    <x-icon name="target" class="h-3.5 w-3.5" /> Open Scoring App
                </a>
                @if($isCompleted && ! $dashboard['scores_published'])
                    <button type="button" wire:click="toggleScoresPublished"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-300 transition hover:bg-emerald-500/20">
                        <x-icon name="eye" class="h-3.5 w-3.5" /> Publish Scores
                    </button>
                @endif
                @if($match->status !== \App\Enums\MatchStatus::Completed)
                    <button type="button" wire:click="transitionStatus('completed')"
                            wire:confirm="Mark this match as completed? This locks scoring and triggers post-match workflows."
                            class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-2 px-3 py-2 text-xs font-semibold text-secondary transition hover:border-accent/50 hover:text-primary">
                        <x-icon name="check-circle" class="h-3.5 w-3.5" /> Complete Match
                    </button>
                @endif
            </div>
        </div>
    </section>

    {{-- Section 2: ELR setup checklist --}}
    @if($isElr && $checklist)
        <section class="rounded-2xl border border-border bg-surface overflow-hidden">
            <div class="border-b border-border bg-surface-2/30 px-5 py-3">
                <h3 class="text-base font-semibold text-primary">ELR setup checklist</h3>
                <p class="text-xs text-muted">Configure everything before opening scoring.</p>
            </div>
            @if($checklistReady)
                <div class="flex items-center gap-2 border-b border-emerald-500/20 bg-emerald-500/10 px-5 py-3">
                    <x-icon name="circle-check" class="h-5 w-5 text-emerald-400" />
                    <span class="text-sm font-semibold text-emerald-300">Ready to score</span>
                </div>
            @endif
            <ul class="divide-y divide-border/40 px-5 py-2">
                @foreach($checklist as $item)
                    <li wire:key="chk-{{ $item['key'] }}" class="flex items-center gap-3 py-2.5">
                        @if($item['done'])
                            <x-icon name="check" class="h-4 w-4 shrink-0 text-emerald-400" />
                            <span class="text-sm text-secondary">{{ $item['label'] }}</span>
                        @else
                            <x-icon name="circle" class="h-4 w-4 shrink-0 text-muted" />
                            <a href="{{ $setupUrl }}{{ $item['anchor'] }}" wire:navigate class="text-sm font-medium text-accent hover:underline">
                                {{ $item['label'] }}
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Section 3: Stages --}}
    @if($stages->isNotEmpty() || $isElr)
        <section class="rounded-2xl border border-border bg-surface overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-border bg-surface-2/30 px-5 py-3">
                <div>
                    <h3 class="text-base font-semibold text-primary">Stages</h3>
                    <p class="text-xs text-muted">{{ $stages->count() }} configured</p>
                </div>
                <a href="{{ $setupUrl }}#{{ $isElr ? 'elr-stages' : 'stages' }}" wire:navigate
                   class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-semibold text-secondary transition hover:border-accent/50 hover:text-primary">
                    <x-icon name="plus" class="h-3.5 w-3.5" /> Add stage
                </a>
            </div>
            @if($stages->isEmpty())
                <x-empty-state icon="target" title="No stages yet" description="Add stages in match setup before scoring." class="py-8" />
            @else
                <div class="divide-y divide-border/40">
                    @foreach($stages as $stage)
                        <div wire:key="stage-{{ $stage['id'] }}" class="px-5 py-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="font-semibold text-primary">{{ $stage['label'] }}</p>
                                    <p class="text-xs text-muted">
                                        {{ ucfirst(str_replace('_', ' ', $stage['type'])) }}
                                        · {{ $stage['gong_count'] }} gongs
                                        @if($stage['profile'])
                                            · {{ $stage['profile'] }}
                                        @endif
                                    </p>
                                </div>
                                @if($isElr && isset($stage['teams_total']) && $stage['teams_total'] > 0)
                                    <span class="rounded-full border border-border bg-surface-2 px-2.5 py-0.5 text-[11px] font-medium text-secondary tabular-nums">
                                        {{ $stage['teams_completed'] }}/{{ $stage['teams_total'] }} teams
                                    </span>
                                @endif
                            </div>
                            @if($isElr && ($stage['division_ranges'] ?? collect())->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach($stage['division_ranges'] as $range)
                                        <span class="rounded-md border border-border bg-surface-2/60 px-2 py-0.5 text-[10px] text-muted tabular-nums">
                                            Div {{ $range['division_id'] }}: gongs {{ $range['gong_start'] }}–{{ $range['gong_end'] }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    {{-- Section 4: Shooters & teams --}}
    <section class="rounded-2xl border border-border bg-surface p-5">
        <h3 class="text-base font-semibold text-primary">Shooters &amp; teams</h3>
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-muted">Shooters by division</p>
                <ul class="mt-2 space-y-1">
                    @forelse($dashboard['shooters_by_division'] as $div => $count)
                        <li class="flex justify-between text-sm"><span class="text-secondary">{{ $div }}</span><span class="font-semibold tabular-nums text-primary">{{ $count }}</span></li>
                    @empty
                        <li class="text-sm text-muted">No shooters squadded yet.</li>
                    @endforelse
                </ul>
            </div>
            @if($isElr && ! empty($dashboard['team_composition']))
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted">Teams ({{ $dashboard['teams_count'] }})</p>
                    <ul class="mt-2 space-y-1">
                        @foreach($dashboard['team_composition'] as $label => $count)
                            <li class="flex justify-between text-sm"><span class="text-secondary">{{ $label }}</span><span class="font-semibold tabular-nums text-primary">{{ $count }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @elseif(($dashboard['teams_count'] ?? 0) > 0)
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted">Teams</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-primary">{{ $dashboard['teams_count'] }}</p>
                </div>
            @endif
        </div>
        @if(($dashboard['unassigned_shooters'] ?? 0) > 0)
            <div class="mt-4 flex items-center justify-between gap-3 rounded-lg border border-amber-500/30 bg-amber-500/8 px-4 py-3">
                <p class="text-sm text-amber-200">{{ $dashboard['unassigned_shooters'] }} shooter(s) not assigned to a squad or team</p>
                <a href="{{ $squaddingUrl }}" wire:navigate class="shrink-0 text-xs font-semibold text-amber-300 hover:underline">Fix</a>
            </div>
        @endif
        @if(! empty($dashboard['division_mismatches']))
            <div class="mt-4 rounded-lg border border-amber-500/30 bg-amber-500/8 p-4">
                <p class="text-sm font-semibold text-amber-200">Registration division mismatch</p>
                <ul class="mt-2 space-y-1 text-xs text-amber-200/80">
                    @foreach($dashboard['division_mismatches'] as $m)
                        <li>{{ $m['shooter_name'] }}: registered {{ $m['registration_division'] }}, squadded as {{ $m['shooter_division'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    {{-- Section 5: Scoring progress --}}
    <section class="rounded-2xl border border-border bg-surface p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-primary">Scoring progress</h3>
                @if(isset($progress['shots_recorded']))
                    <p class="mt-0.5 text-xs text-muted">
                        {{ number_format($progress['shots_recorded']) }} shots recorded
                        @if($progress['hits'] !== null)
                            · {{ $progress['hits'] }} hits / {{ $progress['misses'] }} misses
                            @if($progress['completion_pct'] !== null)
                                · {{ $progress['completion_pct'] }}% hit rate
                            @endif
                        @endif
                    </p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ $scoringUrl }}" wire:navigate class="inline-flex items-center gap-1 rounded-lg border border-border bg-surface-2 px-2.5 py-1.5 text-xs font-semibold text-secondary hover:text-primary">Scoring App</a>
                <a href="{{ $scoreboardUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg border border-border bg-surface-2 px-2.5 py-1.5 text-xs font-semibold text-secondary hover:text-primary">Scoreboard</a>
                @if($isElr && $rankingsUrl)
                    <a href="{{ $rankingsUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg border border-border bg-surface-2 px-2.5 py-1.5 text-xs font-semibold text-secondary hover:text-primary">Rankings</a>
                @endif
            </div>
        </div>
        @if(($progress['stage_progress'] ?? collect())->isNotEmpty())
            <div class="mt-4 space-y-2">
                @foreach($progress['stage_progress'] as $sp)
                    <div wire:key="prog-{{ $sp['stage_id'] }}" class="flex items-center justify-between gap-3 text-sm">
                        <span class="truncate text-secondary">{{ $sp['label'] }}</span>
                        <span class="shrink-0 tabular-nums text-primary">
                            {{ $sp['teams_completed'] }}/{{ $sp['teams_total'] }} teams
                            @if($sp['timed_out'] > 0)
                                <span class="text-amber-300">({{ $sp['timed_out'] }} timed out)</span>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Section 6: Exports (MD / platform admin only) --}}
    @if($canExport)
        <section class="rounded-2xl border border-border bg-surface p-5">
            <h3 class="text-base font-semibold text-primary">Exports &amp; reports</h3>
            <p class="mt-0.5 text-xs text-muted">Download match data. Full report options also on the <a href="{{ $reportsUrl }}" wire:navigate class="text-accent hover:underline">Reports tab</a>.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route($exportPrefix . '.standings', $exportParams) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-2 px-3 py-2 text-xs font-semibold text-secondary hover:text-primary">
                    <x-icon name="download" class="h-3.5 w-3.5" /> CSV Standings
                </a>
                <a href="{{ route($exportPrefix . '.detailed', $exportParams) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-2 px-3 py-2 text-xs font-semibold text-secondary hover:text-primary">
                    <x-icon name="download" class="h-3.5 w-3.5" /> CSV Detailed
                </a>
                @if($isElr)
                    <a href="{{ route('scoreboard.export.elr-shots', $match) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-2 px-3 py-2 text-xs font-semibold text-secondary hover:text-primary">
                        <x-icon name="download" class="h-3.5 w-3.5" /> Shots Template
                    </a>
                    <a href="{{ route($exportPrefix . '.elr-rankings', $exportParams) }}?view=overall" class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-2 px-3 py-2 text-xs font-semibold text-secondary hover:text-primary">
                        <x-icon name="download" class="h-3.5 w-3.5" /> Rankings Overall
                    </a>
                    <a href="{{ route($exportPrefix . '.elr-rankings', $exportParams) }}?view=teams" class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-2 px-3 py-2 text-xs font-semibold text-secondary hover:text-primary">
                        <x-icon name="download" class="h-3.5 w-3.5" /> Rankings Teams
                    </a>
                    <a href="{{ route($exportPrefix . '.elr-rankings', $exportParams) }}?view=divisions" class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-2 px-3 py-2 text-xs font-semibold text-secondary hover:text-primary">
                        <x-icon name="download" class="h-3.5 w-3.5" /> Rankings Divisions
                    </a>
                    <a href="{{ route($exportPrefix . '.pdf-elr-rankings', $exportParams) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-2 px-3 py-2 text-xs font-semibold text-secondary hover:text-primary">
                        <x-icon name="download" class="h-3.5 w-3.5" /> PDF Rankings
                    </a>
                @else
                    <a href="{{ route($exportPrefix . '.pdf-standings', $exportParams) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-2 px-3 py-2 text-xs font-semibold text-secondary hover:text-primary">
                        <x-icon name="download" class="h-3.5 w-3.5" /> PDF Standings
                    </a>
                @endif
            </div>
        </section>
    @endif
</div>
