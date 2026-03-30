<?php

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

    public function mount(Organization $organization, ShootingMatch $match): void
    {
        $this->organization = $organization;
        $this->match = $match;

        if ($match->created_by !== auth()->id() && !auth()->user()->isAdmin()) {
            abort(403, 'Only the match creator can manage squadding.');
        }
    }

    public function getTitle(): string
    {
        return 'Squadding — ' . $this->match->name;
    }

    public function randomizeRelays(): void
    {
        $match = $this->match;
        $squads = $match->squads()->orderBy('sort_order')->get();

        if ($squads->isEmpty()) {
            Flux::toast('No relays exist. Add relays on the match edit page first.', variant: 'warning');
            return;
        }

        $confirmedRegs = $match->registrations()
            ->where('payment_status', 'confirmed')
            ->with('user')
            ->get();

        $existingShooterUserIds = $match->shooters()->whereNotNull('user_id')->pluck('user_id')->toArray();
        $regsToAssign = $confirmedRegs->filter(fn ($r) => !in_array($r->user_id, $existingShooterUserIds));

        foreach ($regsToAssign as $reg) {
            $squad = $match->squads()->firstOrCreate(['name' => 'Default'], ['sort_order' => 0]);
            $maxSort = $squad->shooters()->max('sort_order') ?? 0;
            Shooter::create([
                'squad_id' => $squad->id,
                'name' => $reg->user->name,
                'user_id' => $reg->user_id,
                'sort_order' => $maxSort + 1,
            ]);
        }

        $allShooters = $match->shooters()->get();
        $riflePairs = $this->buildRiflePairs($confirmedRegs);
        $concurrentSize = max(1, $match->concurrent_relays ?? 2);
        $relayIds = $squads->pluck('id')->toArray();
        $blocks = array_chunk($relayIds, $concurrentSize);

        foreach ($squads as $squad) {
            Shooter::where('squad_id', $squad->id)->update(['sort_order' => 0]);
        }

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
        foreach ($assignments as $relayId) {
            if (isset($relayCounts[$relayId])) $relayCounts[$relayId]++;
        }

        foreach ($unpairedShooters as $shooter) {
            $minRelay = array_keys($relayCounts, min($relayCounts))[0];
            $assignments[$shooter->id] = $minRelay;
            $relayCounts[$minRelay]++;
        }

        $positionCounters = array_fill_keys($relayIds, 0);
        foreach ($assignments as $shooterId => $relayId) {
            $positionCounters[$relayId]++;
            Shooter::where('id', $shooterId)->update([
                'squad_id' => $relayId,
                'sort_order' => $positionCounters[$relayId],
            ]);
        }

        $this->match->refresh();
        Flux::toast('Relays randomized! Shared-rifle constraints respected.', variant: 'success');
    }

    private function buildRiflePairs($registrations): array
    {
        $pairs = [];
        $matched = [];

        foreach ($registrations as $reg) {
            if (!$reg->share_rifle_with || in_array($reg->user_id, $matched)) continue;

            $partnerName = trim($reg->share_rifle_with);
            $partner = $registrations->first(function ($r) use ($partnerName, $reg) {
                return $r->user_id !== $reg->user_id
                    && stripos($r->user->name, $partnerName) !== false;
            });

            if ($partner && !in_array($partner->user_id, $matched)) {
                $pairs[] = [$reg->user_id, $partner->user_id];
                $matched[] = $reg->user_id;
                $matched[] = $partner->user_id;
            }
        }

        return $pairs;
    }

    public function moveShooter(int $shooterId, int $targetSquadId): void
    {
        $shooter = Shooter::findOrFail($shooterId);
        $targetSquad = $this->match->squads()->findOrFail($targetSquadId);
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

    public function addWalkin(): void
    {
        $this->validate([
            'walkinName' => 'required|string|max:255',
            'walkinBib' => 'nullable|string|max:50',
            'walkinRelayId' => 'required|integer',
        ]);

        $squad = $this->match->squads()->findOrFail($this->walkinRelayId);
        $maxSort = Shooter::where('squad_id', $squad->id)->max('sort_order') ?? 0;

        Shooter::create([
            'squad_id' => $squad->id,
            'name' => $this->walkinName,
            'bib_number' => $this->walkinBib ?: null,
            'sort_order' => $maxSort + 1,
        ]);

        $this->reset('walkinName', 'walkinBib', 'walkinRelayId');
        Flux::toast('Walk-in shooter added.', variant: 'success');
    }

    public function with(): array
    {
        $squads = $this->match->squads()
            ->with(['shooters' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $realSquads = $squads->filter(fn ($s) => !in_array($s->name, ['Default', 'Unassigned']));
        $unassignedSquads = $squads->filter(fn ($s) => in_array($s->name, ['Default', 'Unassigned']));
        $unassignedShooters = $unassignedSquads->flatMap(fn ($s) => $s->shooters);

        $confirmedCount = $this->match->registrations()->where('payment_status', 'confirmed')->count();

        $shareMap = [];
        $regs = $this->match->registrations()->where('payment_status', 'confirmed')->whereNotNull('share_rifle_with')->with('user')->get();
        foreach ($regs as $reg) {
            $shareMap[$reg->user_id] = $reg->share_rifle_with;
        }

        $concurrentSize = max(1, $this->match->concurrent_relays ?? 2);

        return [
            'squads' => $realSquads,
            'unassignedShooters' => $unassignedShooters,
            'confirmedCount' => $confirmedCount,
            'shareMap' => $shareMap,
            'concurrentSize' => $concurrentSize,
        ];
    }
}; ?>

<div class="space-y-6 max-w-6xl">
    {{-- Header --}}
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
            <span class="text-xs text-muted">{{ $concurrentSize }} relays shoot at once</span>
            <flux:button wire:click="randomizeRelays" variant="primary" class="!bg-accent hover:!bg-accent-hover"
                         wire:confirm="Randomize all shooters into relays? Existing assignments will be reshuffled.">
                Randomize Relays
            </flux:button>
        </div>
    </div>

    {{-- Concurrent group legend --}}
    <div class="flex flex-wrap items-center gap-2 text-xs text-muted">
        <span class="font-medium text-secondary">Concurrent groups:</span>
        @php $colors = ['bg-blue-500/20 text-blue-400', 'bg-emerald-500/20 text-emerald-400', 'bg-amber-500/20 text-amber-400', 'bg-purple-500/20 text-purple-400', 'bg-pink-500/20 text-pink-400']; @endphp
        @foreach($squads->chunk($concurrentSize) as $groupIndex => $group)
            <span class="rounded px-2 py-0.5 {{ $colors[$groupIndex % count($colors)] }}">
                {{ $group->pluck('name')->join(' + ') }}
            </span>
        @endforeach
    </div>

    {{-- Relay Grid --}}
    @if($squads->isNotEmpty())
        <div class="space-y-3">
            @php $relayCounter = 0; @endphp
            @foreach($squads as $squad)
                @php
                    $groupIndex = intdiv($relayCounter, $concurrentSize);
                    $groupColor = $colors[$groupIndex % count($colors)];
                    $relayCounter++;
                @endphp
                <div class="rounded-xl border border-border bg-surface overflow-hidden" wire:key="sq-{{ $squad->id }}">
                    <div class="flex items-center justify-between border-b border-border px-4 py-2">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-primary">{{ $squad->name }}</span>
                            <span class="rounded px-1.5 py-0.5 text-[10px] {{ $groupColor }}">Group {{ $groupIndex + 1 }}</span>
                            <span class="text-xs text-muted">({{ $squad->shooters->count() }} shooters)</span>
                        </div>
                    </div>
                    <div class="p-3">
                        @if($squad->shooters->isNotEmpty())
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-muted border-b border-border/50">
                                            <th class="px-2 py-1.5 font-medium w-10">#</th>
                                            <th class="px-2 py-1.5 font-medium">Shooter</th>
                                            <th class="px-2 py-1.5 font-medium w-32">Shares rifle</th>
                                            <th class="px-2 py-1.5 font-medium w-24 text-right">Actions</th>
                                        </tr>
                                    </thead>
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
                                                                <button @click="open = !open" class="rounded px-1.5 py-0.5 text-xs text-muted hover:text-secondary transition-colors" title="Move to relay">&#8644;</button>
                                                                <div x-show="open" x-transition class="absolute right-0 z-10 mt-1 w-40 rounded-lg border border-border bg-surface-2 py-1 shadow-lg">
                                                                    @foreach($squads->where('id', '!=', $squad->id) as $otherSquad)
                                                                        <button wire:click="moveShooter({{ $shooter->id }}, {{ $otherSquad->id }})" @click="open = false"
                                                                                class="block w-full px-3 py-1.5 text-left text-xs text-secondary hover:bg-surface hover:text-white transition-colors">{{ $otherSquad->name }}</button>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endif
                                                        <button wire:click="removeFromRelay({{ $shooter->id }})" class="rounded px-1 py-0.5 text-xs text-accent/60 hover:text-accent transition-colors" title="Remove from relay">&times;</button>
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
            <p class="text-muted">No relays set up yet. Add relays on the <a href="{{ route('org.matches.edit', [$organization, $match]) }}" class="text-accent hover:underline">match edit page</a> first.</p>
        </div>
    @endif

    {{-- Unassigned Shooters --}}
    @if($unassignedShooters->isNotEmpty())
        <div class="rounded-xl border border-amber-500/30 bg-amber-900/10 p-4 space-y-3">
            <h3 class="text-sm font-semibold text-amber-400">Unassigned Shooters ({{ $unassignedShooters->count() }})</h3>
            <div class="flex flex-wrap gap-2">
                @foreach($unassignedShooters as $shooter)
                    <div class="flex items-center gap-1 rounded-lg border border-border bg-surface px-3 py-1.5" wire:key="un-{{ $shooter->id }}">
                        <span class="text-sm text-secondary">{{ $shooter->name }}</span>
                        @if(isset($shareMap[$shooter->user_id]))
                            <span class="rounded bg-amber-600/20 px-1 py-0.5 text-[9px] text-amber-400">shares</span>
                        @endif
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

    {{-- Add Walk-in Shooter --}}
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
                <label class="block text-xs text-muted mb-1">Relay</label>
                <select wire:model="walkinRelayId"
                        class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-red-500 focus:ring-1 focus:ring-red-500">
                    <option value="">Select relay</option>
                    @foreach($squads as $sq)
                        <option value="{{ $sq->id }}">{{ $sq->name }}</option>
                    @endforeach
                </select>
            </div>
            <flux:button wire:click="addWalkin" size="sm" variant="primary" class="!bg-accent hover:!bg-accent-hover">
                Add
            </flux:button>
        </div>
    </div>
</div>
