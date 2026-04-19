<?php

use App\Enums\MatchStatus;
use App\Models\ShootingMatch;
use App\Models\MatchRegistration;
use App\Models\Squad;
use App\Models\Shooter;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    class extends Component {
    public ShootingMatch $match;

    public string $walkinName = '';
    public string $walkinBib = '';
    public string $walkinCaliber = '';
    public ?int $walkinRelayId = null;
    public string $walkinSearch = '';
    public ?int $walkinUserId = null;

    public string $newSquadName = '';
    public ?int $defaultCapacity = null;

    public string $activeTab = 'squads';

    public int $autoRelayMax = 10;

    public function mount(ShootingMatch $match): void
    {
        $this->match = $match;
        $this->defaultCapacity = $match->max_squad_size;
        $this->autoRelayMax = $match->max_squad_size ?? 10;
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

    public function autoSetupRelays(): void
    {
        $match = $this->match;
        $max = max(1, $this->autoRelayMax);

        $confirmedRegs = $match->registrations()->where('payment_status', 'confirmed')->with('user')->get();
        $existingShooterUserIds = $match->shooters()->whereNotNull('user_id')->pluck('user_id')->toArray();

        foreach ($confirmedRegs as $reg) {
            if (in_array($reg->user_id, $existingShooterUserIds)) continue;
            $holder = $match->squads()->firstOrCreate(['name' => 'Unassigned'], ['sort_order' => 999]);
            Shooter::create([
                'squad_id' => $holder->id,
                'name' => $reg->user->name,
                'user_id' => $reg->user_id,
                'sort_order' => Shooter::where('squad_id', $holder->id)->max('sort_order') + 1,
            ]);
        }

        $allShooters = $match->shooters()->get();
        $shooterCount = $allShooters->count();

        if ($shooterCount === 0) {
            Flux::toast('No shooters to assign.', variant: 'warning');
            return;
        }

        $match->update(['max_squad_size' => $max]);
        $this->defaultCapacity = $max;

        $relayCount = (int) ceil($shooterCount / $max);

        $match->squads()->whereDoesntHave('shooters')->delete();

        $existingSquads = $match->squads()->orderBy('sort_order')->get()
            ->reject(fn ($s) => in_array($s->name, ['Default', 'Unassigned']));
        $maxSort = $match->squads()->max('sort_order') ?? 0;

        $relays = collect();
        for ($i = 1; $i <= $relayCount; $i++) {
            $existing = $existingSquads->firstWhere('name', "Relay {$i}");
            if ($existing) {
                $relays->push($existing);
            } else {
                $maxSort++;
                $relays->push($match->squads()->create(['name' => "Relay {$i}", 'sort_order' => $maxSort, 'max_capacity' => $max]));
            }
        }

        $shuffled = $allShooters->shuffle()->values();
        $relayCounts = $relays->mapWithKeys(fn ($r) => [$r->id => 0])->toArray();

        foreach ($shuffled as $shooter) {
            $targetId = array_keys($relayCounts, min($relayCounts))[0];
            $relayCounts[$targetId]++;
            $shooter->update(['squad_id' => $targetId, 'sort_order' => $relayCounts[$targetId]]);
        }

        $match->squads()->whereIn('name', ['Default', 'Unassigned'])->whereDoesntHave('shooters')->delete();

        $this->match->refresh();
        Flux::toast("Created {$relayCount} relays for {$shooterCount} shooters (max {$max} per relay).", variant: 'success');
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

    public function randomizeRelays(): void
    {
        $match = $this->match;
        $squads = $match->squads()->orderBy('sort_order')->get();

        if ($squads->isEmpty()) {
            Flux::toast('No relays exist. Add squads first.', variant: 'warning');
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

    public function moveShooter(int $shooterId, int $targetSquadId): void
    {
        $shooter = Shooter::findOrFail($shooterId);
        $targetSquad = $this->match->squads()->findOrFail($targetSquadId);
        if ($targetSquad->isFull()) { Flux::toast("{$targetSquad->name} is full.", variant: 'danger'); return; }
        $maxSort = Shooter::where('squad_id', $targetSquadId)->max('sort_order') ?? 0;
        $shooter->update(['squad_id' => $targetSquadId, 'sort_order' => $maxSort + 1]);
        Flux::toast("Moved {$shooter->name} to {$targetSquad->name}.", variant: 'success');
    }

    /** Swap with the immediate neighbour above them in the same squad. */
    public function moveShooterUp(int $shooterId): void
    {
        $shooter = Shooter::findOrFail($shooterId);
        if (! $this->match->squads()->whereKey($shooter->squad_id)->exists()) { return; }

        $neighbour = Shooter::where('squad_id', $shooter->squad_id)
            ->where('sort_order', '<', $shooter->sort_order)
            ->orderByDesc('sort_order')
            ->first();
        if (! $neighbour) { return; }

        DB::transaction(function () use ($shooter, $neighbour) {
            $a = $shooter->sort_order;
            $b = $neighbour->sort_order;
            $shooter->update(['sort_order' => $b]);
            $neighbour->update(['sort_order' => $a]);
        });
    }

    /** Swap with the immediate neighbour below them in the same squad. */
    public function moveShooterDown(int $shooterId): void
    {
        $shooter = Shooter::findOrFail($shooterId);
        if (! $this->match->squads()->whereKey($shooter->squad_id)->exists()) { return; }

        $neighbour = Shooter::where('squad_id', $shooter->squad_id)
            ->where('sort_order', '>', $shooter->sort_order)
            ->orderBy('sort_order')
            ->first();
        if (! $neighbour) { return; }

        DB::transaction(function () use ($shooter, $neighbour) {
            $a = $shooter->sort_order;
            $b = $neighbour->sort_order;
            $shooter->update(['sort_order' => $b]);
            $neighbour->update(['sort_order' => $a]);
        });
    }

    public function removeFromRelay(int $shooterId): void
    {
        $shooter = Shooter::findOrFail($shooterId);
        $defaultSquad = $this->match->squads()->firstOrCreate(['name' => 'Unassigned'], ['sort_order' => 999]);
        $maxSort = Shooter::where('squad_id', $defaultSquad->id)->max('sort_order') ?? 0;
        $shooter->update(['squad_id' => $defaultSquad->id, 'sort_order' => $maxSort + 1]);
        Flux::toast("{$shooter->name} moved to unassigned.", variant: 'success');
    }

    // ── Match-Day Management ──

    public function markNoShow(int $shooterId): void
    {
        $shooter = Shooter::findOrFail($shooterId);
        $shooter->update(['status' => 'no_show']);
        Flux::toast("{$shooter->name} marked as no-show.", variant: 'warning');
    }

    public function markPresent(int $shooterId): void
    {
        $shooter = Shooter::findOrFail($shooterId);
        $shooter->update(['status' => 'active']);
        Flux::toast("{$shooter->name} marked as present.", variant: 'success');
    }

    public function markWithdrawn(int $shooterId): void
    {
        $shooter = Shooter::findOrFail($shooterId);
        $shooter->update(['status' => 'withdrawn']);
        Flux::toast("{$shooter->name} marked as withdrawn.", variant: 'warning');
    }

    public function deleteShooter(int $shooterId): void
    {
        $shooter = Shooter::findOrFail($shooterId);
        $name = $shooter->name;
        $shooter->scores()->delete();
        $shooter->stageTimes()->delete();
        $shooter->prsStageResults()->delete();
        $shooter->disqualifications()->delete();
        $shooter->delete();
        Flux::toast("{$name} removed from the match.", variant: 'success');
    }

    public function markAllPresent(): void
    {
        $count = $this->match->shooters()->count();
        Flux::toast("All {$count} shooters confirmed as present.", variant: 'success');
    }

    public function markAllNoShow(): void
    {
        $shooters = $this->match->shooters()->where('status', 'active')->get();
        $toMark = $shooters->filter(fn ($s) => !$s->scores()->exists() && !$s->prsStageResults()->exists());
        $toMark->each(fn ($s) => $s->update(['status' => 'no_show']));
        Flux::toast("{$toMark->count()} shooter(s) without scores marked as no-show.", variant: 'warning');
    }

    // ── Walk-in with user search ──

    public function selectWalkinUser(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->walkinUserId = $user->id;
        $this->walkinName = $user->name;
        $this->walkinSearch = '';
    }

    public function clearWalkinUser(): void
    {
        $this->walkinUserId = null;
        $this->walkinSearch = '';
    }

    public function addWalkin(): void
    {
        $this->validate([
            'walkinName' => 'required|string|max:255',
            'walkinBib' => 'nullable|string|max:50',
            'walkinCaliber' => 'nullable|string|max:64',
            'walkinRelayId' => 'required|integer',
        ]);

        $squad = $this->match->squads()->findOrFail($this->walkinRelayId);
        if ($squad->isFull()) { Flux::toast("{$squad->name} is full.", variant: 'danger'); return; }

        if ($this->walkinUserId) {
            $existing = $this->match->shooters()->where('user_id', $this->walkinUserId)->first();
            if ($existing) { Flux::toast("{$this->walkinName} is already in this match.", variant: 'danger'); return; }
        }

        // Persist caliber alongside the name using the platform-standard
        // "Firstname Lastname — Caliber" suffix so reports and scoreboards
        // that parse caliber via caliberFromShooterName() pick it up without
        // any extra lookup. Strip any caliber the user already typed into
        // the name to avoid double-suffixing.
        $caliber = trim($this->walkinCaliber);
        $baseName = trim($this->walkinName);
        if ($caliber !== '' && str_contains($baseName, ' — ')) {
            $baseName = trim(\Illuminate\Support\Str::before($baseName, ' — '));
        }
        $displayName = $caliber !== '' ? "{$baseName} — {$caliber}" : $baseName;

        $maxSort = Shooter::where('squad_id', $squad->id)->max('sort_order') ?? 0;
        $shooter = Shooter::create([
            'squad_id' => $squad->id,
            'name' => $displayName,
            'bib_number' => $this->walkinBib ?: null,
            'user_id' => $this->walkinUserId,
            'sort_order' => $maxSort + 1,
            'status' => 'active',
        ]);

        if ($this->walkinUserId) {
            $walkinUser = User::find($this->walkinUserId);
            $registration = MatchRegistration::firstOrCreate(
                ['match_id' => $this->match->id, 'user_id' => $this->walkinUserId],
                [
                    'payment_status' => 'confirmed',
                    'payment_reference' => $walkinUser ? MatchRegistration::generatePaymentReference($walkinUser) : 'WALKIN-'.strtoupper(\Illuminate\Support\Str::random(8)),
                    'amount' => $this->match->entry_fee ?? 0,
                    'is_free_entry' => ($this->match->entry_fee ?? 0) == 0,
                    'admin_notes' => 'Walk-in added by admin on match day',
                    'caliber' => $caliber !== '' ? $caliber : null,
                ]
            );

            // firstOrCreate skips attribute updates if the row already
            // existed — backfill caliber if a registration was there but
            // empty so reports see the captured value.
            if ($caliber !== '' && empty($registration->caliber)) {
                $registration->update(['caliber' => $caliber]);
            }
        }

        $this->reset('walkinName', 'walkinBib', 'walkinCaliber', 'walkinRelayId', 'walkinUserId', 'walkinSearch');
        Flux::toast("{$shooter->name} added to {$squad->name}.", variant: 'success');
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

        $allShooters = $this->match->shooters()->get();
        $totalShooters = $allShooters->count();
        $activeCount = $allShooters->where('status', 'active')->count();
        $noShowCount = $allShooters->where('status', 'no_show')->count();
        $withdrawnCount = $allShooters->where('status', 'withdrawn')->count();
        $dqCount = $allShooters->where('status', 'dq')->count();

        $userResults = [];
        if (strlen($this->walkinSearch) >= 2) {
            $existingUserIds = $this->match->shooters()->whereNotNull('user_id')->pluck('user_id')->toArray();
            $userResults = User::where(function ($q) {
                $q->where('name', 'like', "%{$this->walkinSearch}%")
                  ->orWhere('email', 'like', "%{$this->walkinSearch}%");
            })->whereNotIn('id', $existingUserIds)->limit(8)->get(['id', 'name', 'email'])->toArray();
        }

        return [
            'squads' => $realSquads,
            'allSquads' => $squads,
            'unassignedShooters' => $unassignedShooters,
            'confirmedCount' => $confirmedCount,
            'shareMap' => $shareMap,
            'concurrentSize' => $concurrentSize,
            'totalShooters' => $totalShooters,
            'activeCount' => $activeCount,
            'noShowCount' => $noShowCount,
            'withdrawnCount' => $withdrawnCount,
            'dqCount' => $dqCount,
            'userResults' => $userResults,
        ];
    }
}; ?>

<div class="space-y-6 max-w-6xl" x-data="{ tab: @entangle('activeTab') }">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('admin.matches.edit', $match) }}" variant="ghost" size="sm">
                <x-icon name="chevron-left" class="mr-1 h-4 w-4" />
                Back
            </flux:button>
            <div>
                <flux:heading size="xl">Squads &amp; Shooters</flux:heading>
                <p class="mt-1 text-sm text-muted">{{ $match->name }} &mdash; {{ $confirmedCount }} confirmed registrations</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($match->status->canTransitionTo(MatchStatus::SquaddingOpen))
                <flux:button wire:click="openSquadding" variant="primary" class="!bg-indigo-600 hover:!bg-indigo-700 min-h-[44px]" wire:confirm="Open squadding to shooters?">Open Squadding</flux:button>
            @elseif($match->status === MatchStatus::SquaddingOpen)
                <flux:badge color="indigo" size="sm">Squadding Open</flux:badge>
                <flux:button wire:click="closeSquadding" variant="ghost" class="min-h-[44px]" wire:confirm="Close squadding and activate match?">Close &amp; Activate</flux:button>
            @endif
        </div>
    </div>

    {{-- Status summary --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
        <div class="rounded-xl border border-border bg-surface p-4 text-center">
            <p class="text-2xl font-bold text-primary">{{ $totalShooters }}</p>
            <p class="text-xs text-muted mt-1">Total Shooters</p>
        </div>
        <div class="rounded-xl border border-green-500/20 bg-green-500/5 p-4 text-center">
            <p class="text-2xl font-bold text-green-400">{{ $activeCount }}</p>
            <p class="text-xs text-muted mt-1">Active</p>
        </div>
        <div class="rounded-xl border border-zinc-500/20 bg-zinc-500/5 p-4 text-center">
            <p class="text-2xl font-bold text-zinc-400">{{ $noShowCount }}</p>
            <p class="text-xs text-muted mt-1">No-Shows</p>
        </div>
        <div class="rounded-xl border border-amber-500/20 bg-amber-500/5 p-4 text-center">
            <p class="text-2xl font-bold text-amber-400">{{ $withdrawnCount }}</p>
            <p class="text-xs text-muted mt-1">Withdrawn</p>
        </div>
        <div class="rounded-xl border border-red-500/20 bg-red-500/5 p-4 text-center">
            <p class="text-2xl font-bold text-red-400">{{ $dqCount }}</p>
            <p class="text-xs text-muted mt-1">DQ'd</p>
        </div>
    </div>

    {{-- Tab Navigation --}}
    <div class="flex gap-1 border-b border-border">
        <button @click="tab = 'squads'"
                :class="tab === 'squads' ? 'border-accent text-primary' : 'border-transparent text-muted hover:text-secondary'"
                class="border-b-2 px-4 py-2.5 text-sm font-medium transition-colors min-h-[44px]">Squads</button>
        <button @click="tab = 'matchday'"
                :class="tab === 'matchday' ? 'border-accent text-primary' : 'border-transparent text-muted hover:text-secondary'"
                class="border-b-2 px-4 py-2.5 text-sm font-medium transition-colors min-h-[44px]">Match Day</button>
        <button @click="tab = 'walkin'"
                :class="tab === 'walkin' ? 'border-accent text-primary' : 'border-transparent text-muted hover:text-secondary'"
                class="border-b-2 px-4 py-2.5 text-sm font-medium transition-colors min-h-[44px]">Walk-ins &amp; Add</button>
    </div>

    {{-- TAB: Squads --}}
    <div x-show="tab === 'squads'" x-cloak class="space-y-5">

        {{-- Auto Setup Relays --}}
        <div class="rounded-xl border border-accent/30 bg-accent/5 p-4 space-y-3">
            <h3 class="text-sm font-semibold text-primary">Auto Setup Relays</h3>
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs text-muted mb-1">Max per relay</label>
                    <input type="number" wire:model="autoRelayMax" min="1" max="50"
                           class="w-24 rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:ring-1 focus:ring-accent min-h-[44px]" />
                </div>
                <flux:button wire:click="autoSetupRelays" variant="primary" class="!bg-accent hover:!bg-accent-hover min-h-[44px]"
                             wire:confirm="This will create relays and randomly assign ALL shooters. Continue?">
                    Auto Setup Relays
                </flux:button>
            </div>
            <p class="text-[10px] text-muted">Creates the right number of relays for all registered shooters and randomly distributes them.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button wire:click="autoSquad" variant="primary" class="!bg-indigo-600 hover:!bg-indigo-700 min-h-[44px]"
                         wire:confirm="Auto-assign all unassigned registrants across squads (round-robin)?">Auto-Squad</flux:button>
            <flux:button wire:click="randomizeRelays" variant="primary" class="!bg-accent hover:!bg-accent-hover min-h-[44px]"
                         wire:confirm="Randomize all shooters into relays? Existing assignments will be reshuffled.">Randomize Relays</flux:button>
        </div>

        <div class="rounded-xl border border-border bg-surface p-4 space-y-3">
            <h3 class="text-sm font-semibold text-primary">Squad Capacity</h3>
            <div class="flex items-end gap-4">
                <div>
                    <label class="block text-xs text-muted mb-1">Default Max per Squad</label>
                    <input type="number" wire:model="defaultCapacity" min="1" placeholder="Unlimited"
                           class="w-28 rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-accent focus:ring-1 focus:ring-accent min-h-[44px]" />
                </div>
                <flux:button wire:click="updateDefaultCapacity" size="sm" variant="ghost" class="min-h-[44px]">Save Default</flux:button>
            </div>
        </div>

        @php $colors = ['bg-blue-500/20 text-blue-400', 'bg-emerald-500/20 text-emerald-400', 'bg-amber-500/20 text-amber-400', 'bg-purple-500/20 text-purple-400', 'bg-pink-500/20 text-pink-400']; @endphp
        <div class="flex flex-wrap items-center gap-2 text-xs text-muted">
            <span class="font-medium text-secondary">Concurrent groups:</span>
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
                        <div class="flex items-center justify-between border-b border-border px-4 py-2.5">
                            <div class="flex items-center gap-2 flex-wrap">
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
                                <a href="{{ route('admin.matches.squad-correction', [$match, $squad]) }}"
                                   class="inline-flex items-center gap-1 rounded border border-border bg-surface-2 px-2 py-1 text-[10px] font-medium text-muted transition-colors hover:border-accent hover:text-accent min-h-[36px]"
                                   title="Post-squad score correction — fix hits/misses after the squad leaves the line">
                                    Correct scores
                                </a>
                                <input type="number" min="1" placeholder="Cap" value="{{ $squad->max_capacity }}"
                                       wire:change="updateSquadCapacity({{ $squad->id }}, $event.target.value)"
                                       class="w-16 rounded border border-border bg-surface-2 px-2 py-1 text-xs text-primary text-center focus:border-accent min-h-[36px]" title="Max capacity override" />
                                <button wire:click="deleteSquad({{ $squad->id }})" wire:confirm="Delete {{ $squad->name }} and all its shooters?"
                                        class="text-xs text-accent/60 hover:text-accent transition-colors min-h-[36px] px-1">&times;</button>
                            </div>
                        </div>
                        <div class="p-3">
                            @if($squad->shooters->isNotEmpty())
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead><tr class="text-left text-muted border-b border-border/50">
                                            <th class="px-2 py-1.5 font-medium w-10">#</th>
                                            <th class="px-2 py-1.5 font-medium">Shooter</th>
                                            <th class="px-2 py-1.5 font-medium w-20">Status</th>
                                            <th class="px-2 py-1.5 font-medium w-32">Shares rifle</th>
                                            <th class="px-2 py-1.5 font-medium w-32 text-right">Actions</th>
                                        </tr></thead>
                                        <tbody class="divide-y divide-border/30">
                                            @foreach($squad->shooters as $shooter)
                                                <tr wire:key="sh-{{ $shooter->id }}" class="{{ $shooter->isNoShow() ? 'opacity-50' : '' }} {{ $shooter->isDq() ? 'opacity-40' : '' }}">
                                                    <td class="px-2 py-1.5 text-muted font-mono text-xs">{{ $shooter->sort_order }}</td>
                                                    <td class="px-2 py-1.5 text-secondary">
                                                        {{ $shooter->name }}
                                                        @if(!$shooter->user_id)
                                                            <span class="ml-1 rounded px-1 py-0.5 text-[9px] bg-amber-500/10 text-amber-400">walk-in</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-2 py-1.5">
                                                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium {{ $shooter->statusBadgeClasses() }}">{{ $shooter->statusLabel() }}</span>
                                                    </td>
                                                    <td class="px-2 py-1.5">
                                                        @if(isset($shareMap[$shooter->user_id]))
                                                            <span class="rounded bg-amber-600/20 px-1.5 py-0.5 text-[10px] text-amber-400">{{ $shareMap[$shooter->user_id] }}</span>
                                                        @else
                                                            <span class="text-muted text-xs">&mdash;</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-2 py-1.5 text-right">
                                                        <div class="flex items-center justify-end gap-1" x-data="{ open: false, sOpen: false }">
                                                            {{-- Reorder within squad --}}
                                                            <button wire:click="moveShooterUp({{ $shooter->id }})"
                                                                    @class([
                                                                        'rounded px-1.5 py-1 text-xs transition-colors min-h-[32px]',
                                                                        'text-muted/30 cursor-not-allowed' => $loop->first,
                                                                        'text-muted hover:text-secondary hover:bg-surface-2' => ! $loop->first,
                                                                    ])
                                                                    @disabled($loop->first)
                                                                    title="Move up">
                                                                <x-icon name="chevron-up" class="h-3.5 w-3.5" />
                                                            </button>
                                                            <button wire:click="moveShooterDown({{ $shooter->id }})"
                                                                    @class([
                                                                        'rounded px-1.5 py-1 text-xs transition-colors min-h-[32px]',
                                                                        'text-muted/30 cursor-not-allowed' => $loop->last,
                                                                        'text-muted hover:text-secondary hover:bg-surface-2' => ! $loop->last,
                                                                    ])
                                                                    @disabled($loop->last)
                                                                    title="Move down">
                                                                <x-icon name="chevron-down" class="h-3.5 w-3.5" />
                                                            </button>
                                                            <div class="relative" @click.away="sOpen = false">
                                                                <button @click="sOpen = !sOpen" class="rounded px-2 py-1 text-[10px] font-medium text-muted hover:text-secondary hover:bg-surface-2 transition-colors min-h-[32px]" title="Change status">
                                                                    <x-icon name="settings" class="h-3.5 w-3.5" />
                                                                </button>
                                                                <div x-show="sOpen" x-transition x-cloak class="absolute right-0 z-20 mt-1 w-44 rounded-lg border border-border bg-surface-2 py-1 shadow-xl">
                                                                    @if(!$shooter->isActive())
                                                                        <button wire:click="markPresent({{ $shooter->id }})" @click="sOpen = false" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-green-400 hover:bg-surface transition-colors min-h-[36px]">Mark Present</button>
                                                                    @endif
                                                                    @if(!$shooter->isNoShow())
                                                                        <button wire:click="markNoShow({{ $shooter->id }})" @click="sOpen = false" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-zinc-400 hover:bg-surface transition-colors min-h-[36px]">Mark No-Show</button>
                                                                    @endif
                                                                    @if(!$shooter->isWithdrawn())
                                                                        <button wire:click="markWithdrawn({{ $shooter->id }})" @click="sOpen = false" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-amber-400 hover:bg-surface transition-colors min-h-[36px]">Mark Withdrawn</button>
                                                                    @endif
                                                                    <div class="my-1 border-t border-border/50"></div>
                                                                    <button wire:click="deleteShooter({{ $shooter->id }})" @click="sOpen = false"
                                                                            wire:confirm="Permanently remove {{ $shooter->name }}? This deletes all their scores."
                                                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-red-400 hover:bg-red-500/10 transition-colors min-h-[36px]">Delete Shooter</button>
                                                                </div>
                                                            </div>
                                                            @if($squads->count() > 1)
                                                                <div class="relative" @click.away="open = false">
                                                                    <button @click="open = !open" class="rounded px-1.5 py-1 text-xs text-muted hover:text-secondary transition-colors min-h-[32px]" title="Move to squad">&#8644;</button>
                                                                    <div x-show="open" x-transition x-cloak class="absolute right-0 z-10 mt-1 w-40 rounded-lg border border-border bg-surface-2 py-1 shadow-lg">
                                                                        @foreach($squads->where('id', '!=', $squad->id) as $otherSquad)
                                                                            <button wire:click="moveShooter({{ $shooter->id }}, {{ $otherSquad->id }})" @click="open = false"
                                                                                    class="block w-full px-3 py-1.5 text-left text-xs text-secondary hover:bg-surface hover:text-white transition-colors min-h-[36px]">{{ $otherSquad->name }}</button>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            @endif
                                                            <button wire:click="removeFromRelay({{ $shooter->id }})" class="rounded px-1 py-0.5 text-xs text-accent/60 hover:text-accent transition-colors min-h-[32px]" title="Remove from squad">&times;</button>
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
                                <button @click="open = !open" class="text-xs text-muted hover:text-secondary min-h-[32px]">&#8644;</button>
                                <div x-show="open" x-transition x-cloak class="absolute left-0 z-10 mt-1 w-40 rounded-lg border border-border bg-surface-2 py-1 shadow-lg">
                                    @foreach($squads as $sq)
                                        <button wire:click="moveShooter({{ $shooter->id }}, {{ $sq->id }})" @click="open = false"
                                                class="block w-full px-3 py-1.5 text-left text-xs text-secondary hover:bg-surface hover:text-white transition-colors min-h-[36px]">{{ $sq->name }}</button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="rounded-xl border border-dashed border-border bg-surface/50 p-4 space-y-3">
            <h3 class="text-sm font-medium text-secondary">Add Squad</h3>
            <div class="flex gap-3 items-end">
                <div class="flex-1"><flux:input wire:model="newSquadName" placeholder="e.g. Squad A" /></div>
                <flux:button wire:click="addSquad" size="sm" variant="primary" class="!bg-accent hover:!bg-accent-hover min-h-[44px]">Add Squad</flux:button>
            </div>
        </div>
    </div>

    {{-- TAB: Match Day --}}
    <div x-show="tab === 'matchday'" x-cloak class="space-y-5">
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-primary">Match Day Attendance</h2>
                <p class="text-sm text-muted mt-1">Mark shooters as present or no-show (i.e. absent from their relay). No-shows are kept on record but excluded from rankings and field-average stats so the numbers stay accurate.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <flux:button wire:click="markAllPresent" size="sm" variant="ghost" class="!text-green-400 hover:!bg-green-500/10 min-h-[44px]" wire:confirm="Confirm all shooters as present?">All Present</flux:button>
                <flux:button wire:click="markAllNoShow" size="sm" variant="ghost" class="!text-zinc-400 hover:!bg-zinc-500/10 min-h-[44px]" wire:confirm="Mark all shooters WITHOUT scores as no-show?">Flag No-Shows</flux:button>
            </div>
        </div>

        @if($totalShooters > 0)
            <div class="rounded-xl border border-border bg-surface overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-muted border-b border-border bg-surface-2/30">
                                <th class="px-4 py-3 font-medium">Shooter</th>
                                <th class="px-4 py-3 font-medium w-28">Squad</th>
                                <th class="px-4 py-3 font-medium w-24">Status</th>
                                <th class="px-4 py-3 font-medium w-36 text-right">Quick Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/30">
                            @foreach($allSquads as $squad)
                                @foreach($squad->shooters->sortBy('name') as $shooter)
                                    <tr wire:key="md-{{ $shooter->id }}" class="{{ $shooter->isNoShow() ? 'opacity-50 bg-zinc-900/20' : '' }} {{ $shooter->isDq() ? 'opacity-40 bg-red-900/10' : '' }}">
                                        <td class="px-4 py-2.5">
                                            <span class="text-secondary font-medium">{{ $shooter->name }}</span>
                                            @if(!$shooter->user_id)
                                                <span class="ml-1 rounded px-1 py-0.5 text-[9px] bg-amber-500/10 text-amber-400">walk-in</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5 text-xs text-muted">{{ in_array($squad->name, ['Default', 'Unassigned']) ? 'Unassigned' : $squad->name }}</td>
                                        <td class="px-4 py-2.5">
                                            <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-medium {{ $shooter->statusBadgeClasses() }}">{{ $shooter->statusLabel() }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                @if($shooter->isActive())
                                                    <button wire:click="markNoShow({{ $shooter->id }})" class="rounded px-2 py-1 text-[10px] font-medium text-zinc-400 hover:bg-zinc-500/10 transition-colors min-h-[32px]">No-Show</button>
                                                    <button wire:click="markWithdrawn({{ $shooter->id }})" class="rounded px-2 py-1 text-[10px] font-medium text-amber-400 hover:bg-amber-500/10 transition-colors min-h-[32px]">Withdrawn</button>
                                                @elseif($shooter->isNoShow() || $shooter->isWithdrawn())
                                                    <button wire:click="markPresent({{ $shooter->id }})" class="rounded px-2 py-1 text-[10px] font-medium text-green-400 hover:bg-green-500/10 transition-colors min-h-[32px]">Reinstate</button>
                                                @elseif($shooter->isDq())
                                                    <span class="text-[10px] text-muted">DQ'd</span>
                                                @endif
                                                <button wire:click="deleteShooter({{ $shooter->id }})" wire:confirm="Permanently remove {{ $shooter->name }}?"
                                                        class="rounded px-1.5 py-1 text-xs text-red-400/50 hover:text-red-400 hover:bg-red-500/10 transition-colors min-h-[32px]">
                                                    <x-icon name="trash-2" class="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="rounded-xl border border-dashed border-border bg-surface/50 p-8 text-center">
                <p class="text-muted">No shooters in this match yet.</p>
            </div>
        @endif
    </div>

    {{-- TAB: Walk-ins --}}
    <div x-show="tab === 'walkin'" x-cloak class="space-y-5">
        <div class="rounded-xl border border-border bg-surface p-6 space-y-5">
            <div>
                <h2 class="text-lg font-semibold text-primary">Add Walk-in Shooter</h2>
                <p class="text-sm text-muted mt-1">Add shooters who showed up on match day. Link them to an existing account if they have one.</p>
            </div>

            <div class="space-y-2">
                <label class="block text-sm font-medium text-secondary">Link to existing account <span class="text-muted font-normal">(optional)</span></label>
                @if($walkinUserId)
                    <div class="flex items-center gap-3 rounded-lg border border-green-500/30 bg-green-500/5 px-4 py-3">
                        <x-icon name="circle-check" class="h-5 w-5 text-green-400 shrink-0" />
                        <div class="flex-1">
                            <p class="text-sm font-medium text-primary">{{ $walkinName }}</p>
                            <p class="text-xs text-muted">Linked — scores will appear on their profile</p>
                        </div>
                        <button wire:click="clearWalkinUser" class="rounded px-2 py-1 text-xs text-muted hover:text-accent transition-colors min-h-[36px]">Change</button>
                    </div>
                @else
                    <div class="relative" x-data="{ focused: false }">
                        <input type="text" wire:model.live.debounce.300ms="walkinSearch" @focus="focused = true" @click.away="focused = false"
                               placeholder="Search by name or email..."
                               class="w-full rounded-lg border border-border bg-surface-2 px-4 py-2.5 text-sm text-primary placeholder-muted focus:border-accent focus:ring-1 focus:ring-accent min-h-[44px]" />
                        @if(count($userResults) > 0)
                            <div x-show="focused" x-transition x-cloak class="absolute left-0 right-0 z-20 mt-1 max-h-60 overflow-y-auto rounded-lg border border-border bg-surface-2 shadow-xl">
                                @foreach($userResults as $u)
                                    <button wire:click="selectWalkinUser({{ $u['id'] }})" @click="focused = false"
                                            class="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-surface transition-colors min-h-[44px]">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-surface text-xs font-bold text-primary shrink-0">{{ strtoupper(substr($u['name'], 0, 1)) }}</div>
                                        <div>
                                            <p class="text-sm font-medium text-secondary">{{ $u['name'] }}</p>
                                            <p class="text-xs text-muted">{{ $u['email'] }}</p>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        @elseif(strlen($walkinSearch) >= 2)
                            <div x-show="focused" x-cloak class="absolute left-0 right-0 z-20 mt-1 rounded-lg border border-border bg-surface-2 px-4 py-3 shadow-xl">
                                <p class="text-sm text-muted">No matching users found.</p>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-secondary mb-1">Name <span class="text-accent">*</span></label>
                    <input type="text" wire:model="walkinName" placeholder="Shooter name"
                           class="w-full rounded-lg border border-border bg-surface-2 px-4 py-2.5 text-sm text-primary placeholder-muted focus:border-accent focus:ring-1 focus:ring-accent min-h-[44px]"
                           {{ $walkinUserId ? 'readonly' : '' }} />
                </div>
                <div>
                    <label class="block text-sm font-medium text-secondary mb-1">Caliber</label>
                    <input type="text" wire:model="walkinCaliber" placeholder="e.g. 6.5 Creedmoor"
                           list="dl-walkin-calibers-admin"
                           class="w-full rounded-lg border border-border bg-surface-2 px-4 py-2.5 text-sm text-primary placeholder-muted focus:border-accent focus:ring-1 focus:ring-accent min-h-[44px]" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-secondary mb-1">Bib #</label>
                    <input type="text" wire:model="walkinBib" placeholder="e.g. 42"
                           class="w-full rounded-lg border border-border bg-surface-2 px-4 py-2.5 text-sm text-primary placeholder-muted focus:border-accent focus:ring-1 focus:ring-accent min-h-[44px]" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-secondary mb-1">Squad <span class="text-accent">*</span></label>
                    <select wire:model="walkinRelayId"
                            class="w-full rounded-lg border border-border bg-surface-2 px-4 py-2.5 text-sm text-primary focus:border-accent focus:ring-1 focus:ring-accent min-h-[44px]">
                        <option value="">Select squad</option>
                        @foreach($allSquads as $sq)
                            <option value="{{ $sq->id }}">{{ $sq->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Same curated caliber list used by the member equipment form
                 and regular registration, so walk-ins match the rest of the
                 platform's suggestion set. --}}
            <datalist id="dl-walkin-calibers-admin">
                @foreach(array_unique(array_merge(config('equipment-suggestions.calibers', []), config('equipment-suggestions.caliber_aliases', []))) as $v)
                    <option value="{{ $v }}">
                @endforeach
            </datalist>

            <flux:button wire:click="addWalkin" variant="primary" class="!bg-accent hover:!bg-accent-hover min-h-[44px]">Add Walk-in</flux:button>
        </div>
    </div>
</div>
