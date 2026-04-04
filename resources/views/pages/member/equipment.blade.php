<?php

use App\Models\Rifle;
use App\Models\AmmoLoad;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Flux\Flux;

new #[Layout('components.layouts.app')]
    class extends Component {

    // Rifle form
    public ?int $editingRifleId = null;
    public string $rifleName = '';
    public string $caliber = '';
    public string $action_brand = '';
    public string $barrel_brand_length = '';
    public string $trigger_brand = '';
    public string $stock_chassis_brand = '';
    public string $muzzle_brake_silencer_brand = '';
    public string $scope_brand_type = '';
    public string $scope_mount_brand = '';
    public string $bipod_brand = '';
    public bool $rifleIsDefault = false;
    public bool $showRifleForm = false;

    // Ammo form
    public ?int $editingAmmoId = null;
    public ?int $ammoRifleId = null;
    public string $ammoName = '';
    public string $bullet_brand_type = '';
    public string $bullet_weight = '';
    public string $muzzle_velocity = '';
    public bool $ammoIsDefault = false;
    public bool $showAmmoForm = false;

    public function getTitle(): string
    {
        return 'My Rifles — DeadCenter';
    }

    // ── Rifle CRUD ─────────────────────────────────────

    public function createRifle(): void
    {
        $this->resetRifleForm();
        $this->cancelAmmo();
        $this->showRifleForm = true;
    }

    public function editRifle(int $id): void
    {
        $rifle = auth()->user()->rifles()->findOrFail($id);
        $this->editingRifleId = $rifle->id;
        $this->rifleName = $rifle->name;
        $this->caliber = $rifle->caliber ?? '';
        $this->action_brand = $rifle->action_brand ?? '';
        $this->barrel_brand_length = $rifle->barrel_brand_length ?? '';
        $this->trigger_brand = $rifle->trigger_brand ?? '';
        $this->stock_chassis_brand = $rifle->stock_chassis_brand ?? '';
        $this->muzzle_brake_silencer_brand = $rifle->muzzle_brake_silencer_brand ?? '';
        $this->scope_brand_type = $rifle->scope_brand_type ?? '';
        $this->scope_mount_brand = $rifle->scope_mount_brand ?? '';
        $this->bipod_brand = $rifle->bipod_brand ?? '';
        $this->rifleIsDefault = $rifle->is_default;
        $this->cancelAmmo();
        $this->showRifleForm = true;
    }

    public function saveRifle(): void
    {
        $validated = $this->validate([
            'rifleName'                   => 'required|string|max:100',
            'caliber'                     => 'nullable|string|max:255',
            'action_brand'                => 'nullable|string|max:255',
            'barrel_brand_length'         => 'nullable|string|max:255',
            'trigger_brand'               => 'nullable|string|max:255',
            'stock_chassis_brand'         => 'nullable|string|max:255',
            'muzzle_brake_silencer_brand' => 'nullable|string|max:255',
            'scope_brand_type'            => 'nullable|string|max:255',
            'scope_mount_brand'           => 'nullable|string|max:255',
            'bipod_brand'                 => 'nullable|string|max:255',
            'rifleIsDefault'              => 'boolean',
        ]);

        $data = [
            'name'                        => $validated['rifleName'],
            'caliber'                     => $validated['caliber'],
            'action_brand'                => $validated['action_brand'],
            'barrel_brand_length'         => $validated['barrel_brand_length'],
            'trigger_brand'               => $validated['trigger_brand'],
            'stock_chassis_brand'         => $validated['stock_chassis_brand'],
            'muzzle_brake_silencer_brand' => $validated['muzzle_brake_silencer_brand'],
            'scope_brand_type'            => $validated['scope_brand_type'],
            'scope_mount_brand'           => $validated['scope_mount_brand'],
            'bipod_brand'                 => $validated['bipod_brand'],
            'is_default'                  => $validated['rifleIsDefault'],
        ];

        if ($data['is_default']) {
            auth()->user()->rifles()
                ->when($this->editingRifleId, fn ($q) => $q->where('id', '!=', $this->editingRifleId))
                ->update(['is_default' => false]);
        }

        if ($this->editingRifleId) {
            auth()->user()->rifles()->findOrFail($this->editingRifleId)->update($data);
            Flux::toast('Rifle updated.', variant: 'success');
        } else {
            auth()->user()->rifles()->create($data);
            Flux::toast('Rifle created.', variant: 'success');
        }

        $this->resetRifleForm();
        $this->showRifleForm = false;
    }

    public function deleteRifle(int $id): void
    {
        auth()->user()->rifles()->findOrFail($id)->delete();
        Flux::toast('Rifle deleted.', variant: 'success');
    }

    public function cancelRifle(): void
    {
        $this->resetRifleForm();
        $this->showRifleForm = false;
    }

    private function resetRifleForm(): void
    {
        $this->editingRifleId = null;
        $this->rifleName = '';
        $this->caliber = '';
        $this->action_brand = '';
        $this->barrel_brand_length = '';
        $this->trigger_brand = '';
        $this->stock_chassis_brand = '';
        $this->muzzle_brake_silencer_brand = '';
        $this->scope_brand_type = '';
        $this->scope_mount_brand = '';
        $this->bipod_brand = '';
        $this->rifleIsDefault = false;
        $this->resetValidation();
    }

    // ── Ammo Load CRUD ─────────────────────────────────

    public function createAmmo(int $rifleId): void
    {
        $this->resetAmmoForm();
        $this->cancelRifle();
        $this->ammoRifleId = $rifleId;
        $this->showAmmoForm = true;
    }

    public function editAmmo(int $id): void
    {
        $ammo = AmmoLoad::whereHas('rifle', fn ($q) => $q->where('user_id', auth()->id()))
            ->findOrFail($id);
        $this->editingAmmoId = $ammo->id;
        $this->ammoRifleId = $ammo->rifle_id;
        $this->ammoName = $ammo->name;
        $this->bullet_brand_type = $ammo->bullet_brand_type ?? '';
        $this->bullet_weight = $ammo->bullet_weight ?? '';
        $this->muzzle_velocity = $ammo->muzzle_velocity ?? '';
        $this->ammoIsDefault = $ammo->is_default;
        $this->cancelRifle();
        $this->showAmmoForm = true;
    }

    public function saveAmmo(): void
    {
        $validated = $this->validate([
            'ammoName'          => 'required|string|max:100',
            'bullet_brand_type' => 'nullable|string|max:255',
            'bullet_weight'     => 'nullable|string|max:100',
            'muzzle_velocity'   => 'nullable|string|max:100',
            'ammoIsDefault'     => 'boolean',
        ]);

        $rifle = auth()->user()->rifles()->findOrFail($this->ammoRifleId);

        $data = [
            'name'              => $validated['ammoName'],
            'bullet_brand_type' => $validated['bullet_brand_type'],
            'bullet_weight'     => $validated['bullet_weight'],
            'muzzle_velocity'   => $validated['muzzle_velocity'],
            'is_default'        => $validated['ammoIsDefault'],
        ];

        if ($data['is_default']) {
            $rifle->ammoLoads()
                ->when($this->editingAmmoId, fn ($q) => $q->where('id', '!=', $this->editingAmmoId))
                ->update(['is_default' => false]);
        }

        if ($this->editingAmmoId) {
            $rifle->ammoLoads()->findOrFail($this->editingAmmoId)->update($data);
            Flux::toast('Ammo load updated.', variant: 'success');
        } else {
            $rifle->ammoLoads()->create($data);
            Flux::toast('Ammo load created.', variant: 'success');
        }

        $this->resetAmmoForm();
        $this->showAmmoForm = false;
    }

    public function deleteAmmo(int $id): void
    {
        AmmoLoad::whereHas('rifle', fn ($q) => $q->where('user_id', auth()->id()))
            ->findOrFail($id)->delete();
        Flux::toast('Ammo load deleted.', variant: 'success');
    }

    public function cancelAmmo(): void
    {
        $this->resetAmmoForm();
        $this->showAmmoForm = false;
    }

    private function resetAmmoForm(): void
    {
        $this->editingAmmoId = null;
        $this->ammoRifleId = null;
        $this->ammoName = '';
        $this->bullet_brand_type = '';
        $this->bullet_weight = '';
        $this->muzzle_velocity = '';
        $this->ammoIsDefault = false;
        $this->resetValidation();
    }

    public function with(): array
    {
        return [
            'rifles' => auth()->user()->rifles()
                ->with(['ammoLoads' => fn ($q) => $q->orderByDesc('is_default')->orderBy('name')])
                ->orderByDesc('is_default')->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="space-y-6 max-w-4xl">
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">My Rifles</flux:heading>
            <p class="mt-1 text-base text-muted">Manage your rifles and ammo loads. Pick a setup when registering for a match.</p>
        </div>
        @unless($showRifleForm)
            <flux:button wire:click="createRifle" variant="primary" class="!bg-accent hover:!bg-accent-hover min-h-[44px] focus:ring-2 focus:ring-accent focus:outline-none">
                <svg class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Add New Rifle
            </flux:button>
        @endunless
    </div>

    {{-- ── Rifle Form ───────────────────────────────── --}}
    @if($showRifleForm)
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <h2 class="text-lg font-semibold text-primary">{{ $editingRifleId ? 'Edit Rifle' : 'New Rifle' }}</h2>

            <form wire:submit="saveRifle" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-secondary mb-1">Profile Name *</label>
                    <input type="text" wire:model="rifleName" placeholder="e.g. PRS Rig, Royal Flush Setup"
                           class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-base text-primary placeholder-muted focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent" />
                    @error('rifleName') <p class="mt-1 text-xs text-accent">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Caliber</label>
                        <input type="text" wire:model="caliber" list="dl-calibers" placeholder="Start typing…"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-base text-primary placeholder-muted focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Action Brand</label>
                        <input type="text" wire:model="action_brand" list="dl-actions" placeholder="Start typing…"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-base text-primary placeholder-muted focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Barrel Brand & Length</label>
                        <input type="text" wire:model="barrel_brand_length" list="dl-barrels" placeholder="Start typing…"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-base text-primary placeholder-muted focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Trigger Brand</label>
                        <input type="text" wire:model="trigger_brand" list="dl-triggers" placeholder="Start typing…"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-base text-primary placeholder-muted focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Stock / Chassis Brand</label>
                        <input type="text" wire:model="stock_chassis_brand" list="dl-stocks" placeholder="Start typing…"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-base text-primary placeholder-muted focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Muzzle Brake / Silencer Brand</label>
                        <input type="text" wire:model="muzzle_brake_silencer_brand" list="dl-muzzle" placeholder="Start typing…"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-base text-primary placeholder-muted focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Scope Brand & Type</label>
                        <input type="text" wire:model="scope_brand_type" list="dl-scopes" placeholder="Start typing…"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-base text-primary placeholder-muted focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Scope Mount Brand</label>
                        <input type="text" wire:model="scope_mount_brand" list="dl-mounts" placeholder="Start typing…"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-base text-primary placeholder-muted focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Bipod Brand</label>
                        <input type="text" wire:model="bipod_brand" list="dl-bipods" placeholder="Start typing…"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-base text-primary placeholder-muted focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent" />
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-secondary">
                    <input type="checkbox" wire:model="rifleIsDefault" class="rounded border-border bg-surface-2 text-accent focus:outline-none focus:ring-2 focus:ring-accent">
                    Set as default rifle
                </label>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover min-h-[44px]"
                                 wire:loading.attr="disabled" wire:target="saveRifle">
                        <span wire:loading.remove wire:target="saveRifle">{{ $editingRifleId ? 'Update Rifle' : 'Save Rifle' }}</span>
                        <span wire:loading wire:target="saveRifle">Saving…</span>
                    </flux:button>
                    <flux:button wire:click="cancelRifle" variant="ghost" class="min-h-[44px] focus:ring-2 focus:ring-accent focus:outline-none">Cancel</flux:button>
                </div>
            </form>
        </div>
    @endif

    {{-- ── Ammo Form ────────────────────────────────── --}}
    @if($showAmmoForm)
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <h2 class="text-lg font-semibold text-primary">{{ $editingAmmoId ? 'Edit Ammo Load' : 'New Ammo Load' }}</h2>

            <form wire:submit="saveAmmo" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-secondary mb-1">Load Name *</label>
                    <input type="text" wire:model="ammoName" placeholder="e.g. Match Ammo, Practice Load"
                           class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-base text-primary placeholder-muted focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent" />
                    @error('ammoName') <p class="mt-1 text-xs text-accent">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Bullet Brand & Type</label>
                        <input type="text" wire:model="bullet_brand_type" list="dl-bullets" placeholder="Start typing…"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-base text-primary placeholder-muted focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Bullet Weight</label>
                        <input type="text" wire:model="bullet_weight" placeholder="e.g. 175gr"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-base text-primary placeholder-muted focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Muzzle Velocity</label>
                        <input type="text" wire:model="muzzle_velocity" placeholder="e.g. 2600 fps"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-base text-primary placeholder-muted focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent" />
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-secondary">
                    <input type="checkbox" wire:model="ammoIsDefault" class="rounded border-border bg-surface-2 text-accent focus:outline-none focus:ring-2 focus:ring-accent">
                    Set as default ammo for this rifle
                </label>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover min-h-[44px]"
                                 wire:loading.attr="disabled" wire:target="saveAmmo">
                        <span wire:loading.remove wire:target="saveAmmo">{{ $editingAmmoId ? 'Update Ammo Load' : 'Save Ammo Load' }}</span>
                        <span wire:loading wire:target="saveAmmo">Saving…</span>
                    </flux:button>
                    <flux:button wire:click="cancelAmmo" variant="ghost" class="min-h-[44px] focus:ring-2 focus:ring-accent focus:outline-none">Cancel</flux:button>
                </div>
            </form>
        </div>
    @endif

    {{-- ── Rifle Cards ──────────────────────────────── --}}
    @if($rifles->isEmpty() && !$showRifleForm)
        <div class="rounded-xl border border-dashed border-border bg-surface/50 px-6 py-12 text-center">
            <svg class="mx-auto h-10 w-10 text-muted/50" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437" /></svg>
            <h3 class="mt-3 text-base font-semibold text-primary">No rifles yet</h3>
            <p class="mt-1 text-base text-muted">Add your first rifle and its ammo loads so you can quickly select them during match registration.</p>
            <flux:button wire:click="createRifle" variant="primary" class="mt-4 !bg-accent hover:!bg-accent-hover min-h-[44px] focus:ring-2 focus:ring-accent focus:outline-none" size="sm">
                Add Your First Rifle
            </flux:button>
        </div>
    @else
        <div class="space-y-4">
            @foreach($rifles as $rifle)
                <div class="rounded-xl border border-border bg-surface p-5 space-y-4">
                    {{-- Rifle header --}}
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-base font-semibold text-primary">{{ $rifle->name }}</h3>
                                @if($rifle->caliber)
                                    <span class="text-sm text-secondary">{{ $rifle->caliber }}</span>
                                @endif
                                @if($rifle->is_default)
                                    <span class="rounded-full bg-accent/20 px-2 py-0.5 text-[10px] font-bold uppercase text-accent">Default</span>
                                @endif
                            </div>
                            @php
                                $summary = array_filter([
                                    $rifle->action_brand,
                                    $rifle->barrel_brand_length,
                                    $rifle->scope_brand_type,
                                    $rifle->trigger_brand,
                                    $rifle->stock_chassis_brand,
                                    $rifle->muzzle_brake_silencer_brand,
                                    $rifle->scope_mount_brand,
                                    $rifle->bipod_brand,
                                ]);
                            @endphp
                            @if($summary)
                                <p class="mt-1 text-xs text-muted">{{ implode(' · ', $summary) }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <flux:button wire:click="createAmmo({{ $rifle->id }})" variant="ghost" size="sm" class="min-h-[44px] focus:ring-2 focus:ring-accent focus:outline-none">+ Ammo</flux:button>
                            <flux:button wire:click="editRifle({{ $rifle->id }})" variant="ghost" size="sm" class="min-h-[44px] focus:ring-2 focus:ring-accent focus:outline-none">Edit</flux:button>
                            <flux:button wire:click="deleteRifle({{ $rifle->id }})" variant="ghost" size="sm" class="!text-red-400 hover:!text-red-300 min-h-[44px] focus:ring-2 focus:ring-accent focus:outline-none"
                                         wire:confirm="Delete '{{ $rifle->name }}' and all its ammo loads?">Delete</flux:button>
                        </div>
                    </div>

                    {{-- Ammo loads --}}
                    @if($rifle->ammoLoads->isNotEmpty())
                        <div class="border-t border-border pt-3">
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted mb-2">Ammo Loads</h4>
                            <div class="space-y-2">
                                @foreach($rifle->ammoLoads as $ammo)
                                    <div class="flex items-center justify-between gap-3 rounded-lg border border-border bg-surface-2 px-4 py-2.5">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-base font-medium text-primary">{{ $ammo->name }}</span>
                                                @if($ammo->is_default)
                                                    <span class="rounded-full bg-accent/20 px-2 py-0.5 text-[10px] font-bold uppercase text-accent">Default</span>
                                                @endif
                                            </div>
                                            @php
                                                $ammoDetails = array_filter([
                                                    $ammo->bullet_brand_type,
                                                    $ammo->bullet_weight,
                                                    $ammo->muzzle_velocity ? "@ {$ammo->muzzle_velocity}" : null,
                                                ]);
                                            @endphp
                                            @if($ammoDetails)
                                                <p class="text-xs text-muted">{{ implode(' · ', $ammoDetails) }}</p>
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                                            <flux:button wire:click="editAmmo({{ $ammo->id }})" variant="ghost" size="sm" class="min-h-[44px] focus:ring-2 focus:ring-accent focus:outline-none">Edit</flux:button>
                                            <flux:button wire:click="deleteAmmo({{ $ammo->id }})" variant="ghost" size="sm" class="min-h-[44px] !text-red-400 hover:!text-red-300 focus:ring-2 focus:ring-accent focus:outline-none"
                                                         wire:confirm="Delete ammo load '{{ $ammo->name }}'?">Delete</flux:button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- ── Autocomplete datalists (from NRAPA/SARP reference) ── --}}
    <datalist id="dl-calibers">
        @foreach(array_unique(array_merge(config('equipment-suggestions.calibers', []), config('equipment-suggestions.caliber_aliases', []))) as $v)
            <option value="{{ $v }}">
        @endforeach
    </datalist>
    <datalist id="dl-actions">
        @foreach(config('equipment-suggestions.action_brands', []) as $v)<option value="{{ $v }}">@endforeach
    </datalist>
    <datalist id="dl-barrels">
        @foreach(config('equipment-suggestions.barrel_brands', []) as $v)<option value="{{ $v }}">@endforeach
    </datalist>
    <datalist id="dl-triggers">
        @foreach(config('equipment-suggestions.trigger_brands', []) as $v)<option value="{{ $v }}">@endforeach
    </datalist>
    <datalist id="dl-stocks">
        @foreach(config('equipment-suggestions.stock_chassis_brands', []) as $v)<option value="{{ $v }}">@endforeach
    </datalist>
    <datalist id="dl-muzzle">
        @foreach(config('equipment-suggestions.muzzle_brake_silencer_brands', []) as $v)<option value="{{ $v }}">@endforeach
    </datalist>
    <datalist id="dl-scopes">
        @foreach(config('equipment-suggestions.scope_brands', []) as $v)<option value="{{ $v }}">@endforeach
    </datalist>
    <datalist id="dl-mounts">
        @foreach(config('equipment-suggestions.scope_mount_brands', []) as $v)<option value="{{ $v }}">@endforeach
    </datalist>
    <datalist id="dl-bipods">
        @foreach(config('equipment-suggestions.bipod_brands', []) as $v)<option value="{{ $v }}">@endforeach
    </datalist>
    <datalist id="dl-bullets">
        @foreach(config('equipment-suggestions.bullet_brands', []) as $v)<option value="{{ $v }}">@endforeach
    </datalist>
</div>
