<?php

use App\Models\UserEquipmentProfile;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Flux\Flux;

new #[Layout('components.layouts.app')]
    class extends Component {

    public ?int $editingId = null;

    public string $name = '';
    public string $caliber = '';
    public string $action_brand = '';
    public string $bullet_brand_type = '';
    public string $bullet_weight = '';
    public string $barrel_brand_length = '';
    public string $trigger_brand = '';
    public string $stock_chassis_brand = '';
    public string $muzzle_brake_silencer_brand = '';
    public string $scope_brand_type = '';
    public string $scope_mount_brand = '';
    public string $bipod_brand = '';
    public bool $is_default = false;

    public bool $showForm = false;

    public function getTitle(): string
    {
        return 'My Equipment — DeadCenter';
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $profile = auth()->user()->equipmentProfiles()->findOrFail($id);
        $this->editingId = $profile->id;
        $this->name = $profile->name;
        $this->caliber = $profile->caliber ?? '';
        $this->action_brand = $profile->action_brand ?? '';
        $this->bullet_brand_type = $profile->bullet_brand_type ?? '';
        $this->bullet_weight = $profile->bullet_weight ?? '';
        $this->barrel_brand_length = $profile->barrel_brand_length ?? '';
        $this->trigger_brand = $profile->trigger_brand ?? '';
        $this->stock_chassis_brand = $profile->stock_chassis_brand ?? '';
        $this->muzzle_brake_silencer_brand = $profile->muzzle_brake_silencer_brand ?? '';
        $this->scope_brand_type = $profile->scope_brand_type ?? '';
        $this->scope_mount_brand = $profile->scope_mount_brand ?? '';
        $this->bipod_brand = $profile->bipod_brand ?? '';
        $this->is_default = $profile->is_default;
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:100',
            'caliber' => 'nullable|string|max:255',
            'action_brand' => 'nullable|string|max:255',
            'bullet_brand_type' => 'nullable|string|max:255',
            'bullet_weight' => 'nullable|string|max:100',
            'barrel_brand_length' => 'nullable|string|max:255',
            'trigger_brand' => 'nullable|string|max:255',
            'stock_chassis_brand' => 'nullable|string|max:255',
            'muzzle_brake_silencer_brand' => 'nullable|string|max:255',
            'scope_brand_type' => 'nullable|string|max:255',
            'scope_mount_brand' => 'nullable|string|max:255',
            'bipod_brand' => 'nullable|string|max:255',
            'is_default' => 'boolean',
        ]);

        if ($this->is_default) {
            auth()->user()->equipmentProfiles()
                ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
                ->update(['is_default' => false]);
        }

        if ($this->editingId) {
            $profile = auth()->user()->equipmentProfiles()->findOrFail($this->editingId);
            $profile->update($validated);
            Flux::toast('Equipment profile updated.', variant: 'success');
        } else {
            auth()->user()->equipmentProfiles()->create($validated);
            Flux::toast('Equipment profile created.', variant: 'success');
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        auth()->user()->equipmentProfiles()->findOrFail($id)->delete();
        Flux::toast('Profile deleted.', variant: 'success');
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->caliber = '';
        $this->action_brand = '';
        $this->bullet_brand_type = '';
        $this->bullet_weight = '';
        $this->barrel_brand_length = '';
        $this->trigger_brand = '';
        $this->stock_chassis_brand = '';
        $this->muzzle_brake_silencer_brand = '';
        $this->scope_brand_type = '';
        $this->scope_mount_brand = '';
        $this->bipod_brand = '';
        $this->is_default = false;
        $this->resetValidation();
    }

    public function with(): array
    {
        return [
            'profiles' => auth()->user()->equipmentProfiles()->orderByDesc('is_default')->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="space-y-6 max-w-4xl">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">My Equipment</flux:heading>
            <p class="mt-1 text-sm text-muted">Save your rifle and equipment setups. Pick a profile when registering for a match.</p>
        </div>
        @unless($showForm)
            <flux:button wire:click="create" variant="primary" class="!bg-accent hover:!bg-accent-hover">
                <svg class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                New Profile
            </flux:button>
        @endunless
    </div>

    {{-- Form --}}
    @if($showForm)
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <h2 class="text-lg font-semibold text-primary">{{ $editingId ? 'Edit Profile' : 'New Equipment Profile' }}</h2>

            <form wire:submit="save" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-secondary mb-1">Profile Name *</label>
                    <input type="text" wire:model="name" placeholder="e.g. PRS Rig, Royal Flush Setup"
                           class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                    @error('name') <p class="mt-1 text-xs text-accent">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Caliber</label>
                        <input type="text" wire:model="caliber" placeholder="e.g. .308 Win"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Action Brand</label>
                        <input type="text" wire:model="action_brand" placeholder="Optional"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Bullet Brand & Type</label>
                        <input type="text" wire:model="bullet_brand_type" placeholder="e.g. Lapua Scenar"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Bullet Weight</label>
                        <input type="text" wire:model="bullet_weight" placeholder="e.g. 175gr"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Barrel Brand & Length</label>
                        <input type="text" wire:model="barrel_brand_length" placeholder="e.g. Krieger 26&quot;"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Trigger Brand</label>
                        <input type="text" wire:model="trigger_brand" placeholder="e.g. Triggertech Diamond"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Stock / Chassis Brand</label>
                        <input type="text" wire:model="stock_chassis_brand" placeholder="e.g. MDT ACC"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Muzzle Brake / Silencer Brand</label>
                        <input type="text" wire:model="muzzle_brake_silencer_brand" placeholder="e.g. Area 419 Hellfire"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Scope Brand & Type</label>
                        <input type="text" wire:model="scope_brand_type" placeholder="e.g. Nightforce ATACR"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Scope Mount Brand</label>
                        <input type="text" wire:model="scope_mount_brand" placeholder="e.g. Spuhr"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary mb-1">Bipod Brand</label>
                        <input type="text" wire:model="bipod_brand" placeholder="e.g. Atlas CAL"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-secondary">
                    <input type="checkbox" wire:model="is_default" class="rounded border-border bg-surface-2 text-accent focus:ring-accent">
                    Set as default profile
                </label>

                <div class="flex items-center gap-3 pt-2">
                    <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover">
                        {{ $editingId ? 'Update Profile' : 'Save Profile' }}
                    </flux:button>
                    <flux:button wire:click="cancel" variant="ghost">Cancel</flux:button>
                </div>
            </form>
        </div>
    @endif

    {{-- Profile list --}}
    @if($profiles->isEmpty() && !$showForm)
        <div class="rounded-xl border border-dashed border-border bg-surface/50 px-6 py-12 text-center">
            <svg class="mx-auto h-10 w-10 text-muted/50" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437" /></svg>
            <h3 class="mt-3 text-sm font-semibold text-primary">No equipment profiles yet</h3>
            <p class="mt-1 text-xs text-muted">Create a profile for each rifle setup so you can quickly fill in equipment during match registration.</p>
            <flux:button wire:click="create" variant="primary" class="mt-4 !bg-accent hover:!bg-accent-hover" size="sm">
                Create Your First Profile
            </flux:button>
        </div>
    @else
        <div class="space-y-3">
            @foreach($profiles as $profile)
                <div class="rounded-xl border border-border bg-surface p-5 flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-primary">{{ $profile->name }}</h3>
                            @if($profile->is_default)
                                <span class="rounded-full bg-accent/20 px-2 py-0.5 text-[10px] font-bold uppercase text-accent">Default</span>
                            @endif
                        </div>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                            @if($profile->caliber)
                                <span><span class="text-secondary font-medium">Caliber:</span> {{ $profile->caliber }}</span>
                            @endif
                            @if($profile->scope_brand_type)
                                <span><span class="text-secondary font-medium">Scope:</span> {{ $profile->scope_brand_type }}</span>
                            @endif
                            @if($profile->bullet_brand_type)
                                <span><span class="text-secondary font-medium">Bullet:</span> {{ $profile->bullet_brand_type }} {{ $profile->bullet_weight }}</span>
                            @endif
                            @if($profile->barrel_brand_length)
                                <span><span class="text-secondary font-medium">Barrel:</span> {{ $profile->barrel_brand_length }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <flux:button wire:click="edit({{ $profile->id }})" variant="ghost" size="sm">Edit</flux:button>
                        <flux:button wire:click="delete({{ $profile->id }})" variant="ghost" size="sm" class="!text-red-400 hover:!text-red-300"
                                     wire:confirm="Delete '{{ $profile->name }}'?">Delete</flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
