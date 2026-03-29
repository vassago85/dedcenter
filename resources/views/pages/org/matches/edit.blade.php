<?php

use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\MatchDivision;
use App\Models\MatchCategory;
use App\Models\TargetSet;
use App\Models\Gong;
use App\Models\Squad;
use App\Models\Shooter;
use App\Enums\MatchStatus;
use chillerlan\QRCode\{QRCode, QROptions};
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    class extends Component {
    public Organization $organization;
    public ?ShootingMatch $match = null;

    public string $name = '';
    public string $date = '';
    public string $location = '';
    public string $notes = '';
    public string $entry_fee = '';
    public string $scoring_type = 'standard';
    public bool $side_bet_enabled = false;

    public string $tsDistance = '';

    public string $gongNumber = '';
    public string $gongLabel = '';
    public string $gongMultiplier = '1.00';
    public ?int $addingGongToTargetSetId = null;

    public string $squadName = '';

    public string $shooterName = '';
    public string $shooterBib = '';
    public ?int $addingShooterToSquadId = null;
    public ?int $shooterDivision = null;

    public string $divisionName = '';
    public string $categoryName = '';
    public array $shooterCategories = [];

    public function mount(Organization $organization, ?ShootingMatch $match = null): void
    {
        $this->organization = $organization;

        if ($match && $match->exists) {
            if ($match->created_by !== auth()->id() && !auth()->user()->isAdmin()) {
                abort(403, 'Only the match creator can edit this match.');
            }

            $this->match = $match;
            $this->name = $match->name;
            $this->date = $match->date?->format('Y-m-d') ?? '';
            $this->location = $match->location ?? '';
            $this->notes = $match->notes ?? '';
            $this->entry_fee = $match->entry_fee ? (string) $match->entry_fee : '';
            $this->scoring_type = $match->scoring_type ?? 'standard';
            $this->side_bet_enabled = (bool) $match->side_bet_enabled;
        } else {
            $this->entry_fee = $organization->entry_fee_default ? (string) $organization->entry_fee_default : '';
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
            'entry_fee' => 'nullable|numeric|min:0',
            'scoring_type' => 'required|in:standard,prs',
        ]);

        $validated['entry_fee'] = $this->entry_fee !== '' ? (float) $this->entry_fee : null;
        $validated['side_bet_enabled'] = $this->scoring_type === 'standard' && $this->side_bet_enabled;

        if ($this->match) {
            $this->match->update($validated);
            Flux::toast('Match updated.', variant: 'success');
        } else {
            $this->match = ShootingMatch::create([
                ...$validated,
                'organization_id' => $this->organization->id,
                'created_by' => auth()->id(),
                'status' => MatchStatus::Draft,
            ]);
            Flux::toast('Match created.', variant: 'success');
            $this->redirect(route('org.matches.edit', [$this->organization, $this->match]), navigate: true);
        }
    }

    public function addTargetSet(): void
    {
        $this->validate(['tsDistance' => 'required|integer|min:1']);
        $distance = (int) $this->tsDistance;
        $maxSort = $this->match->targetSets()->max('sort_order') ?? 0;
        $this->match->targetSets()->create([
            'label' => "{$distance}m",
            'distance_meters' => $distance,
            'sort_order' => $maxSort + 1,
        ]);
        $this->reset('tsDistance');
        Flux::toast('Target set added.', variant: 'success');
    }

    public function updateTargetSet(int $id, string $field, string $value): void
    {
        $ts = TargetSet::where('id', $id)->where('match_id', $this->match->id)->firstOrFail();
        if ($field === 'distance_meters') {
            $distance = max(1, (int) $value);
            $ts->update(['distance_meters' => $distance, 'label' => "{$distance}m"]);
        } elseif ($field === 'label') {
            $ts->update(['label' => $value]);
        }
    }

    public function setTiebreakerStage(int $targetSetId): void
    {
        $this->match->targetSets()->update(['is_tiebreaker' => false]);
        TargetSet::where('id', $targetSetId)->where('match_id', $this->match->id)->update(['is_tiebreaker' => true]);
        Flux::toast('Tiebreaker stage set.', variant: 'success');
    }

    public function updateParTime(int $targetSetId, string $value): void
    {
        $ts = TargetSet::where('id', $targetSetId)->where('match_id', $this->match->id)->firstOrFail();
        $ts->update(['par_time_seconds' => $value !== '' ? max(0, (float) $value) : null]);
    }

    public function cloneTargetSet(int $id): void
    {
        $source = TargetSet::where('id', $id)->where('match_id', $this->match->id)->firstOrFail();
        $maxSort = $this->match->targetSets()->max('sort_order') ?? 0;
        $clone = $this->match->targetSets()->create([
            'label' => $source->label . ' (copy)',
            'distance_meters' => $source->distance_meters,
            'sort_order' => $maxSort + 1,
        ]);
        foreach ($source->gongs as $gong) {
            $clone->gongs()->create(['number' => $gong->number, 'label' => $gong->label, 'multiplier' => $gong->multiplier]);
        }
        Flux::toast('Target set cloned.', variant: 'success');
    }

    public function deleteTargetSet(int $id): void
    {
        TargetSet::where('id', $id)->where('match_id', $this->match->id)->delete();
        Flux::toast('Target set deleted.', variant: 'success');
    }

    public function populateStandardTargets(int $targetSetId): void
    {
        $standards = [
            ['number' => 1, 'label' => '2.5 MOA', 'multiplier' => '1.00'],
            ['number' => 2, 'label' => '2.0 MOA', 'multiplier' => '1.30'],
            ['number' => 3, 'label' => '1.5 MOA', 'multiplier' => '1.50'],
            ['number' => 4, 'label' => '1.0 MOA', 'multiplier' => '1.80'],
            ['number' => 5, 'label' => '0.5 MOA', 'multiplier' => '2.00'],
        ];
        $maxNumber = Gong::where('target_set_id', $targetSetId)->max('number') ?? 0;
        foreach ($standards as $s) {
            Gong::create(['target_set_id' => $targetSetId, 'number' => $maxNumber + $s['number'], 'label' => $s['label'], 'multiplier' => $s['multiplier']]);
        }
        Flux::toast('5 standard targets added.', variant: 'success');
    }

    public function populatePrsTargets(int $targetSetId, int $count = 5): void
    {
        $maxNumber = Gong::where('target_set_id', $targetSetId)->max('number') ?? 0;
        for ($i = 1; $i <= $count; $i++) {
            Gong::create(['target_set_id' => $targetSetId, 'number' => $maxNumber + $i, 'label' => "T{$i}", 'multiplier' => '1.00']);
        }
        Flux::toast("{$count} PRS targets added (1pt each).", variant: 'success');
    }

    public function startAddGong(int $targetSetId): void
    {
        $this->addingGongToTargetSetId = $targetSetId;
        $this->reset('gongNumber', 'gongLabel', 'gongMultiplier');
        $this->gongMultiplier = '1.00';
        $maxNumber = Gong::where('target_set_id', $targetSetId)->max('number') ?? 0;
        $this->gongNumber = (string) ($maxNumber + 1);
    }

    public function addGong(): void
    {
        $this->validate(['gongNumber' => 'required|integer|min:1', 'gongLabel' => 'nullable|string|max:255', 'gongMultiplier' => 'required|numeric|min:0.01']);
        Gong::create(['target_set_id' => $this->addingGongToTargetSetId, 'number' => (int) $this->gongNumber, 'label' => $this->gongLabel ?: null, 'multiplier' => $this->gongMultiplier]);
        $this->addingGongToTargetSetId = null;
        $this->reset('gongNumber', 'gongLabel', 'gongMultiplier');
        Flux::toast('Gong added.', variant: 'success');
    }

    public function updateGong(int $gongId, string $field, string $value): void
    {
        $gong = Gong::findOrFail($gongId);
        if ($field === 'label') { $gong->update(['label' => $value ?: null]); }
        elseif ($field === 'multiplier') { $gong->update(['multiplier' => max(0.01, (float) $value)]); }
    }

    public function deleteGong(int $id): void { Gong::destroy($id); Flux::toast('Gong deleted.', variant: 'success'); }

    public function addSquad(): void
    {
        $this->validate(['squadName' => 'required|string|max:255']);
        $maxSort = $this->match->squads()->max('sort_order') ?? 0;
        $this->match->squads()->create(['name' => $this->squadName, 'sort_order' => $maxSort + 1]);
        $this->reset('squadName');
        Flux::toast('Squad added.', variant: 'success');
    }

    public function deleteSquad(int $id): void
    {
        Squad::where('id', $id)->where('match_id', $this->match->id)->delete();
        Flux::toast('Squad deleted.', variant: 'success');
    }

    public function startAddShooter(int $squadId): void { $this->addingShooterToSquadId = $squadId; $this->reset('shooterName', 'shooterBib'); $this->shooterDivision = null; $this->shooterCategories = []; }

    public function addShooter(): void
    {
        $this->validate(['shooterName' => 'required|string|max:255', 'shooterBib' => 'nullable|string|max:50']);
        $maxSort = Shooter::where('squad_id', $this->addingShooterToSquadId)->max('sort_order') ?? 0;
        $shooter = Shooter::create([
            'squad_id' => $this->addingShooterToSquadId,
            'name' => $this->shooterName,
            'bib_number' => $this->shooterBib ?: null,
            'match_division_id' => $this->shooterDivision ?: null,
            'sort_order' => $maxSort + 1,
        ]);
        if (!empty($this->shooterCategories)) {
            $shooter->categories()->sync(array_map('intval', $this->shooterCategories));
        }
        $this->addingShooterToSquadId = null;
        $this->reset('shooterName', 'shooterBib');
        $this->shooterDivision = null;
        $this->shooterCategories = [];
        Flux::toast('Shooter added.', variant: 'success');
    }

    public function deleteShooter(int $id): void { Shooter::destroy($id); Flux::toast('Shooter deleted.', variant: 'success'); }

    public function updateShooterDivision(int $shooterId, string $value): void
    {
        $shooter = Shooter::findOrFail($shooterId);
        $shooter->update(['match_division_id' => $value !== '' ? (int) $value : null]);
    }

    // ── Divisions ──

    public function addDivision(): void
    {
        $this->validate(['divisionName' => 'required|string|max:255']);
        $maxSort = $this->match->divisions()->max('sort_order') ?? 0;
        $this->match->divisions()->create(['name' => $this->divisionName, 'sort_order' => $maxSort + 1]);
        $this->reset('divisionName');
        Flux::toast('Division added.', variant: 'success');
    }

    public function addMinorMajorPreset(): void
    {
        $maxSort = $this->match->divisions()->max('sort_order') ?? 0;
        $this->match->divisions()->create(['name' => 'Minor (.30 cal and below)', 'sort_order' => $maxSort + 1]);
        $this->match->divisions()->create(['name' => 'Major (above .30 cal)', 'sort_order' => $maxSort + 2]);
        Flux::toast('Minor/Major divisions added.', variant: 'success');
    }

    public function updateDivision(int $id, string $name): void
    {
        if (trim($name) === '') return;
        MatchDivision::where('id', $id)->where('match_id', $this->match->id)->update(['name' => trim($name)]);
    }

    public function deleteDivision(int $id): void
    {
        MatchDivision::where('id', $id)->where('match_id', $this->match->id)->delete();
        Flux::toast('Division deleted.', variant: 'success');
    }

    public function addSaprfDivisionPreset(): void
    {
        $maxSort = $this->match->divisions()->max('sort_order') ?? 0;
        $presets = [
            ['name' => 'Open', 'description' => 'Unrestricted equipment class'],
            ['name' => 'Factory', 'description' => 'Factory-stock rifle, no modifications'],
            ['name' => 'Limited', 'description' => 'Limited modifications allowed'],
        ];
        foreach ($presets as $i => $p) {
            $this->match->divisions()->create([...$p, 'sort_order' => $maxSort + $i + 1]);
        }
        Flux::toast('Open/Factory/Limited divisions added.', variant: 'success');
    }

    // ── Categories ──

    public function addCategory(): void
    {
        $this->validate(['categoryName' => 'required|string|max:255']);
        $maxSort = $this->match->categories()->max('sort_order') ?? 0;
        $slug = \Illuminate\Support\Str::slug($this->categoryName);
        $this->match->categories()->create(['name' => $this->categoryName, 'slug' => $slug, 'sort_order' => $maxSort + 1]);
        $this->reset('categoryName');
        Flux::toast('Category added.', variant: 'success');
    }

    public function addSaprfCategoryPreset(): void
    {
        $maxSort = $this->match->categories()->max('sort_order') ?? 0;
        $presets = [
            ['name' => 'Overall', 'slug' => 'overall', 'description' => 'All shooters — default catch-all'],
            ['name' => 'Ladies', 'slug' => 'ladies', 'description' => 'Female shooters'],
            ['name' => 'Junior', 'slug' => 'junior', 'description' => 'Shooters under 21'],
            ['name' => 'Senior', 'slug' => 'senior', 'description' => 'Shooters 55+'],
        ];
        foreach ($presets as $i => $p) {
            $this->match->categories()->create([...$p, 'sort_order' => $maxSort + $i + 1]);
        }
        Flux::toast('SAPRF category presets added.', variant: 'success');
    }

    public function updateCategory(int $id, string $name): void
    {
        if (trim($name) === '') return;
        MatchCategory::where('id', $id)->where('match_id', $this->match->id)->update(['name' => trim($name)]);
    }

    public function deleteCategory(int $id): void
    {
        MatchCategory::where('id', $id)->where('match_id', $this->match->id)->delete();
        Flux::toast('Category deleted.', variant: 'success');
    }

    public function updateShooterCategories(int $shooterId, array $categoryIds): void
    {
        $shooter = Shooter::findOrFail($shooterId);
        $shooter->categories()->sync(array_map('intval', $categoryIds));
    }

    public function startMatch(): void { $this->match->update(['status' => MatchStatus::Active]); Flux::toast('Match started!', variant: 'success'); }
    public function completeMatch(): void { $this->match->update(['status' => MatchStatus::Completed]); Flux::toast('Match completed.', variant: 'success'); }
    public function reopenMatch(): void { $this->match->update(['status' => MatchStatus::Active]); Flux::toast('Match reopened.', variant: 'success'); }

    public function with(): array
    {
        $data = ['divisions' => collect(), 'categories' => collect(), 'qrCodeSvg' => null];
        if ($this->match) {
            $data['targetSets'] = $this->match->targetSets()->with('gongs')->orderBy('sort_order')->get();
            $data['squads'] = $this->match->squads()->with(['shooters.division', 'shooters.categories'])->orderBy('sort_order')->get();
            $data['divisions'] = $this->match->divisions()->orderBy('sort_order')->get();
            $data['categories'] = $this->match->categories()->orderBy('sort_order')->get();

            if (in_array($this->match->status, [MatchStatus::Active, MatchStatus::Completed])) {
                $liveUrl = route('live', $this->match);
                $options = new QROptions(['outputType' => QRCode::OUTPUT_MARKUP_SVG, 'svgUseCssProperties' => false, 'scale' => 5]);
                $data['qrCodeSvg'] = (new QRCode($options))->render($liveUrl);
                $data['liveUrl'] = $liveUrl;
            }
        }
        return $data;
    }
}; ?>

<div class="space-y-8 max-w-4xl">
    <div class="flex items-center gap-4">
        <flux:button href="{{ route('org.matches.index', $organization) }}" variant="ghost" size="sm">
            <svg class="mr-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
            Back
        </flux:button>
        <div>
            <flux:heading size="xl">{{ $match ? 'Edit Match' : 'New Match' }}</flux:heading>
            <p class="mt-1 text-sm text-slate-400">{{ $organization->name }}</p>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-xl border border-slate-700 bg-slate-800 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-white">Match Details</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" label="Name" placeholder="e.g. Monthly Steel Challenge" required />
                <flux:input wire:model="date" label="Date" type="date" required />
            </div>
            <flux:input wire:model="location" label="Location" placeholder="e.g. Range 3, Pretoria" />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <flux:input wire:model="entry_fee" label="Entry Fee (ZAR)" type="number" step="0.01" min="0" placeholder="Leave empty for free" />
                    <p class="mt-1 text-xs text-slate-500">Leave empty or 0 for free entry.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Scoring Type</label>
                    <div class="flex gap-2">
                        <button type="button" wire:click="$set('scoring_type', 'standard')"
                                class="flex-1 rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $scoring_type === 'standard' ? 'bg-red-600 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600' }}">
                            Standard
                        </button>
                        <button type="button" wire:click="$set('scoring_type', 'prs')"
                                class="flex-1 rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $scoring_type === 'prs' ? 'bg-amber-600 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600' }}">
                            PRS
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        @if($scoring_type === 'prs')
                            PRS: Hit/miss (1pt each), shooter completes full stage, optional timer for tiebreaker.
                        @else
                            Standard: Gong multipliers, relay-style scoring.
                        @endif
                    </p>
                </div>
            </div>
            @if($scoring_type === 'standard')
                <div class="rounded-lg border border-slate-700 bg-slate-700/30 p-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" wire:model="side_bet_enabled"
                               class="rounded border-slate-600 bg-slate-700 text-red-500 focus:ring-red-500 focus:ring-offset-0 h-5 w-5" />
                        <div>
                            <span class="text-sm font-medium text-white">Enable Side Bet</span>
                            <p class="text-xs text-slate-400">Rank by smallest gong hits with furthest-distance tiebreaker. Winner is whoever hits the most small gongs.</p>
                        </div>
                    </label>
                </div>
            @endif
            <flux:textarea wire:model="notes" label="Notes" placeholder="Optional notes about this match..." rows="3" />
            <div class="flex justify-end pt-2">
                <flux:button type="submit" variant="primary" class="!bg-red-600 hover:!bg-red-700">
                    {{ $match ? 'Save Changes' : 'Create Match' }}
                </flux:button>
            </div>
        </div>
    </form>

    @if($match)
        <div class="rounded-xl border border-slate-700 bg-slate-800 p-6">
            <h2 class="text-lg font-semibold text-white mb-4">Match Controls</h2>
            <div class="flex flex-wrap gap-3">
                @if($match->status === MatchStatus::Draft)
                    <flux:button wire:click="startMatch" variant="primary" class="!bg-green-600 hover:!bg-green-700" wire:confirm="Start this match?">Start Match</flux:button>
                @elseif($match->status === MatchStatus::Active)
                    <flux:button wire:click="completeMatch" variant="primary" class="!bg-blue-600 hover:!bg-blue-700" wire:confirm="Complete this match?">Complete Match</flux:button>
                    <flux:button href="{{ route('scoring') }}" target="_blank" variant="ghost">Open Scoring</flux:button>
                    <flux:button href="{{ route('scoreboard', $match) }}" target="_blank" variant="ghost">View Scoreboard</flux:button>
                @elseif($match->status === MatchStatus::Completed)
                    <flux:button wire:click="reopenMatch" variant="ghost" wire:confirm="Reopen this match?">Reopen Match</flux:button>
                @endif
            </div>

            @if($qrCodeSvg)
                <div class="mt-4 border-t border-slate-700 pt-4">
                    <h3 class="text-sm font-medium text-slate-300 mb-3">Live Scoreboard</h3>
                    <div class="flex items-start gap-4">
                        <div class="rounded-lg bg-white p-2 w-32 h-32 flex-shrink-0">{!! $qrCodeSvg !!}</div>
                        <div class="space-y-2">
                            <p class="text-xs text-slate-400">Share this QR code for spectators to follow scores live on their phones.</p>
                            <div class="flex items-center gap-2">
                                <input type="text" value="{{ $liveUrl }}" readonly class="flex-1 rounded-md border border-slate-600 bg-slate-700 px-3 py-1.5 text-xs text-slate-300" />
                                <button onclick="navigator.clipboard.writeText('{{ $liveUrl }}'); this.textContent='Copied!'; setTimeout(() => this.textContent='Copy', 2000)"
                                        class="rounded-md bg-slate-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-600">Copy</button>
                            </div>
                            <flux:button href="{{ route('live', $match) }}" target="_blank" variant="ghost" size="sm">Open Live Scoreboard</flux:button>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <flux:separator />

        {{-- Divisions --}}
        <div class="space-y-4">
            <h2 class="text-lg font-semibold text-white">Divisions</h2>
            @if($divisions->isNotEmpty())
                <div class="space-y-2">
                    @foreach($divisions as $div)
                        <div class="flex items-center gap-3 rounded-lg border border-slate-700 bg-slate-800 px-4 py-2" wire:key="div-{{ $div->id }}">
                            <input type="text" value="{{ $div->name }}"
                                   class="flex-1 rounded-md border border-slate-600 bg-slate-700 px-3 py-1.5 text-sm text-white focus:border-red-500 focus:ring-1 focus:ring-red-500"
                                   wire:change="updateDivision({{ $div->id }}, $event.target.value)" />
                            <span class="text-xs text-slate-500">{{ $div->shooters()->count() }} shooters</span>
                            <button class="text-red-400 hover:text-red-300 text-lg leading-none" wire:click="deleteDivision({{ $div->id }})" wire:confirm="Delete this division?">&times;</button>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-slate-500">No divisions. All shooters compete in a single combined pool.</p>
            @endif
            <div class="rounded-xl border border-dashed border-slate-600 bg-slate-800/50 p-4 space-y-3">
                <div class="flex gap-3 items-end">
                    <div class="flex-1"><flux:input wire:model="divisionName" placeholder="e.g. Open, Production..." /></div>
                    <flux:button wire:click="addDivision" size="sm" variant="primary" class="!bg-red-600 hover:!bg-red-700">Add Division</flux:button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <flux:button wire:click="addMinorMajorPreset" size="sm" variant="ghost">+ Minor/Major</flux:button>
                    <flux:button wire:click="addSaprfDivisionPreset" size="sm" variant="ghost">+ Open/Factory/Limited</flux:button>
                </div>
                <p class="text-[10px] text-slate-600">Divisions classify by equipment class. Single-select per shooter.</p>
            </div>
        </div>

        {{-- Categories --}}
        <div class="space-y-4">
            <h2 class="text-lg font-semibold text-white">Categories</h2>
            @if($categories->isNotEmpty())
                <div class="space-y-2">
                    @foreach($categories as $cat)
                        <div class="flex items-center gap-3 rounded-lg border border-slate-700 bg-slate-800 px-4 py-2" wire:key="cat-{{ $cat->id }}">
                            <input type="text" value="{{ $cat->name }}"
                                   class="flex-1 rounded-md border border-slate-600 bg-slate-700 px-3 py-1.5 text-sm text-white focus:border-red-500 focus:ring-1 focus:ring-red-500"
                                   wire:change="updateCategory({{ $cat->id }}, $event.target.value)" />
                            @if($cat->description)
                                <span class="text-[10px] text-slate-500 hidden sm:inline">{{ $cat->description }}</span>
                            @endif
                            <span class="text-xs text-slate-500">{{ $cat->shooters()->count() }}</span>
                            <button class="text-red-400 hover:text-red-300 text-lg leading-none" wire:click="deleteCategory({{ $cat->id }})" wire:confirm="Delete this category?">&times;</button>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-slate-500">No categories. Add demographic categories like Overall, Ladies, Junior, Senior.</p>
            @endif
            <div class="rounded-xl border border-dashed border-slate-600 bg-slate-800/50 p-4 space-y-3">
                <div class="flex gap-3 items-end">
                    <div class="flex-1"><flux:input wire:model="categoryName" placeholder="e.g. Ladies, Junior..." /></div>
                    <flux:button wire:click="addCategory" size="sm" variant="primary" class="!bg-red-600 hover:!bg-red-700">Add Category</flux:button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <flux:button wire:click="addSaprfCategoryPreset" size="sm" variant="ghost">+ SAPRF Preset (Overall/Ladies/Junior/Senior)</flux:button>
                </div>
                <p class="text-[10px] text-slate-600">Categories classify by demographics. Multi-select per shooter (a score appears in all matching category leaderboards).</p>
            </div>
        </div>

        <flux:separator />

        {{-- Target Sets --}}
        <div class="space-y-4">
            <h2 class="text-lg font-semibold text-white">Target Sets</h2>
            @foreach($targetSets as $ts)
                <div class="rounded-xl border {{ $ts->is_tiebreaker && $scoring_type === 'prs' ? 'border-amber-500/50 ring-1 ring-amber-500/20' : 'border-slate-700' }} bg-slate-800 overflow-hidden" wire:key="ts-{{ $ts->id }}">
                    <div class="flex items-center justify-between border-b border-slate-700 px-6 py-3">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-1">
                                <input type="number" value="{{ $ts->distance_meters }}" min="1"
                                       class="w-20 rounded-md border border-slate-600 bg-slate-700 px-2 py-1 text-sm text-white text-center focus:border-red-500 focus:ring-1 focus:ring-red-500"
                                       wire:change="updateTargetSet({{ $ts->id }}, 'distance_meters', $event.target.value)" />
                                <span class="text-sm text-slate-400">m</span>
                            </div>
                            <span class="text-xs text-slate-500">({{ $ts->gongs->count() }} targets)</span>
                            @if($scoring_type === 'prs' && $ts->is_tiebreaker)
                                <span class="rounded bg-amber-600 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white">Tiebreaker</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            @if($scoring_type === 'prs')
                                <button wire:click="setTiebreakerStage({{ $ts->id }})"
                                        class="rounded-lg px-2 py-1 text-xs font-medium transition-colors {{ $ts->is_tiebreaker ? 'bg-amber-600 text-white' : 'bg-slate-700 text-slate-400 hover:bg-slate-600 hover:text-white' }}"
                                        title="Set as tiebreaker stage">
                                    &#9201; TB
                                </button>
                            @endif
                            <flux:button size="sm" variant="ghost" wire:click="cloneTargetSet({{ $ts->id }})">Clone</flux:button>
                            <flux:button size="sm" variant="ghost" class="!text-red-400 hover:!text-red-300" wire:click="deleteTargetSet({{ $ts->id }})" wire:confirm="Delete this target set?">Delete</flux:button>
                        </div>
                    </div>
                    @if($scoring_type === 'prs')
                        <div class="flex items-center gap-3 border-b border-slate-700/50 bg-slate-800/50 px-6 py-2">
                            <label class="text-xs font-medium text-slate-400 whitespace-nowrap">Par Time (s):</label>
                            <input type="number" value="{{ $ts->par_time_seconds }}" step="0.01" min="0" placeholder="e.g. 90.00"
                                   class="w-28 rounded-md border border-slate-600 bg-slate-700 px-2 py-1 text-sm text-white text-center focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                   wire:change="updateParTime({{ $ts->id }}, $event.target.value)" />
                            <span class="text-xs text-slate-500">Max time for this stage. Incomplete shooters get this time.</span>
                        </div>
                    @endif
                    <div class="p-4 space-y-3">
                        @if($ts->gongs->isNotEmpty())
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead><tr class="text-left text-slate-400 border-b border-slate-700/50"><th class="px-3 py-2 font-medium w-12">#</th><th class="px-3 py-2 font-medium">Label</th><th class="px-3 py-2 font-medium w-28">Multiplier</th><th class="px-3 py-2 font-medium w-10"></th></tr></thead>
                                    <tbody class="divide-y divide-slate-700/50">
                                        @foreach($ts->gongs->sortBy('number') as $gong)
                                            <tr wire:key="gong-{{ $gong->id }}">
                                                <td class="px-3 py-1.5 text-slate-400 font-mono">{{ $gong->number }}</td>
                                                <td class="px-3 py-1.5"><input type="text" value="{{ $gong->label }}" placeholder="e.g. 2.5 MOA" class="w-full rounded border border-slate-600 bg-slate-700 px-2 py-1 text-sm text-white placeholder-slate-500 focus:border-red-500 focus:ring-1 focus:ring-red-500" wire:change="updateGong({{ $gong->id }}, 'label', $event.target.value)" /></td>
                                                <td class="px-3 py-1.5"><div class="flex items-center gap-1"><input type="number" value="{{ $gong->multiplier }}" step="0.01" min="0.01" class="w-20 rounded border border-slate-600 bg-slate-700 px-2 py-1 text-sm text-white text-center focus:border-red-500 focus:ring-1 focus:ring-red-500" wire:change="updateGong({{ $gong->id }}, 'multiplier', $event.target.value)" /><span class="text-slate-400 text-xs">x</span></div></td>
                                                <td class="px-3 py-1.5 text-right"><button class="text-red-400 hover:text-red-300 text-lg leading-none" wire:click="deleteGong({{ $gong->id }})">&times;</button></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-sm text-slate-500 px-3">No targets yet.</p>
                        @endif
                        @if($addingGongToTargetSetId === $ts->id)
                            <div class="rounded-lg border border-slate-600 bg-slate-700/50 p-4 space-y-3">
                                <div class="grid grid-cols-3 gap-3">
                                    <flux:input wire:model="gongNumber" label="#" type="number" min="1" required />
                                    <flux:input wire:model="gongLabel" label="Label" placeholder="e.g. 1.5 MOA" />
                                    <flux:input wire:model="gongMultiplier" label="Multiplier" type="number" step="0.01" min="0.01" required />
                                </div>
                                <div class="flex gap-2">
                                    <flux:button wire:click="addGong" size="sm" variant="primary" class="!bg-red-600 hover:!bg-red-700">Add Target</flux:button>
                                    <flux:button wire:click="$set('addingGongToTargetSetId', null)" size="sm" variant="ghost">Cancel</flux:button>
                                </div>
                            </div>
                        @else
                            <div class="flex flex-wrap gap-2 px-3">
                                @if($ts->gongs->isEmpty())
                                    @if($match->scoring_type === 'prs')
                                        <flux:button size="sm" variant="primary" class="!bg-amber-600 hover:!bg-amber-700" wire:click="populatePrsTargets({{ $ts->id }}, 5)">+ 5 PRS Targets</flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="populatePrsTargets({{ $ts->id }}, 8)">+ 8 Targets</flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="populatePrsTargets({{ $ts->id }}, 10)">+ 10 Targets</flux:button>
                                    @else
                                        <flux:button size="sm" variant="primary" class="!bg-red-600 hover:!bg-red-700" wire:click="populateStandardTargets({{ $ts->id }})">+ Add Standard Targets (5 MOA)</flux:button>
                                    @endif
                                @endif
                                <flux:button size="sm" variant="ghost" wire:click="startAddGong({{ $ts->id }})">+ Add Custom Target</flux:button>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
            <div class="rounded-xl border border-dashed border-slate-600 bg-slate-800/50 p-4 space-y-3">
                <h3 class="text-sm font-medium text-slate-300">Add Target Set</h3>
                <div class="flex gap-3 items-end">
                    <div class="w-32"><flux:input wire:model="tsDistance" label="Distance (m)" type="number" min="1" placeholder="e.g. 100" /></div>
                    <flux:button wire:click="addTargetSet" size="sm" variant="primary" class="!bg-red-600 hover:!bg-red-700">Add Target Set</flux:button>
                </div>
            </div>
        </div>

        <flux:separator />

        {{-- Squads --}}
        <div class="space-y-4">
            <h2 class="text-lg font-semibold text-white">Squads</h2>
            @foreach($squads as $squad)
                <div class="rounded-xl border border-slate-700 bg-slate-800 overflow-hidden" wire:key="squad-{{ $squad->id }}">
                    <div class="flex items-center justify-between border-b border-slate-700 px-6 py-3">
                        <span class="font-medium text-white">{{ $squad->name }}</span>
                        <flux:button size="sm" variant="ghost" class="!text-red-400 hover:!text-red-300" wire:click="deleteSquad({{ $squad->id }})" wire:confirm="Delete this squad?">Delete</flux:button>
                    </div>
                    <div class="p-4 space-y-3">
                        @if($squad->shooters->isNotEmpty())
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead><tr class="text-left text-slate-400 border-b border-slate-700/50"><th class="px-3 py-2 font-medium">Name</th><th class="px-3 py-2 font-medium">Bib #</th>@if($divisions->isNotEmpty())<th class="px-3 py-2 font-medium">Division</th>@endif @if($categories->isNotEmpty())<th class="px-3 py-2 font-medium">Categories</th>@endif<th class="px-3 py-2 font-medium"></th></tr></thead>
                                    <tbody class="divide-y divide-slate-700/50">
                                        @foreach($squad->shooters->sortBy('sort_order') as $shooter)
                                            <tr wire:key="shooter-{{ $shooter->id }}">
                                                <td class="px-3 py-2 text-slate-300">{{ $shooter->name }}</td>
                                                <td class="px-3 py-2 text-slate-300">{{ $shooter->bib_number ?? '—' }}</td>
                                                @if($divisions->isNotEmpty())
                                                    <td class="px-3 py-2">
                                                        <select class="rounded border border-slate-600 bg-slate-700 px-2 py-1 text-xs text-white focus:border-red-500"
                                                                wire:change="updateShooterDivision({{ $shooter->id }}, $event.target.value)">
                                                            <option value="" {{ !$shooter->match_division_id ? 'selected' : '' }}>—</option>
                                                            @foreach($divisions as $d)
                                                                <option value="{{ $d->id }}" {{ $shooter->match_division_id == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                @endif
                                                @if($categories->isNotEmpty())
                                                    <td class="px-3 py-2">
                                                        <div class="flex flex-wrap gap-1" x-data="{ cats: {{ json_encode($shooter->categories->pluck('id')->toArray()) }} }">
                                                            @foreach($categories as $cat)
                                                                <label class="inline-flex items-center gap-0.5 cursor-pointer">
                                                                    <input type="checkbox" value="{{ $cat->id }}"
                                                                           class="rounded border-slate-600 bg-slate-700 text-red-500 focus:ring-red-500 focus:ring-offset-0 h-3 w-3"
                                                                           {{ $shooter->categories->contains('id', $cat->id) ? 'checked' : '' }}
                                                                           x-on:change="
                                                                               let id = {{ $cat->id }};
                                                                               if ($event.target.checked) { cats.push(id); } else { cats = cats.filter(c => c !== id); }
                                                                               $wire.updateShooterCategories({{ $shooter->id }}, [...cats]);
                                                                           " />
                                                                    <span class="text-[10px] text-slate-400">{{ $cat->name }}</span>
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                    </td>
                                                @endif
                                                <td class="px-3 py-2 text-right"><flux:button size="sm" variant="ghost" class="!text-red-400 hover:!text-red-300" wire:click="deleteShooter({{ $shooter->id }})">&times;</flux:button></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-sm text-slate-500 px-3">No shooters yet.</p>
                        @endif
                        @if($addingShooterToSquadId === $squad->id)
                            <div class="rounded-lg border border-slate-600 bg-slate-700/50 p-4 space-y-3">
                                <div class="grid grid-cols-2 gap-3 {{ $divisions->isNotEmpty() ? 'sm:grid-cols-3' : '' }}">
                                    <flux:input wire:model="shooterName" label="Name" placeholder="Shooter name" required />
                                    <flux:input wire:model="shooterBib" label="Bib #" placeholder="Optional" />
                                    @if($divisions->isNotEmpty())
                                        <div>
                                            <label class="block text-sm font-medium text-slate-300 mb-1">Division</label>
                                            <select wire:model="shooterDivision" class="w-full rounded-md border border-slate-600 bg-slate-700 px-3 py-2 text-sm text-white focus:border-red-500 focus:ring-1 focus:ring-red-500">
                                                <option value="">No Division</option>
                                                @foreach($divisions as $d)
                                                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
                                </div>
                                @if($categories->isNotEmpty())
                                    <div class="col-span-full">
                                        <label class="block text-sm font-medium text-slate-300 mb-1">Categories</label>
                                        <div class="flex flex-wrap gap-3">
                                            @foreach($categories as $cat)
                                                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                                    <input type="checkbox" value="{{ $cat->id }}" wire:model="shooterCategories"
                                                           class="rounded border-slate-600 bg-slate-700 text-red-500 focus:ring-red-500 focus:ring-offset-0" />
                                                    <span class="text-sm text-slate-300">{{ $cat->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                <div class="col-span-full flex gap-2">
                                    <flux:button wire:click="addShooter" size="sm" variant="primary" class="!bg-red-600 hover:!bg-red-700">Add Shooter</flux:button>
                                    <flux:button wire:click="$set('addingShooterToSquadId', null)" size="sm" variant="ghost">Cancel</flux:button>
                                </div>
                            </div>
                        @else
                            <div class="px-3"><flux:button size="sm" variant="ghost" wire:click="startAddShooter({{ $squad->id }})">+ Add Shooter</flux:button></div>
                        @endif
                    </div>
                </div>
            @endforeach
            <div class="rounded-xl border border-dashed border-slate-600 bg-slate-800/50 p-4 space-y-3">
                <h3 class="text-sm font-medium text-slate-300">Add Squad</h3>
                <div class="flex gap-3">
                    <div class="flex-1"><flux:input wire:model="squadName" placeholder="e.g. Squad A" /></div>
                    <flux:button wire:click="addSquad" size="sm" variant="primary" class="!bg-red-600 hover:!bg-red-700 self-end">Add Squad</flux:button>
                </div>
            </div>
        </div>
    @endif
</div>
