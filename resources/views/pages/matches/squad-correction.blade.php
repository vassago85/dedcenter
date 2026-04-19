<?php

/**
 * Post-squad correction editor.
 *
 * Reached from the admin/org squadding page via a "Correct scores"
 * button on each squad card. Shows a compact hit/miss grid for every
 * shooter on the squad across every target_set in the match, so a
 * scorekeeper can reconcile the paper book with what the mobile app
 * submitted once the squad is off the line.
 *
 * The editor is deliberately kept post-hoc (not a live scoring pad):
 *   - Live scoring stays on the phone/tablet app.
 *   - This page is for after-the-fact correction only.
 *   - A correction note is required on save and written to every audit
 *     row so investigators can trace who changed what and why.
 *
 * Route-mounted twice (admin + org) so it inherits the matching auth
 * middleware. See routes/web.php.
 */

use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Services\RoyalFlushShotStatusService;
use App\Services\SquadScoreCorrectionService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Correct Scores')]
    class extends Component {
    public ShootingMatch $match;
    public Squad $squad;

    /** Required note attached to every audit row written on save. */
    public string $note = '';

    /**
     * Dirty working state: shooter_id => gong_id => true|false|null.
     * Seeded from the database on mount; mutated by toggleCell; diffed
     * against the DB by SquadScoreCorrectionService on save.
     *
     * @var array<int, array<int, bool|null>>
     */
    public array $cells = [];

    public function mount(ShootingMatch $match, Squad $squad): void
    {
        if ($squad->match_id !== $match->id) {
            abort(404);
        }

        if (! $match->isStandard()) {
            Flux::toast('Score corrections are only available for standard matches. PRS and ELR have their own editors.', variant: 'warning');
        }

        $this->match = $match;
        $this->squad = $squad;
        $this->seedCells();
    }

    /** Read every score on the squad once and fold it into the $cells map. */
    private function seedCells(): void
    {
        $shooters = $this->squad->shooters()->pluck('id');
        $gongIds = $this->gongIds();

        $scores = \App\Models\Score::whereIn('shooter_id', $shooters)
            ->whereIn('gong_id', $gongIds)
            ->get(['shooter_id', 'gong_id', 'is_hit']);

        $map = [];
        foreach ($shooters as $shooterId) {
            foreach ($gongIds as $gongId) {
                $map[$shooterId][$gongId] = null;
            }
        }
        foreach ($scores as $score) {
            $map[$score->shooter_id][$score->gong_id] = (bool) $score->is_hit;
        }

        $this->cells = $map;
    }

    /** @return array<int, int> gong_ids for every target_set in this match */
    private function gongIds(): array
    {
        return \App\Models\Gong::join('target_sets', 'gongs.target_set_id', '=', 'target_sets.id')
            ->where('target_sets.match_id', $this->match->id)
            ->pluck('gongs.id')
            ->all();
    }

    /**
     * Cycle a cell through none → hit → miss → none. Kept as a pure
     * state mutation — the write to the DB only happens on save().
     */
    public function toggleCell(int $shooterId, int $gongId): void
    {
        $current = $this->cells[$shooterId][$gongId] ?? null;

        $next = match (true) {
            $current === null => true,   // pristine → hit
            $current === true => false,  // hit → miss
            default => null,             // miss → cleared
        };

        $this->cells[$shooterId][$gongId] = $next;
    }

    public function save(SquadScoreCorrectionService $corrections): void
    {
        $this->validate([
            'note' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'note.required' => 'A correction note is required so the change can be audited.',
            'note.min' => 'Correction note is too short — give at least a few words of context.',
        ]);

        try {
            $stats = $corrections->apply(
                $this->match,
                $this->squad,
                $this->cells,
                $this->note,
                auth()->id(),
            );
        } catch (\InvalidArgumentException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');

            return;
        }

        $summary = collect([
            $stats['created'] ? "{$stats['created']} added" : null,
            $stats['updated'] ? "{$stats['updated']} flipped" : null,
            $stats['deleted'] ? "{$stats['deleted']} cleared" : null,
        ])->filter()->implode(', ');

        if ($summary === '') {
            Flux::toast('No changes to save — everything already matched.', variant: 'info');

            return;
        }

        $this->note = '';
        $this->seedCells();

        Flux::toast("Corrections saved: {$summary}. Audit log updated.", variant: 'success');
    }

    public function with(): array
    {
        $targetSets = $this->match->targetSets()
            ->with(['gongs' => fn ($q) => $q->orderBy('number')])
            ->orderBy('sort_order')
            ->get();

        $shooters = $this->squad->shooters()
            ->with('user:id,email,name')
            ->orderBy('sort_order')
            ->get();

        // Compute Royal-Flush "armed" status from the dirty working state
        // (the cells the user has toggled, not the DB). This is what
        // makes the banner appear the moment an edit brings a shooter to
        // 4/4 at any distance — even before saving.
        $rfStatus = $this->computeArmedStatus($targetSets);

        return [
            'targetSets' => $targetSets,
            'shooters' => $shooters,
            'rfArmed' => $rfStatus,
        ];
    }

    /**
     * Mirrors RoyalFlushShotStatusService's armed rule, but operates on
     * the in-memory working $cells so the banner updates live.
     *
     *   shooter_id => target_set_id => ['armed' => bool, 'flushed' => bool,
     *                                   'hits' => int, 'misses' => int,
     *                                   'gong_count' => int]
     *
     * @return array<int, array<int, array<string, int|bool>>>
     */
    private function computeArmedStatus(Collection $targetSets): array
    {
        $rfEnabled = (bool) $this->match->royal_flush_enabled
            && $this->match->isStandard();

        $out = [];

        foreach ($this->cells as $shooterId => $gongStates) {
            $shooterId = (int) $shooterId;

            foreach ($targetSets as $ts) {
                $hits = 0;
                $misses = 0;
                $gongCount = $ts->gongs->count();
                foreach ($ts->gongs as $gong) {
                    $state = $gongStates[$gong->id] ?? null;
                    if ($state === true) $hits++;
                    elseif ($state === false) $misses++;
                }
                $unshot = max(0, $gongCount - $hits - $misses);

                $armed = $rfEnabled
                    && $gongCount > 1
                    && $misses === 0
                    && $unshot === 1
                    && $hits === ($gongCount - 1);

                $out[$shooterId][$ts->id] = [
                    'armed' => $armed,
                    'flushed' => $rfEnabled && $gongCount > 0 && $hits >= $gongCount,
                    'hits' => $hits,
                    'misses' => $misses,
                    'gong_count' => $gongCount,
                ];
            }
        }

        return $out;
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-primary sm:text-2xl">Correct scores — {{ $squad->name }}</h1>
            <p class="mt-1 text-sm text-muted">
                {{ $match->name }} &middot; {{ $shooters->count() }} shooters &middot; {{ $targetSets->count() }} target sets
            </p>
            <p class="mt-1 text-xs text-muted">
                Tap a gong to cycle <span class="text-green-400 font-semibold">hit</span> → <span class="text-accent font-semibold">miss</span> → <span class="text-muted">clear</span>. Nothing saves until you press <em>Save corrections</em>.
            </p>
        </div>
        @php
            $isAdminContext = str_starts_with(request()->route()->getName() ?? '', 'admin.');
            $backHref = $isAdminContext
                ? route('admin.matches.squadding', $match)
                : route('org.matches.squadding', [request()->route('organization'), $match]);
        @endphp
        <a href="{{ $backHref }}"
           class="rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-medium text-muted hover:border-accent hover:text-accent transition-colors">
            ← Back to squadding
        </a>
    </div>

    @if(!$match->isStandard())
        <div class="rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
            This match uses {{ strtoupper($match->scoring_type ?? 'standard') }} scoring. Standard-match corrections aren't the right tool here — use the PRS / ELR editors instead.
        </div>
    @endif

    @if($shooters->isEmpty() || $targetSets->isEmpty())
        <div class="rounded-2xl border border-dashed border-border bg-surface/50 p-8 text-center">
            <p class="text-muted">
                @if($shooters->isEmpty())
                    This squad has no shooters yet.
                @else
                    This match has no target sets yet — add at least one before correcting scores.
                @endif
            </p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($shooters as $shooter)
                @php
                    $rowStatuses = $rfArmed[$shooter->id] ?? [];
                    $anyArmed = collect($rowStatuses)->contains(fn ($s) => $s['armed']);
                @endphp
                <div wire:key="shooter-{{ $shooter->id }}"
                     class="rounded-2xl border {{ $anyArmed ? 'border-accent/70 bg-accent/5' : 'border-border bg-surface' }} overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border/60 px-4 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-primary">{{ $shooter->name }}</span>
                            @if($shooter->isNoShow())
                                <span class="rounded-full border border-zinc-500/30 bg-zinc-500/10 px-2 py-0.5 text-[10px] font-medium text-zinc-400">No show</span>
                            @elseif($shooter->isDq())
                                <span class="rounded-full border border-red-500/30 bg-red-500/10 px-2 py-0.5 text-[10px] font-medium text-red-400">DQ</span>
                            @endif
                        </div>
                        @if($anyArmed)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-accent/20 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-accent">
                                <span class="h-1.5 w-1.5 rounded-full bg-accent animate-pulse"></span>
                                Royal Flush armed
                            </span>
                        @endif
                    </div>

                    <div class="divide-y divide-border/40">
                        @foreach($targetSets as $ts)
                            @php
                                $status = $rowStatuses[$ts->id] ?? ['armed' => false, 'flushed' => false, 'hits' => 0, 'misses' => 0, 'gong_count' => $ts->gongs->count()];
                            @endphp
                            <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                                <div class="min-w-[7rem] text-sm">
                                    <div class="font-semibold text-primary">{{ $ts->label ?? ($ts->distance_meters . 'm') }}</div>
                                    <div class="text-[11px] text-muted tabular-nums">
                                        {{ $status['hits'] }}/{{ $status['gong_count'] }} hits
                                        @if($status['misses'] > 0)
                                            &middot; <span class="text-accent">{{ $status['misses'] }} miss{{ $status['misses'] === 1 ? '' : 'es' }}</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-1.5">
                                    @foreach($ts->gongs as $gong)
                                        @php
                                            $state = $cells[$shooter->id][$gong->id] ?? null;
                                            $cellClasses = match(true) {
                                                $state === true => 'border-green-500/60 bg-green-500/20 text-green-300',
                                                $state === false => 'border-accent/60 bg-accent/20 text-accent',
                                                default => 'border-border bg-surface-2 text-muted hover:border-accent/40',
                                            };
                                        @endphp
                                        <button type="button"
                                                wire:click="toggleCell({{ $shooter->id }}, {{ $gong->id }})"
                                                class="inline-flex h-10 min-w-[2.75rem] items-center justify-center rounded-lg border px-2 text-sm font-bold transition-colors {{ $cellClasses }}"
                                                title="Gong {{ $gong->number }}{{ $gong->label ? ' — ' . $gong->label : '' }}">
                                            {{ $state === true ? '✓' : ($state === false ? '✗' : $gong->number) }}
                                        </button>
                                    @endforeach
                                </div>

                                @if($status['flushed'])
                                    <span class="rounded-full bg-amber-500/20 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-300">Flushed</span>
                                @elseif($status['armed'])
                                    <span class="rounded-full bg-accent/20 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-accent">Armed</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="sticky bottom-0 -mx-4 border-t border-border bg-app/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-xl sm:border sm:bg-surface">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="correction-note" class="block text-xs font-medium text-muted mb-1">Correction note (required)</label>
                    <input id="correction-note" type="text" wire:model.live="note"
                           placeholder="e.g. Relay 3 300m paper book review, shooter 2 cell 4 was a hit not a miss"
                           class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:outline-none" />
                    @error('note')
                        <p class="mt-1 text-xs text-accent">{{ $message }}</p>
                    @enderror
                </div>
                <flux:button wire:click="save" variant="primary" class="!bg-accent hover:!bg-accent-hover">
                    Save corrections
                </flux:button>
            </div>
        </div>
    @endif
</div>
