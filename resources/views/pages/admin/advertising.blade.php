<?php

use App\Enums\AdvertisingMode;
use App\Enums\MdPackageStatus;
use App\Enums\PlacementKey;
use App\Enums\SponsorScope;
use App\Models\Setting;
use App\Models\ShootingMatch;
use App\Models\Sponsor;
use App\Models\SponsorAssignment;
use App\Services\AdvertisingService;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Advertising')]
    class extends Component {

    public bool $advertisingEnabled = false;

    #[Url(as: 'filter')]
    public string $statusFilter = '';

    public function mount(): void
    {
        $this->advertisingEnabled = (bool) Setting::get('advertising_enabled', false);
    }

    public function toggleAdvertising(): void
    {
        $this->advertisingEnabled = !$this->advertisingEnabled;
        Setting::set('advertising_enabled', $this->advertisingEnabled ? '1' : '0');
        Flux::toast($this->advertisingEnabled ? 'Advertising enabled' : 'Advertising disabled', variant: 'success');
    }

    public ?int $sellMatchId = null;
    public string $sellMode = 'package';
    public string $sellBrandId = '';
    public string $sellPlacementKey = '';

    public function openSell(int $matchId, string $mode = 'package'): void
    {
        $this->sellMatchId = $matchId;
        $this->sellMode = $mode;
        $this->sellBrandId = '';
        $this->sellPlacementKey = '';
        Flux::modal('sell-form')->show();
    }

    public function sell(): void
    {
        $match = ShootingMatch::findOrFail($this->sellMatchId);
        $brand = Sponsor::active()->findOrFail((int) $this->sellBrandId);
        $service = app(AdvertisingService::class);

        try {
            if ($this->sellMode === 'package') {
                $service->sellFullPackage($match, $brand);
                Flux::toast('Full package sold to ' . $brand->name, variant: 'success');
            } else {
                $key = PlacementKey::from($this->sellPlacementKey);
                $service->sellIndividualPlacement($match, $key, $brand);
                Flux::toast($key->poweredByLabel() . ' placement sold to ' . $brand->name, variant: 'success');
            }
        } catch (\LogicException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');
        }

        Flux::modal('sell-form')->close();
    }

    public function clearAll(int $matchId): void
    {
        $match = ShootingMatch::findOrFail($matchId);
        app(AdvertisingService::class)->clearAllPlacements($match);
        Flux::toast('All placements cleared for ' . $match->name, variant: 'success');
    }

    public function clearPlacement(int $matchId, string $keyValue): void
    {
        $match = ShootingMatch::findOrFail($matchId);
        $key = PlacementKey::from($keyValue);
        app(AdvertisingService::class)->clearPlacement($match, $key);
        Flux::toast($key->poweredByLabel() . ' placement cleared.', variant: 'success');
    }

    public function with(): array
    {
        $query = ShootingMatch::query()
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->with('fullPackageBrand', 'organization')
            ->orderByDesc('date');

        if ($this->statusFilter === 'pending') {
            $query->where('md_package_status', 'pending');
        } elseif ($this->statusFilter === 'public') {
            $query->where('advertising_mode', 'public_open');
        } elseif ($this->statusFilter === 'package_sold') {
            $query->whereNotNull('full_package_brand_id');
        } elseif ($this->statusFilter === 'unsold') {
            $query->whereNull('full_package_brand_id')
                ->where('advertising_mode', 'public_open');
        }

        $matches = $query->get();

        $matchPlacements = [];
        foreach ($matches as $match) {
            $assignments = SponsorAssignment::forMatch($match->id)
                ->whereIn('placement_key', PlacementKey::advertisingPlacements())
                ->with('sponsor')
                ->active()
                ->get()
                ->keyBy(fn ($a) => $a->placement_key->value);
            $matchPlacements[$match->id] = $assignments;
        }

        $totalPackagesSold = ShootingMatch::whereNotNull('full_package_brand_id')->count();
        $totalIndividual = SponsorAssignment::query()
            ->where('scope_type', SponsorScope::Match)
            ->whereIn('placement_key', PlacementKey::advertisingPlacements())
            ->whereNotNull('sponsor_id')
            ->where('active', true)
            ->count();

        return [
            'matches' => $matches,
            'matchPlacements' => $matchPlacements,
            'brands' => Sponsor::active()->orderBy('name')->get(),
            'advertisingPlacements' => PlacementKey::advertisingPlacements(),
            'totalPackagesSold' => $totalPackagesSold,
            'totalIndividual' => $totalIndividual,
        ];
    }
}; ?>

<div class="space-y-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Advertising</flux:heading>
            <p class="mt-1 text-sm text-muted">Manage advertising placements across all events. Sell full packages or individual placements.</p>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-border bg-surface px-4 py-3">
            <span class="text-sm font-medium {{ $advertisingEnabled ? 'text-green-400' : 'text-zinc-500' }}">
                {{ $advertisingEnabled ? 'Live' : 'Off' }}
            </span>
            <flux:switch wire:click="toggleAdvertising" :checked="$advertisingEnabled" />
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-border bg-surface p-4">
            <p class="text-xs text-muted uppercase tracking-wide">Full Packages Sold</p>
            <p class="mt-1 text-2xl font-bold text-primary">{{ $totalPackagesSold }}</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4">
            <p class="text-xs text-muted uppercase tracking-wide">Individual Placements</p>
            <p class="mt-1 text-2xl font-bold text-primary">{{ $totalIndividual }}</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4">
            <p class="text-xs text-muted uppercase tracking-wide">Total Events</p>
            <p class="mt-1 text-2xl font-bold text-primary">{{ $matches->count() }}</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4">
            <p class="text-xs text-muted uppercase tracking-wide">Available Inventory</p>
            <p class="mt-1 text-2xl font-bold text-primary">{{ $matches->filter(fn($m) => !$m->isFullPackageSold() && $m->advertising_mode === \App\Enums\AdvertisingMode::PublicOpen)->count() }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap gap-2">
        <flux:button size="sm" :variant="$statusFilter === '' ? 'primary' : 'ghost'" wire:click="$set('statusFilter', '')">All</flux:button>
        <flux:button size="sm" :variant="$statusFilter === 'pending' ? 'primary' : 'ghost'" wire:click="$set('statusFilter', 'pending')">Waiting for MD</flux:button>
        <flux:button size="sm" :variant="$statusFilter === 'public' ? 'primary' : 'ghost'" wire:click="$set('statusFilter', 'public')">Public Open</flux:button>
        <flux:button size="sm" :variant="$statusFilter === 'package_sold' ? 'primary' : 'ghost'" wire:click="$set('statusFilter', 'package_sold')">Full Package Sold</flux:button>
        <flux:button size="sm" :variant="$statusFilter === 'unsold' ? 'primary' : 'ghost'" wire:click="$set('statusFilter', 'unsold')">Has Unsold Inventory</flux:button>
    </div>

    {{-- Table --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Event</flux:table.column>
            <flux:table.column>Date</flux:table.column>
            <flux:table.column>MD Package</flux:table.column>
            <flux:table.column>Mode</flux:table.column>
            <flux:table.column>Leaderboard</flux:table.column>
            <flux:table.column>Results</flux:table.column>
            <flux:table.column>Scoring</flux:table.column>
            <flux:table.column align="end">Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse($matches as $match)
                @php
                    $placements = $matchPlacements[$match->id] ?? collect();
                @endphp
                <flux:table.row wire:key="ad-match-{{ $match->id }}">
                    <flux:table.cell variant="strong">
                        <div>
                            <span>{{ $match->name }}</span>
                            @if($match->organization)
                                <span class="text-xs text-muted block">{{ $match->organization->name }}</span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $match->date?->format('d M Y') ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if($match->md_package_status === \App\Enums\MdPackageStatus::Pending)
                            <flux:badge size="sm" color="amber">Pending</flux:badge>
                        @elseif($match->md_package_status === \App\Enums\MdPackageStatus::Taken)
                            <flux:badge size="sm" color="green">Taken</flux:badge>
                        @elseif($match->md_package_status === \App\Enums\MdPackageStatus::Declined)
                            <flux:badge size="sm" color="zinc">Declined</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">Expired</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <span class="text-xs text-muted">{{ $match->advertising_mode?->label() ?? '—' }}</span>
                    </flux:table.cell>
                    @foreach($advertisingPlacements as $key)
                        @php $a = $placements->get($key->value); @endphp
                        <flux:table.cell>
                            @if($match->isFullPackageSold())
                                <span class="text-xs text-emerald-400">{{ $match->fullPackageBrand?->name ?? 'Brand' }}</span>
                            @elseif($a?->sponsor)
                                <span class="text-xs text-emerald-400">{{ $a->sponsor->name }}</span>
                            @else
                                <span class="text-xs text-muted">Available</span>
                            @endif
                        </flux:table.cell>
                    @endforeach
                    <flux:table.cell align="end">
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            @if(!$match->isFullPackageSold() && !$match->hasIndividualPlacements())
                                <flux:button size="xs" variant="primary" wire:click="openSell({{ $match->id }}, 'package')">Sell Package</flux:button>
                            @endif
                            @if(!$match->isFullPackageSold() && count($match->availablePlacementKeys()) > 0)
                                <flux:button size="xs" variant="ghost" wire:click="openSell({{ $match->id }}, 'individual')">Sell Placement</flux:button>
                            @endif
                            @if($match->isFullPackageSold() || $match->hasIndividualPlacements())
                                <flux:button size="xs" variant="danger" wire:click="clearAll({{ $match->id }})" wire:confirm="Clear all advertising placements for this event?">Clear All</flux:button>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8">
                        <span class="text-sm text-muted">No events match the current filter.</span>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Sell Modal --}}
    <flux:modal name="sell-form" class="min-w-[min(100vw-2rem,28rem)]">
        <form wire:submit="sell" class="space-y-4">
            <flux:heading size="lg">{{ $sellMode === 'package' ? 'Sell Full Package' : 'Sell Individual Placement' }}</flux:heading>

            <flux:select wire:model="sellBrandId" label="Brand" required>
                <option value="">Select a brand…</option>
                @foreach($brands as $brand)
                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                @endforeach
            </flux:select>

            @if($sellMode === 'individual')
                <flux:select wire:model="sellPlacementKey" label="Placement" required>
                    <option value="">Select a placement…</option>
                    @foreach($advertisingPlacements as $key)
                        <option value="{{ $key->value }}">{{ $key->poweredByLabel() }}</option>
                    @endforeach
                </flux:select>
            @endif

            <div class="flex flex-wrap gap-2 pt-2">
                <flux:button type="submit" variant="primary">Confirm Sale</flux:button>
                <flux:button type="button" variant="ghost" wire:click="$dispatch('modal-close', { name: 'sell-form' })">Cancel</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
