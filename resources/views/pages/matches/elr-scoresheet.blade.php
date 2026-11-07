<?php

use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Services\Scoring\ElrScoresheetService;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Scoresheet')]
    class extends Component {
        public ShootingMatch $match;

        public ?Organization $organization = null;

        public string $note = '';

        public int $revision = 0;

        public function mount(ShootingMatch $match)
        {
            $org = request()->route('organization');
            if ($org instanceof Organization) {
                abort_unless($match->organization_id === $org->id, 404);
                $this->organization = $org;
            }

            abort_unless(auth()->user()?->can('correct', $match), 403, 'You are not authorized to correct scores for this match.');

            $this->match = $match;

            if (! $match->isElr()) {
                $isAdmin = str_starts_with(request()->route()?->getName() ?? '', 'admin.');
                Flux::toast('The full scoresheet is for ELR matches.', variant: 'warning');

                return $this->redirectRoute(
                    $isAdmin ? 'admin.matches.scoring' : 'org.matches.scoring',
                    $isAdmin ? ['match' => $match] : ['organization' => $org, 'match' => $match],
                    navigate: true,
                );
            }
        }

        /**
         * @param  array<string, string>  $dirty
         */
        public function save(array $dirty = []): void
        {
            abort_unless(auth()->user()?->can('correct', $this->match), 403);

            if (count($dirty) > 5000) {
                $this->addError('note', 'Too many cells in one save. Filter to a squad and save that group.');

                return;
            }

            $this->validate([
                'note' => ['required', 'string', 'min:3', 'max:500'],
            ]);

            if ($dirty === []) {
                Flux::toast('No score changes to save.', variant: 'warning');

                return;
            }

            $stats = app(ElrScoresheetService::class)->apply(
                $this->match,
                $dirty,
                $this->note,
                (int) auth()->id(),
            );

            $this->note = '';
            $this->revision++;

            if ($stats['written'] === 0 && $stats['cleared'] === 0) {
                Flux::toast('Those cells already matched the saved scores.', variant: 'warning');

                return;
            }

            Flux::toast($stats['written'].' shots saved, '.$stats['cleared'].' cleared.', variant: 'success');
        }

        public function with(): array
        {
            $isAdmin = str_starts_with(request()->route()?->getName() ?? '', 'admin.');

            return [
                'sheet' => $this->match->isElr()
                    ? app(ElrScoresheetService::class)->sheet($this->match)
                    : ['stages' => [], 'squads' => [], 'shooters' => [], 'cells' => []],
                'backHref' => $isAdmin
                    ? route('admin.matches.scoring', $this->match)
                    : route('org.matches.scoring', [$this->organization, $this->match]),
            ];
        }
    }; ?>

<div>
    <x-match-control-shell :match="$match" :organization="$organization" class="max-w-none">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-xl font-bold text-primary sm:text-2xl">Scoresheet</h1>
                <p class="mt-1 max-w-2xl text-sm text-muted">
                    Every shooter on this match, laid out like the paper. Target 1, 2 and 3 are the three gongs that shooter's class fires — a Minor's first target is the near gong, a Major's is the next one out. The distance on the row is the gong that cell writes to.
                </p>
                <p class="mt-1 text-xs text-muted">
                    Click a cell to cycle empty, hit (1), miss (0). Empty stays unscored. Nothing is stored until you save, and the match status does not change.
                </p>
            </div>
            <a href="{{ $backHref }}" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-medium text-muted transition-colors hover:border-accent hover:text-accent">
                Back to scoring
            </a>
        </div>

        @if($sheet['stages'] === [] || $sheet['shooters'] === [])
            <x-empty-state
                class="mt-4 rounded-2xl border border-dashed border-border bg-surface"
                title="Nothing to score yet"
                description="Add ELR stations and squad the shooters, then this sheet is where you enter or correct the whole match."
            >
                <x-slot:icon>
                    <x-icon name="target" class="h-5 w-5" />
                </x-slot:icon>
            </x-empty-state>
        @else
            <div
                wire:key="scoresheet-{{ $revision }}"
                class="mt-4 space-y-3"
                x-data="{
                    cells: {{ \Illuminate\Support\Js::from($sheet['cells']) }},
                    dirty: {},
                    squad: '',
                    saving: false,
                    cycle(key) {
                        const cur = this.cells[key] ?? '';
                        const next = cur === '' ? '1' : (cur === '1' ? '0' : '');
                        this.cells[key] = next;
                        this.dirty[key] = next;
                    },
                    label(key) {
                        const v = this.cells[key] ?? '';
                        return v === '' ? '·' : v;
                    },
                    klass(key) {
                        const v = this.cells[key] ?? '';
                        const ring = Object.prototype.hasOwnProperty.call(this.dirty, key) ? ' ring-2 ring-amber-400/80' : '';
                        if (v === '1') return 'bg-emerald-600 text-white' + ring;
                        if (v === '0') return 'bg-red-700 text-white' + ring;
                        return 'border border-dashed border-border text-muted hover:border-accent' + ring;
                    },
                    showSquad(id) {
                        return this.squad === '' || this.squad === String(id);
                    },
                    async submit() {
                        if (this.saving || Object.keys(this.dirty).length === 0) return;
                        this.saving = true;
                        try {
                            await this.$wire.save(this.dirty);
                        } finally {
                            this.saving = false;
                        }
                    },
                }"
            >
                <div class="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-surface px-4 py-3">
                    <div class="min-w-[14rem]">
                        <label for="scoresheet-squad" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-muted">Squad</label>
                        <select id="scoresheet-squad" x-model="squad" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:outline-none">
                            <option value="">All squads</option>
                            @foreach($sheet['squads'] as $squad)
                                <option value="{{ $squad['id'] }}">{{ $squad['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="pb-2 text-xs text-muted" x-text="Object.keys(dirty).length ? Object.keys(dirty).length + ' unsaved cells' : 'No unsaved changes'"></p>
                </div>

                <div class="max-h-[70vh] overflow-auto rounded-xl border border-border bg-surface">
                    <table class="w-max min-w-full border-separate border-spacing-0 text-xs">
                        <thead class="sticky top-0 z-20 bg-surface">
                            <tr>
                                <th class="sticky left-0 z-30 min-w-[12rem] border-b border-r border-border bg-surface px-3 py-2 text-left font-semibold text-muted">Shooter</th>
                                @foreach($sheet['stages'] as $stage)
                                    <th colspan="{{ $stage['rel_count'] }}" class="border-b border-l border-border px-2 py-2 text-center font-semibold text-primary">
                                        {{ $stage['label'] }}
                                    </th>
                                @endforeach
                            </tr>
                            <tr>
                                <th class="sticky left-0 z-30 border-b border-r border-border bg-surface px-3 py-1.5 text-left text-[10px] font-medium uppercase tracking-wider text-muted">Class · distance on the cell</th>
                                @foreach($sheet['stages'] as $stage)
                                    @for($rel = 0; $rel < $stage['rel_count']; $rel++)
                                        <th class="border-b border-border px-1 py-1.5 text-center text-[10px] font-semibold uppercase tracking-wider text-muted {{ $rel === 0 ? 'border-l' : '' }}">
                                            Target {{ $rel + 1 }}
                                        </th>
                                    @endfor
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @php $previousSquad = null; @endphp
                            @foreach($sheet['shooters'] as $shooter)
                                @if($shooter['squad_id'] !== $previousSquad)
                                    @php $previousSquad = $shooter['squad_id']; @endphp
                                    <tr x-show="showSquad({{ $shooter['squad_id'] }})" x-cloak>
                                        <td colspan="{{ 1 + collect($sheet['stages'])->sum('rel_count') }}" class="sticky left-0 border-b border-border bg-surface-2 px-3 py-1.5 text-[11px] font-bold uppercase tracking-wider text-muted">
                                            {{ $shooter['squad_name'] }}
                                        </td>
                                    </tr>
                                @endif
                                <tr x-show="showSquad({{ $shooter['squad_id'] }})" x-cloak class="hover:bg-surface-2/40">
                                    <td class="sticky left-0 z-10 border-b border-r border-border bg-surface px-3 py-1.5">
                                        <p class="truncate font-semibold text-primary">{{ $shooter['name'] }}</p>
                                        <p class="truncate text-[10px] text-muted">
                                            {{ $shooter['division'] !== '' ? $shooter['division'] : 'No class' }}
                                            @if($shooter['team'] !== '')
                                                · {{ $shooter['team'] }}
                                            @endif
                                        </p>
                                    </td>
                                    @foreach($shooter['groups'] as $groupIndex => $group)
                                        @foreach($group['targets'] as $targetIndex => $target)
                                            <td class="border-b border-border px-1 py-1 text-center align-middle {{ $targetIndex === 0 ? 'border-l' : '' }}">
                                                @if($target === null)
                                                    <span class="text-muted">—</span>
                                                @else
                                                    <p class="mb-0.5 text-[10px] font-semibold tabular-nums text-muted">{{ $target['distance'] }}m</p>
                                                    <div class="flex items-center justify-center gap-0.5">
                                                        @foreach($target['shots'] as $shot)
                                                            @if($shot === null)
                                                                <span class="flex h-7 w-7 items-center justify-center text-muted">—</span>
                                                            @else
                                                                <button
                                                                    type="button"
                                                                    @click="cycle('{{ $shot['key'] }}')"
                                                                    :class="klass('{{ $shot['key'] }}')"
                                                                    class="flex h-7 w-7 items-center justify-center rounded-md text-[11px] font-bold tabular-nums transition-transform active:scale-95"
                                                                    x-text="label('{{ $shot['key'] }}')"
                                                                ></button>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                        @endforeach
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <form class="sticky bottom-3 flex flex-wrap items-end gap-3 rounded-xl border border-border bg-surface p-3 shadow-lg" @submit.prevent="submit()">
                    <div class="min-w-[16rem] flex-1">
                        <label for="scoresheet-note" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-muted">Note</label>
                        <input
                            id="scoresheet-note"
                            type="text"
                            wire:model="note"
                            maxlength="500"
                            placeholder="Why these cells changed"
                            class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder:text-muted focus:border-accent focus:outline-none"
                        />
                        @error('note')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <button
                        type="submit"
                        class="rounded-lg bg-accent px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-accent-hover disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="saving || Object.keys(dirty).length === 0"
                    >
                        <span x-show="!saving">Save scoresheet</span>
                        <span x-show="saving" x-cloak>Saving…</span>
                    </button>
                </form>
            </div>
        @endif
    </x-match-control-shell>
</div>
