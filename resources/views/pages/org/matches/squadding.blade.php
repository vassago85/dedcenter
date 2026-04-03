<?php

use App\Enums\MatchStatus;
use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\MatchRegistration;
use App\Models\Squad;
use App\Models\Shooter;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    class extends Component {
    public Organization $organization;
    public ShootingMatch $match;

    public string $walkinName = '';
    public string $walkinBib = '';
    public ?int $walkinRelayId = null;

    public string $newSquadName = '';
    public ?int $defaultCapacity = null;

    public function mount(Organization $organization, ShootingMatch $match): void
    {
        $this->organization = $organization;
        $this->match = $match;
        $this->defaultCapacity = $match->max_squad_size;

        if (! $match->userCanManageSquadding(auth()->user())) {
            abort(403, 'You are not authorized to manage squadding for this match.');
        }
    }

    public function getTitle(): string
    {
        return 'Squadding — ' . $this->match->name;
    }

    public function updateDefaultCapacity(): void
    {
        $val = $this->defaultCapacity && $this->defaultCapacity > 0 ? (int) $this->defaultCapacity : null;
        $this->match->update(['max_squad_size' => $val]);
        Flux::toast($val ? "Default max squad size set to {$val}." : 'Default capacity removed (unlimited).', variant: 'success');
    }

    public function updateSquadCapacity(int $squadId, $value): void
    {
        $squad = $this->match->squads()->findOrFail($squadId);
        $cap = $value && (int) $value > 0 ? (int) $value : null;
        $squad->update(['max_capacity' => $cap]);
        Flux::toast("Capacity for {$squad->name} updated.", variant: 'success');
    }

    public function addSquad(): void
    {
        $this->validate(['newSquadName' => 'required|string|max:255']);
        $maxSort = $this->match->squads()->max('sort_order') ?? 0;
        $this->match->squads()->create([
            'name' => $this->newSquadName,
            'sort_order' => $maxSort + 1,
        ]);
        $this->reset('newSquadName');
        Flux::toast('Squad added.', variant: 'success');
    }

    public function deleteSquad(int $id): void
    {
        $squad = $this->match->squads()->findOrFail($id);
        $squad->shooters()->delete();
        $squad->delete();
        Flux::toast("Squad {$squad->name} deleted.", variant: 'success');
    }

    public function openSquadding(): void
    {
        if ($this->match->status->canTransitionTo(MatchStatus::SquaddingOpen)) {
            $this->match->update(['status' => MatchStatus::SquaddingOpen]);
            Flux::toast('Squadding is now open for shooters.', variant: 'success');
        }
    }

    public function closeSquadding(): void
    {
        if ($this->match->status === MatchStatus::SquaddingOpen) {
            $this->match->update(['status' => MatchStatus::Active]);
            Flux::toast('Squadding closed. Match is now active.', variant: 'success');
        }
    }

    public function randomizeRelays(): void
    {
        $match = $this->match;
        $squads = $match->squads()->orderBy('sort_order')->get();

        if ($squads->isEmpty()) {
            Flux::toast('No squads exist. Add squads first.', variant: 'warning');
            return;
        }

        $confirmedRegs = $match->registrations()->where('payment_status', 'confirmed')->with('user')->get();
        $existingShooterUserIds = $match->shooters()->whereNotNull('user_id')->pluck('user_id')->toArray();
        $regsToAssign = $confirmedRegs->filter(fn ($r) => !in_array($r->user_id, $existingShooterUserIds));

        foreach ($regsToAssign as $reg) {
            $squad = $match->squads()->firstOrCreate(['name' => 'Default'], ['sort_order' => 0]);
            $maxSort = $squad->shooters()->max('sort_order') ?? 0;
            Shooter::create(['squad_id' => $squad->id, 'name' => $reg->user->name, 'user_id' => $reg->user_id, 'sort_order' => $maxSort + 1]);
        }

        $allShooters = $match->shooters()->get();
        $riflePairs = $this->buildRiflePairs($confirmedRegs);
        $concurrentSize = max(1, $match->concurrent_relays ?? 2);
        $relayIds = $squads->pluck('id')->toArray();
        $blocks = array_chunk($relayIds, $concurrentSize);

        foreach ($squads as $squad) { Shooter::where('squad_id', $squad->id)->update(['sort_order' => 0]); }

        $shooterList = $allShooters->values();
        $pairedShooterIds = [];
        $assignments = [];

        foreach ($riflePairs as $pair) {
            $shooterA = $shooterList->first(fn ($s) => $s->user_id === $pair[0]);
            $shooterB = $shooterList->first(fn ($s) => $s->user_id === $pair[1]);
            if (!$shooterA || !$shooterB) continue;
            $blockIndexA = array_rand($blocks);
            $availableBlocks = array_keys(array_filter($blocks, fn ($_, $i) => $i !== $blockIndexA, ARRAY_FILTER_USE_BOTH));
            $blockIndexB = empty($availableBlocks) ? $blockIndexA : $availableBlocks[array_rand($availableBlocks)];
            $assignments[$shooterA->id] = $blocks[$blockIndexA][array_rand($blocks[$blockIndexA])];
            $assignments[$shooterB->id] = $blocks[$blockIndexB][array_rand($blocks[$blockIndexB])];
            $pairedShooterIds[] = $shooterA->id;
            $pairedShooterIds[] = $shooterB->id;
        }

        $unpairedShooters = $shooterList->filter(fn ($s) => !in_array($s->id, $pairedShooterIds))->shuffle();
        $relayCounts = array_fill_keys($relayIds, 0);
        foreach ($assignments as $relayId) { if (isset($relayCounts[$relayId])) $relayCounts[$relayId]++; }
        foreach ($unpairedShooters as $shooter) {
            $minRelay = array_keys($relayCounts, min($relayCounts))[0];
            $assignments[$shooter->id] = $minRelay;
            $relayCounts[$minRelay]++;
        }

        $positionCounters = array_fill_keys($relayIds, 0);
        foreach ($assignments as $shooterId => $relayId) {
            $positionCounters[$relayId]++;
            Shooter::where('id', $shooterId)->update(['squad_id' => $relayId, 'sort_order' => $positionCounters[$relayId]]);
        }

        $this->match->refresh();
        Flux::toast('Relays randomized! Shared-rifle constraints respected.', variant: 'success');
    }

    private function buildRiflePairs($registrations): array
    {
        $pairs = []; $matched = [];
        foreach ($registrations as $reg) {
            if (!$reg->share_rifle_with || in_array($reg->user_id, $matched)) continue;
            $partnerName = trim($reg->share_rifle_with);
            $partner = $registrations->first(fn ($r) => $r->user_id !== $reg->user_id && stripos($r->user->name, $partnerName) !== false);
            if ($partner && !in_array($partner->user_id, $matched)) {
                $pairs[] = [$reg->user_id, $partner->user_id];
                $matched[] = $reg->user_id;
                $matched[] = $partner->user_id;
            }
        }
        return $pairs;
    }

    public function autoSquad(): void
    {
        $match = $this->match;
        $squads = $match->squads()->orderBy('sort_order')->get()
            ->reject(fn ($s) => in_array($s->name, ['Default', 'Unassigned']));

        if ($squads->isEmpty()) {
            Flux::toast('No squads exist. Add squads first.', variant: 'warning');
            return;
        }

        $confirmedRegs = $match->registrations()->where('payment_status', 'confirmed')->with('user')->get();
        $existingShooterUserIds = $match->shooters()->whereNotNull('user_id')->pluck('user_id')->toArray();
        $regsToAssign = $confirmedRegs->filter(fn ($r) => !in_array($r->user_id, $existingShooterUserIds));

        $defaultSquad = $match->squads()->where('name', 'Default')->first()
            ?? $match->squads()->where('name', 'Unassigned')->first();

        $unassignedShooters = $defaultSquad
            ? Shooter::where('squad_id', $defaultSquad->id)->get()
            : collect();

        foreach ($regsToAssign as $reg) {
            $holder = $match->squads()->firstOrCreate(['name' => 'Unassigned'], ['sort_order' => 999]);
            $maxSort = Shooter::where('squad_id', $holder->id)->max('sort_order') ?? 0;
            $newShooter = Shooter::create([
                'squad_id' => $holder->id,
                'name' => $reg->user->name,
                'user_id' => $reg->user_id,
                'sort_order' => $maxSort + 1,
            ]);
            $unassignedShooters->push($newShooter);
        }

        if ($unassignedShooters->isEmpty()) {
            Flux::toast('No unassigned shooters to distribute.', variant: 'warning');
            return;
        }

        $squadIds = $squads->pluck('id')->toArray();
        $counts = [];
        foreach ($squadIds as $sid) {
            $counts[$sid] = Shooter::where('squad_id', $sid)->count();
        }

        foreach ($unassignedShooters as $shooter) {
            $targetId = null;
            $minCount = PHP_INT_MAX;
            foreach ($squadIds as $sid) {
                $squad = $squads->firstWhere('id', $sid);
                $cap = $squad->effectiveCapacity();
                if ($cap !== null && $counts[$sid] >= $cap) continue;
                if ($counts[$sid] < $minCount) {
                    $minCount = $counts[$sid];
                    $targetId = $sid;
                }
            }

            if (!$targetId) continue;

            $counts[$targetId]++;
            $maxSort = Shooter::where('squad_id', $targetId)->max('sort_order') ?? 0;
            $shooter->update(['squad_id' => $targetId, 'sort_order' => $maxSort + 1]);
        }

        $this->match->refresh();
        Flux::toast('Unassigned shooters distributed across squads.', variant: 'success');
    }

    public function moveShooter(int $shooterId, int $targetSquadId): void
    {
        $shooter = Shooter::findOrFail($shooterId);
        $targetSquad = $this->match->squads()->findOrFail($targetSquadId);
        if ($targetSquad->isFull()) { Flux::toast("{$targetSquad->name} is full.", variant: 'danger'); return; }
        $maxSort = Shooter::where('squad_id', $targetSquadId)->max('sort_order') ?? 0;
        $shooter->update(['squad_id' => $targetSquadId, 'sort_order' => $maxSort + 1]);
        Flux::toast("Moved {$shooter->name} to {$targetSquad->name}.", variant: 'success');
    }

    public function removeFromRelay(int $shooterId): void
    {
        $shooter = Shooter::findOrFail($shooterId);
        $defaultSquad = $this->match->squads()->firstOrCreate(['name' => 'Unassigned'], ['sort_order' => 999]);
        $maxSort = Shooter::where('squad_id', $defaultSquad->id)->max('sort_order') ?? 0;
        $shooter->update(['squad_id' => $defaultSquad->id, 'sort_order' => $maxSort + 1]);
        Flux::toast("{$shooter->name} moved to unassigned.", variant: 'success');
    }

    public function assignToTeam(int $shooterId, int $teamId): void
    {
        $shooter = Shooter::findOrFail($shooterId);
        $team = $this->match->teams()->findOrFail($teamId);
        if ($team->isFull()) {
            Flux::toast("{$team->name} is full.", variant: 'danger');
            return;
        }
        $shooter->update(['team_id' => $teamId]);
        Flux::toast("{$shooter->name} assigned to {$team->name}.", variant: 'success');
    }

    public function removeFromTeam(int $shooterId): void
    {
        $shooter = Shooter::findOrFail($shooterId);
        $shooter->update(['team_id' => null]);
        Flux::toast("{$shooter->name} removed from team.", variant: 'success');
    }

    public function autoTeam(): void
    {
        $teams = $this->match->teams()->withCount('shooters')->orderBy('sort_order')->get();
        if ($teams->isEmpty()) {
            Flux::toast('No teams exist. Add teams in match settings first.', variant: 'warning');
            return;
        }

        $unassigned = $this->match->shooters()->whereNull('team_id')->active()->get();
        if ($unassigned->isEmpty()) {
            Flux::toast('No unassigned shooters to distribute.', variant: 'warning');
            return;
        }

        $counts = $teams->pluck('shooters_count', 'id')->toArray();
        foreach ($unassigned as $shooter) {
            $targetId = null;
            $minCount = PHP_INT_MAX;
            foreach ($teams as $team) {
                $max = $team->effectiveMaxSize();
                if ($counts[$team->id] >= $max) continue;
                if ($counts[$team->id] < $minCount) {
                    $minCount = $counts[$team->id];
                    $targetId = $team->id;
                }
            }
            if (!$targetId) continue;
            $shooter->update(['team_id' => $targetId]);
            $counts[$targetId]++;
        }

        $this->match->refresh();
        Flux::toast('Shooters distributed into teams.', variant: 'success');
    }

    public function addWalkin(): void
    {
        $this->validate(['walkinName' => 'required|string|max:255', 'walkinBib' => 'nullable|string|max:50', 'walkinRelayId' => 'required|integer']);
        $squad = $this->match->squads()->findOrFail($this->walkinRelayId);
        if ($squad->isFull()) { Flux::toast("{$squad->name} is full.", variant: 'danger'); return; }
        $maxSort = Shooter::where('squad_id', $squad->id)->max('sort_order') ?? 0;
        Shooter::create(['squad_id' => $squad->id, 'name' => $this->walkinName, 'bib_number' => $this->walkinBib ?: null, 'sort_order' => $maxSort + 1]);
        $this->reset('walkinName', 'walkinBib', 'walkinRelayId');
        Flux::toast('Walk-in shooter added.', variant: 'success');
    }

    public function with(): array
    {
        $squads = $this->match->squads()->with(['shooters' => fn ($q) => $q->orderBy('sort_order')])->orderBy('sort_order')->get();
        $realSquads = $squads->filter(fn ($s) => !in_array($s->name, ['Default', 'Unassigned']));
        $unassignedSquads = $squads->filter(fn ($s) => in_array($s->name, ['Default', 'Unassigned']));
        $unassignedShooters = $unassignedSquads->flatMap(fn ($s) => $s->shooters);
        $confirmedCount = $this->match->registrations()->where('payment_status', 'confirmed')->count();
        $shareMap = [];
        $regs = $this->match->registrations()->where('payment_status', 'confirmed')->whereNotNull('share_rifle_with')->with('user')->get();
        foreach ($regs as $reg) { $shareMap[$reg->user_id] = $reg->share_rifle_with; }
        $concurrentSize = max(1, $this->match->concurrent_relays ?? 2);

        $teams = $this->match->isTeamEvent()
            ? $this->match->teams()->with(['shooters' => fn ($q) => $q->orderBy('sort_order')])->withCount('shooters')->orderBy('sort_order')->get()
            : collect();

        return [
            'squads' => $realSquads,
            'allSquads' => $squads,
            'unassignedShooters' => $unassignedShooters,
            'confirmedCount' => $confirmedCount,
            'shareMap' => $shareMap,
            'concurrentSize' => $concurrentSize,
            'teams' => $teams,
            'isTeamEvent' => $this->match->isTeamEvent(),
        ];
    }
}; ?>

<div class="space-y-6 max-w-6xl">
    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('org.matches.edit', [$organization, $match]) }}" variant="ghost" size="sm">
                <svg class="mr-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                Back
            </flux:button>
            <div>
                <flux:heading size="xl">Squadding</flux:heading>
                <p class="mt-1 text-sm text-muted">{{ $match->name }} &mdash; {{ $confirmedCount }} confirmed registrations</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            @if($match->status->canTransitionTo(MatchStatus::SquaddingOpen))
                <flux:button wire:click="openSquadding" variant="primary" class="!bg-indigo-600 hover:!bg-indigo-700" wire:confirm="Open squadding to shooters?">Open Squadding</flux:button>
            @elseif($match->status === MatchStatus::SquaddingOpen)
                <flux:badge color="indigo" size="sm">Squadding Open</flux:badge>
                <flux:button wire:click="closeSquadding" variant="ghost" wire:confirm="Close squadding and activate match?">Close &amp; Activate</flux:button>
            @endif
            <flux:button wire:click="autoSquad" variant="primary" class="!bg-indigo-600 hover:!bg-indigo-700"
                         wire:confirm="Auto-assign all unassigned registrants across squads (round-robin)?">Auto-Squad</flux:button>
            <flux:button wire:click="randomizeRelays" variant="primary" class="!bg-accent hover:!bg-accent-hover"
                         wire:confirm="Randomize all shooters into relays? Existing assignments will be reshuffled.">Randomize Relays</flux:button>
        </div>
    </div>

    {{-- Capacity settings --}}
    <div class="rounded-xl border border-border bg-surface p-4 space-y-3">
        <h3 class="text-sm font-semibold text-primary">Squad Capacity</h3>
        <div class="flex items-end gap-4">
            <div>
                <label class="block text-xs text-muted mb-1">Default Max per Squad</label>
                <input type="number" wire:model="defaultCapacity" min="1" placeholder="Unlimited"
                       class="w-28 rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
            </div>
            <flux:button wire:click="updateDefaultCapacity" size="sm" variant="ghost">Save Default</flux:button>
        </div>
        <p class="text-[10px] text-muted">Individual squads can override this. Leave blank for unlimited.</p>
    </div>

    {{-- Concurrent group legend --}}
    <div class="flex flex-wrap items-center gap-2 text-xs text-muted">
        <span class="font-medium text-secondary">Concurrent groups:</span>
        @php $colors = ['bg-blue-500/20 text-blue-400', 'bg-emerald-500/20 text-emerald-400', 'bg-amber-500/20 text-amber-400', 'bg-purple-500/20 text-purple-400', 'bg-pink-500/20 text-pink-400']; @endphp
        @foreach($squads->chunk($concurrentSize) as $groupIndex => $group)
            <span class="rounded px-2 py-0.5 {{ $colors[$groupIndex % count($colors)] }}">{{ $group->pluck('name')->join(' + ') }}</span>
        @endforeach
    </div>

    @if($squads->isNotEmpty())
        <div class="space-y-3">
            @php $relayCounter = 0; @endphp
            @foreach($squads as $squad)
                @php
                    $groupIndex = intdiv($relayCounter, $concurrentSize);
                    $groupColor = $colors[$groupIndex % count($colors)];
                    $relayCounter++;
                    $cap = $squad->effectiveCapacity();
                    $remaining = $squad->spotsRemaining();
                @endphp
                <div class="rounded-xl border border-border bg-surface overflow-hidden" wire:key="sq-{{ $squad->id }}">
                    <div class="flex items-center justify-between border-b border-border px-4 py-2">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-primary">{{ $squad->name }}</span>
                            <span class="rounded px-1.5 py-0.5 text-[10px] {{ $groupColor }}">Group {{ $groupIndex + 1 }}</span>
                            <span class="text-xs text-muted">
                                {{ $squad->shooters->count() }}{{ $cap ? "/{$cap}" : '' }} shooters
                                @if($remaining !== null && $remaining <= 0)
                                    <span class="text-accent font-bold ml-1">FULL</span>
                                @elseif($remaining !== null)
                                    <span class="text-green-400 ml-1">({{ $remaining }} spots left)</span>
                                @endif
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="number" min="1" placeholder="Cap" value="{{ $squad->max_capacity }}"
                                   wire:change="updateSquadCapacity({{ $squad->id }}, $event.target.value)"
                                   class="w-16 rounded border border-border bg-surface-2 px-2 py-1 text-xs text-primary text-center focus:border-red-500" title="Max capacity override" />
                            <button wire:click="deleteSquad({{ $squad->id }})" wire:confirm="Delete {{ $squad->name }} and all its shooters?"
                                    class="text-xs text-accent/60 hover:text-accent transition-colors">&times;</button>
                        </div>
                    </div>
                    <div class="p-3">
                        @if($squad->shooters->isNotEmpty())
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead><tr class="text-left text-muted border-b border-border/50"><th class="px-2 py-1.5 font-medium w-10">#</th><th class="px-2 py-1.5 font-medium">Shooter</th><th class="px-2 py-1.5 font-medium w-32">Shares rifle</th><th class="px-2 py-1.5 font-medium w-24 text-right">Actions</th></tr></thead>
                                    <tbody class="divide-y divide-border/30">
                                        @foreach($squad->shooters as $shooter)
                                            <tr wire:key="sh-{{ $shooter->id }}">
                                                <td class="px-2 py-1.5 text-muted font-mono text-xs">{{ $shooter->sort_order }}</td>
                                                <td class="px-2 py-1.5 text-secondary">{{ $shooter->name }}</td>
                                                <td class="px-2 py-1.5">
                                                    @if(isset($shareMap[$shooter->user_id]))
                                                        <span class="rounded bg-amber-600/20 px-1.5 py-0.5 text-[10px] text-amber-400">{{ $shareMap[$shooter->user_id] }}</span>
                                                    @else
                                                        <span class="text-muted text-xs">&mdash;</span>
                                                    @endif
                                                </td>
                                                <td class="px-2 py-1.5 text-right">
                                                    <div class="flex items-center justify-end gap-1" x-data="{ open: false }">
                                                        @if($squads->count() > 1)
                                                            <div class="relative" @click.away="open = false">
                                                                <button @click="open = !open" class="rounded px-1.5 py-0.5 text-xs text-muted hover:text-secondary transition-colors" title="Move to squad">&#8644;</button>
                                                                <div x-show="open" x-transition class="absolute right-0 z-10 mt-1 w-40 rounded-lg border border-border bg-surface-2 py-1 shadow-lg">
                                                                    @foreach($squads->where('id', '!=', $squad->id) as $otherSquad)
                                                                        <button wire:click="moveShooter({{ $shooter->id }}, {{ $otherSquad->id }})" @click="open = false"
                                                                                class="block w-full px-3 py-1.5 text-left text-xs text-secondary hover:bg-surface hover:text-white transition-colors">{{ $otherSquad->name }}</button>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endif
                                                        <button wire:click="removeFromRelay({{ $shooter->id }})" class="rounded px-1 py-0.5 text-xs text-accent/60 hover:text-accent transition-colors" title="Remove">&times;</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-sm text-muted px-2">No shooters assigned.</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="rounded-xl border border-dashed border-border bg-surface/50 p-8 text-center">
            <p class="text-muted">No squads set up yet. Add a squad below.</p>
        </div>
    @endif

    @if($unassignedShooters->isNotEmpty())
        <div class="rounded-xl border border-amber-500/30 bg-amber-900/10 p-4 space-y-3">
            <h3 class="text-sm font-semibold text-amber-400">Unassigned Shooters ({{ $unassignedShooters->count() }})</h3>
            <div class="flex flex-wrap gap-2">
                @foreach($unassignedShooters as $shooter)
                    <div class="flex items-center gap-1 rounded-lg border border-border bg-surface px-3 py-1.5" wire:key="un-{{ $shooter->id }}">
                        <span class="text-sm text-secondary">{{ $shooter->name }}</span>
                        <div class="relative ml-1" x-data="{ open: false }" @click.away="open = false">
                            <button @click="open = !open" class="text-xs text-muted hover:text-secondary">&#8644;</button>
                            <div x-show="open" x-transition class="absolute left-0 z-10 mt-1 w-40 rounded-lg border border-border bg-surface-2 py-1 shadow-lg">
                                @foreach($squads as $sq)
                                    <button wire:click="moveShooter({{ $shooter->id }}, {{ $sq->id }})" @click="open = false"
                                            class="block w-full px-3 py-1.5 text-left text-xs text-secondary hover:bg-surface hover:text-white transition-colors">{{ $sq->name }}</button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Team Assignment --}}
    @if($isTeamEvent)
        <div class="rounded-xl border border-indigo-500/30 bg-surface p-6 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-primary">Team Assignment</h2>
                    <p class="text-sm text-muted">Assign shooters to teams. Team size: {{ $match->team_size }}</p>
                </div>
                <flux:button wire:click="autoTeam" variant="primary" class="!bg-indigo-600 hover:!bg-indigo-700" size="sm"
                             wire:confirm="Auto-assign all unassigned shooters to teams (round-robin)?">Auto-Team</flux:button>
            </div>

            @if($teams->isNotEmpty())
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($teams as $team)
                        <div class="rounded-xl border border-border bg-surface-2/40 p-4 space-y-3" wire:key="team-{{ $team->id }}">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-bold text-primary">{{ $team->name }}</h3>
                                <span class="text-xs text-muted">{{ $team->shooters_count }}/{{ $team->effectiveMaxSize() }}</span>
                            </div>
                            @if($team->shooters->isNotEmpty())
                                <ul class="space-y-1">
                                    @foreach($team->shooters as $ts)
                                        <li class="flex items-center justify-between text-sm text-secondary">
                                            <span>{{ $ts->name }}</span>
                                            <button wire:click="removeFromTeam({{ $ts->id }})" class="text-xs text-accent/60 hover:text-accent">&times;</button>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-xs text-muted">No members yet</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-muted">No teams set up yet. Add teams in match settings.</p>
            @endif

            @php
                $unteamedShooters = $match->shooters()->whereNull('team_id')->active()->get();
            @endphp
            @if($unteamedShooters->isNotEmpty() && $teams->isNotEmpty())
                <div class="border-t border-border pt-4 space-y-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-muted">Unassigned to Teams ({{ $unteamedShooters->count() }})</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($unteamedShooters as $us)
                            <div class="flex items-center gap-1 rounded-lg border border-border bg-surface px-3 py-1.5" wire:key="ut-{{ $us->id }}" x-data="{ tOpen: false }" @click.away="tOpen = false">
                                <span class="text-sm text-secondary">{{ $us->name }}</span>
                                <div class="relative ml-1">
                                    <button @click="tOpen = !tOpen" class="text-xs text-muted hover:text-secondary">&#8644;</button>
                                    <div x-show="tOpen" x-transition class="absolute left-0 z-10 mt-1 w-40 rounded-lg border border-border bg-surface-2 py-1 shadow-lg">
                                        @foreach($teams as $tm)
                                            @if(!$tm->isFull())
                                                <button wire:click="assignToTeam({{ $us->id }}, {{ $tm->id }})" @click="tOpen = false"
                                                        class="block w-full px-3 py-1.5 text-left text-xs text-secondary hover:bg-surface hover:text-white transition-colors">{{ $tm->name }}</button>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="rounded-xl border border-dashed border-border bg-surface/50 p-4 space-y-3">
        <h3 class="text-sm font-medium text-secondary">Add Squad</h3>
        <div class="flex gap-3 items-end">
            <div class="flex-1"><flux:input wire:model="newSquadName" placeholder="e.g. Squad A" /></div>
            <flux:button wire:click="addSquad" size="sm" variant="primary" class="!bg-accent hover:!bg-accent-hover">Add Squad</flux:button>
        </div>
    </div>

    <div class="rounded-xl border border-dashed border-border bg-surface/50 p-4 space-y-3">
        <h3 class="text-sm font-medium text-secondary">Add Walk-in Shooter</h3>
        <div class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[150px]">
                <label class="block text-xs text-muted mb-1">Name</label>
                <input type="text" wire:model="walkinName" placeholder="Shooter name"
                       class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
            </div>
            <div class="w-24">
                <label class="block text-xs text-muted mb-1">Bib #</label>
                <input type="text" wire:model="walkinBib" placeholder="Opt."
                       class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
            </div>
            <div class="w-40">
                <label class="block text-xs text-muted mb-1">Squad</label>
                <select wire:model="walkinRelayId"
                        class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-red-500 focus:ring-1 focus:ring-red-500">
                    <option value="">Select squad</option>
                    @foreach($allSquads as $sq)
                        <option value="{{ $sq->id }}">{{ $sq->name }}</option>
                    @endforeach
                </select>
            </div>
            <flux:button wire:click="addWalkin" size="sm" variant="primary" class="!bg-accent hover:!bg-accent-hover">Add</flux:button>
        </div>
    </div>
</div>
