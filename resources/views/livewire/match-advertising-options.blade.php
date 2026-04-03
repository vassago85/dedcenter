<div class="space-y-6">
    @unless($advertisingEnabled)
        <div class="rounded-lg border border-zinc-700/50 bg-zinc-800/30 p-5">
            <p class="text-sm text-muted">Advertising is currently disabled. A site administrator can enable it from the Advertising dashboard.</p>
        </div>
    @else
    @if($match->md_package_status === \App\Enums\MdPackageStatus::Pending)
        {{-- MD hasn't decided yet --}}
        <div class="rounded-lg border border-amber-500/30 bg-amber-500/5 p-5 space-y-4">
            <div>
                <h3 class="text-base font-semibold text-primary">Full Event Visibility Package — R{{ number_format($match->md_package_price, 0) }}</h3>
                <p class="mt-1 text-sm text-muted">Take control of all advertising placements for this event with a single brand.</p>
            </div>

            <ul class="text-sm text-secondary space-y-1 ml-4 list-disc">
                <li>Leaderboard powered by your brand</li>
                <li>Results powered by your brand</li>
                <li>Scoring powered by your brand</li>
            </ul>

            <div class="pt-2">
                <flux:select wire:model="selectedBrandId" label="Select brand">
                    <option value="">— Choose a brand —</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <flux:button wire:click="takePackage" variant="primary" size="sm">Take Package</flux:button>
                <flux:button wire:click="declinePackage" variant="ghost" size="sm">Decline</flux:button>
            </div>
        </div>

    @elseif($match->md_package_status === \App\Enums\MdPackageStatus::Taken)
        {{-- MD took the package --}}
        <div class="rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-emerald-400">Full Package Active</h3>
                <span class="inline-flex items-center rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-400">Active</span>
            </div>

            @if($currentBrand)
                <div class="space-y-2">
                    <p class="text-sm text-secondary">All placements assigned to <span class="font-semibold text-primary">{{ $currentBrand->name }}</span></p>
                    <div class="text-sm text-muted space-y-1 ml-4 list-disc">
                        @foreach($advertisingPlacements as $key)
                            <p>{{ $key->poweredByLabel() }} powered by {{ $currentBrand->name }}</p>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="pt-2 space-y-3">
                <flux:select wire:model="selectedBrandId" label="Change brand">
                    <option value="">— Choose a brand —</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                    @endforeach
                </flux:select>

                <div class="flex items-center gap-3">
                    <flux:button wire:click="changeBrand" variant="primary" size="sm">Update Brand</flux:button>
                    <flux:button wire:click="releasePackage" variant="danger" size="sm">Release All Placements</flux:button>
                </div>
            </div>
        </div>

    @else
        {{-- Declined or Expired — placements are public --}}
        <div class="rounded-lg border border-slate-500/30 bg-slate-500/5 p-5 space-y-3">
            <h3 class="text-base font-semibold text-primary">Advertising Placements</h3>
            <p class="text-sm text-muted">Advertising placements for this event are available to public advertisers.</p>

            <div class="text-sm text-secondary space-y-1">
                @php
                    $sold = $match->soldPlacementKeys();
                    $soldValues = collect($sold)->map(fn($k) => $k->value)->all();
                @endphp

                @foreach($advertisingPlacements as $key)
                    <div class="flex items-center gap-2">
                        <span class="w-5 text-center">
                            @if(in_array($key->value, $soldValues))
                                <span class="text-emerald-400">&#10003;</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </span>
                        <span>{{ $key->poweredByLabel() }}</span>
                        @if(in_array($key->value, $soldValues))
                            <span class="text-xs text-emerald-400">(Sold)</span>
                        @else
                            <span class="text-xs text-muted">(Available — R{{ number_format($match->individual_placement_price, 0) }})</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    @endunless
</div>
