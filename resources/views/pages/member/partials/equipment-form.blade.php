<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label class="block text-sm font-medium text-secondary mb-1">SA ID Number</label>
        <input type="text" wire:model="sa_id_number" placeholder="Optional"
               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
    </div>
    <div>
        <label class="block text-sm font-medium text-secondary mb-1">Contact Number *</label>
        <input type="text" wire:model="contact_number" placeholder="e.g. 071 480 7251" required
               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
        @error('contact_number') <p class="mt-1 text-xs text-accent">{{ $message }}</p> @enderror
    </div>
</div>

<div class="border-t border-border pt-4">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-primary">Rifle & Equipment</h3>
        @auth
            @if(auth()->user()->equipmentProfiles()->exists())
                <div class="flex items-center gap-2">
                    <label class="text-xs text-muted">Load profile:</label>
                    <select wire:change="loadProfile($event.target.value)"
                            class="rounded-lg border border-border bg-surface-2 px-2 py-1 text-xs text-primary focus:border-red-500 focus:ring-1 focus:ring-red-500">
                        <option value="">-- Select --</option>
                        @foreach(auth()->user()->equipmentProfiles()->orderByDesc('is_default')->orderBy('name')->get() as $ep)
                            <option value="{{ $ep->id }}">{{ $ep->name }}{{ $ep->is_default ? ' (default)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        @endauth
    </div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-secondary mb-1">Caliber *</label>
            <input type="text" wire:model="caliber" placeholder="e.g. .308 Win" required
                   class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
            @error('caliber') <p class="mt-1 text-xs text-accent">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-secondary mb-1">Action Brand</label>
            <input type="text" wire:model="action_brand" placeholder="Optional"
                   class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
        </div>
        <div>
            <label class="block text-sm font-medium text-secondary mb-1">Bullet Brand & Type *</label>
            <input type="text" wire:model="bullet_brand_type" placeholder="e.g. Lapua Scenar" required
                   class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
            @error('bullet_brand_type') <p class="mt-1 text-xs text-accent">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-secondary mb-1">Bullet Weight *</label>
            <input type="text" wire:model="bullet_weight" placeholder="e.g. 175gr" required
                   class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
            @error('bullet_weight') <p class="mt-1 text-xs text-accent">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-secondary mb-1">Barrel Brand & Length *</label>
            <input type="text" wire:model="barrel_brand_length" placeholder="e.g. Krieger 26&quot;" required
                   class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
            @error('barrel_brand_length') <p class="mt-1 text-xs text-accent">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-secondary mb-1">Trigger Brand *</label>
            <input type="text" wire:model="trigger_brand" placeholder="e.g. Triggertech Diamond" required
                   class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
            @error('trigger_brand') <p class="mt-1 text-xs text-accent">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-secondary mb-1">Stock / Chassis Brand *</label>
            <input type="text" wire:model="stock_chassis_brand" placeholder="e.g. MDT ACC" required
                   class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
            @error('stock_chassis_brand') <p class="mt-1 text-xs text-accent">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-secondary mb-1">Muzzle Brake / Silencer Brand *</label>
            <input type="text" wire:model="muzzle_brake_silencer_brand" placeholder="e.g. Area 419 Hellfire" required
                   class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
            @error('muzzle_brake_silencer_brand') <p class="mt-1 text-xs text-accent">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-secondary mb-1">Scope Brand & Type *</label>
            <input type="text" wire:model="scope_brand_type" placeholder="e.g. Nightforce ATACR" required
                   class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
            @error('scope_brand_type') <p class="mt-1 text-xs text-accent">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-secondary mb-1">Scope Mount Brand *</label>
            <input type="text" wire:model="scope_mount_brand" placeholder="e.g. Spuhr" required
                   class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
            @error('scope_mount_brand') <p class="mt-1 text-xs text-accent">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-secondary mb-1">Bipod Brand *</label>
            <input type="text" wire:model="bipod_brand" placeholder="e.g. Atlas CAL" required
                   class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
            @error('bipod_brand') <p class="mt-1 text-xs text-accent">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-secondary mb-1">Share Rifle with?</label>
            <input type="text" wire:model="share_rifle_with" placeholder="Name of person (leave blank if N/A)"
                   class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
            <p class="mt-1 text-xs text-muted">If sharing, you won't be placed in relays that shoot at the same time.</p>
        </div>
    </div>
</div>

{{-- Custom Registration Fields --}}
@if(isset($match) && $match->customFields->isNotEmpty())
    <div class="border-t border-border pt-4">
        <h3 class="text-sm font-semibold text-primary mb-3">Additional Information</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach($match->customFields()->orderBy('sort_order')->get() as $cf)
                <div @if($cf->type === 'checkbox') class="sm:col-span-2" @endif>
                    <label class="block text-sm font-medium text-secondary mb-1">
                        {{ $cf->label }}{{ $cf->is_required ? ' *' : '' }}
                    </label>

                    @if($cf->type === 'text')
                        <input type="text" wire:model="customFieldValues.{{ $cf->id }}"
                               {{ $cf->is_required ? 'required' : '' }}
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                    @elseif($cf->type === 'number')
                        <input type="number" wire:model="customFieldValues.{{ $cf->id }}"
                               {{ $cf->is_required ? 'required' : '' }}
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                    @elseif($cf->type === 'select')
                        <select wire:model="customFieldValues.{{ $cf->id }}"
                                {{ $cf->is_required ? 'required' : '' }}
                                class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-red-500 focus:ring-1 focus:ring-red-500">
                            <option value="">-- Select --</option>
                            @foreach($cf->options ?? [] as $opt)
                                <option value="{{ $opt }}">{{ $opt }}</option>
                            @endforeach
                        </select>
                    @elseif($cf->type === 'checkbox')
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="customFieldValues.{{ $cf->id }}"
                                   class="rounded border-border bg-surface-2 text-accent focus:ring-accent">
                            <span class="text-sm text-muted">{{ $cf->label }}</span>
                        </label>
                    @endif

                    @error('customFieldValues.' . $cf->id) <p class="mt-1 text-xs text-accent">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
    </div>
@endif
