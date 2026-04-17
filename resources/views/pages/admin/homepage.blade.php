<?php

use App\Models\FeaturedItem;
use App\Models\Organization;
use App\Models\ShootingMatch;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Homepage Curation')]
    class extends Component {
    public string $newMatchId = '';
    public string $newMatchPlacement = 'featured';
    public string $newOrgId = '';
    public string $newOrgPlacement = 'featured';

    public function addMatch(): void
    {
        $this->validate([
            'newMatchId' => 'required|exists:matches,id',
            'newMatchPlacement' => 'required|in:featured,popular,promoted',
        ]);

        $maxSort = FeaturedItem::ofType('match')->inPlacement($this->newMatchPlacement)->max('sort_order') ?? 0;

        FeaturedItem::create([
            'type' => 'match',
            'item_id' => $this->newMatchId,
            'placement' => $this->newMatchPlacement,
            'sort_order' => $maxSort + 1,
            'active' => true,
        ]);

        $this->newMatchId = '';
        Flux::toast('Match added to homepage.', variant: 'success');
    }

    public function addOrganization(): void
    {
        $this->validate([
            'newOrgId' => 'required|exists:organizations,id',
            'newOrgPlacement' => 'required|in:featured,popular,promoted',
        ]);

        $maxSort = FeaturedItem::ofType('organization')->inPlacement($this->newOrgPlacement)->max('sort_order') ?? 0;

        FeaturedItem::create([
            'type' => 'organization',
            'item_id' => $this->newOrgId,
            'placement' => $this->newOrgPlacement,
            'sort_order' => $maxSort + 1,
            'active' => true,
        ]);

        $this->newOrgId = '';
        Flux::toast('Organization added to homepage.', variant: 'success');
    }

    public function toggleActive(int $id): void
    {
        $item = FeaturedItem::findOrFail($id);
        $item->update(['active' => ! $item->active]);
    }

    public function moveUp(int $id): void
    {
        $item = FeaturedItem::findOrFail($id);
        $prev = FeaturedItem::where('type', $item->type)
            ->where('placement', $item->placement)
            ->where('sort_order', '<', $item->sort_order)
            ->orderByDesc('sort_order')
            ->first();

        if ($prev) {
            $tmp = $item->sort_order;
            $item->update(['sort_order' => $prev->sort_order]);
            $prev->update(['sort_order' => $tmp]);
        }
    }

    public function moveDown(int $id): void
    {
        $item = FeaturedItem::findOrFail($id);
        $next = FeaturedItem::where('type', $item->type)
            ->where('placement', $item->placement)
            ->where('sort_order', '>', $item->sort_order)
            ->orderBy('sort_order')
            ->first();

        if ($next) {
            $tmp = $item->sort_order;
            $item->update(['sort_order' => $next->sort_order]);
            $next->update(['sort_order' => $tmp]);
        }
    }

    public function remove(int $id): void
    {
        FeaturedItem::findOrFail($id)->delete();
        Flux::toast('Item removed.', variant: 'success');
    }

    public function with(): array
    {
        return [
            'featuredMatches' => FeaturedItem::ofType('match')->ordered()->get(),
            'featuredOrgs' => FeaturedItem::ofType('organization')->ordered()->get(),
            'availableMatches' => ShootingMatch::orderByDesc('date')->take(50)->get(),
            'availableOrgs' => Organization::active()->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="space-y-8 max-w-4xl">
    <x-admin-tab-bar :tabs="[
        ['href' => route('admin.settings'), 'label' => 'General', 'active' => false],
        ['href' => route('admin.homepage'), 'label' => 'Homepage', 'active' => true],
        ['href' => route('admin.contact-submissions'), 'label' => 'Contact Inbox', 'active' => false],
    ]" />

    <div>
        <h1 class="text-2xl font-bold text-white">Homepage Curation</h1>
        <p class="mt-1 text-sm text-secondary">Control which matches and organizations appear on the shooter landing page.</p>
    </div>

    {{-- Featured Matches --}}
    <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
        <h2 class="text-lg font-semibold text-white">Featured Matches</h2>
        <p class="text-sm text-muted">These matches are shown prominently on the shooter homepage. Drag to reorder or use the arrows.</p>

        @if($featuredMatches->count())
            <div class="space-y-2">
                @foreach($featuredMatches as $fi)
                    @php $match = \App\Models\ShootingMatch::find($fi->item_id); @endphp
                    <div class="flex items-center gap-3 rounded-lg px-4 py-3 {{ $fi->active ? 'bg-surface-2' : 'bg-surface-2/50 opacity-60' }}">
                        <div class="flex flex-col gap-0.5">
                            <button wire:click="moveUp({{ $fi->id }})" class="text-muted hover:text-white transition-colors" title="Move up">
                                <x-icon name="chevron-up" class="h-3.5 w-3.5" />
                            </button>
                            <button wire:click="moveDown({{ $fi->id }})" class="text-muted hover:text-white transition-colors" title="Move down">
                                <x-icon name="chevron-down" class="h-3.5 w-3.5" />
                            </button>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate">{{ $match?->name ?? 'Deleted match' }}</p>
                            <p class="text-xs text-muted">{{ $fi->placement }} &middot; {{ $match?->date?->format('d M Y') }}</p>
                        </div>
                        <span class="rounded px-2 py-0.5 text-[10px] font-bold uppercase {{ $fi->active ? 'bg-emerald-600/20 text-emerald-400' : 'bg-zinc-600/20 text-zinc-400' }}">
                            {{ $fi->active ? 'Active' : 'Hidden' }}
                        </span>
                        <button wire:click="toggleActive({{ $fi->id }})" class="text-xs text-secondary hover:text-white transition-colors" title="Toggle visibility">
                            @if($fi->active) <x-icon name="eye" class="h-4 w-4" /> @else <x-icon name="eye-off" class="h-4 w-4" /> @endif
                        </button>
                        <button wire:click="remove({{ $fi->id }})" wire:confirm="Remove this item?" class="text-xs text-accent hover:text-accent-hover transition-colors" title="Remove">
                            <x-icon name="trash-2" class="h-4 w-4" />
                        </button>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-muted py-4 text-center">No featured matches yet. Add one below.</p>
        @endif

        <form wire:submit="addMatch" class="flex items-end gap-3 pt-2 border-t border-border">
            <div class="flex-1">
                <flux:select wire:model="newMatchId" label="Match" placeholder="Select a match...">
                    <option value="">Select a match...</option>
                    @foreach($availableMatches as $m)
                        <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->date?->format('d M Y') }})</option>
                    @endforeach
                </flux:select>
            </div>
            <div class="w-36">
                <flux:select wire:model="newMatchPlacement" label="Placement">
                    <option value="featured">Featured</option>
                    <option value="popular">Popular</option>
                    <option value="promoted">Promoted</option>
                </flux:select>
            </div>
            <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover">
                Add
            </flux:button>
        </form>
    </div>

    {{-- Featured Organizations --}}
    <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
        <h2 class="text-lg font-semibold text-white">Featured Organizations</h2>
        <p class="text-sm text-muted">These clubs and organizations are highlighted on the shooter homepage.</p>

        @if($featuredOrgs->count())
            <div class="space-y-2">
                @foreach($featuredOrgs as $fi)
                    @php $org = \App\Models\Organization::find($fi->item_id); @endphp
                    <div class="flex items-center gap-3 rounded-lg px-4 py-3 {{ $fi->active ? 'bg-surface-2' : 'bg-surface-2/50 opacity-60' }}">
                        <div class="flex flex-col gap-0.5">
                            <button wire:click="moveUp({{ $fi->id }})" class="text-muted hover:text-white transition-colors" title="Move up">
                                <x-icon name="chevron-up" class="h-3.5 w-3.5" />
                            </button>
                            <button wire:click="moveDown({{ $fi->id }})" class="text-muted hover:text-white transition-colors" title="Move down">
                                <x-icon name="chevron-down" class="h-3.5 w-3.5" />
                            </button>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate">{{ $org?->name ?? 'Deleted organization' }}</p>
                            <p class="text-xs text-muted">{{ $fi->placement }} &middot; {{ $org?->type }}</p>
                        </div>
                        <span class="rounded px-2 py-0.5 text-[10px] font-bold uppercase {{ $fi->active ? 'bg-emerald-600/20 text-emerald-400' : 'bg-zinc-600/20 text-zinc-400' }}">
                            {{ $fi->active ? 'Active' : 'Hidden' }}
                        </span>
                        <button wire:click="toggleActive({{ $fi->id }})" class="text-xs text-secondary hover:text-white transition-colors" title="Toggle visibility">
                            @if($fi->active) <x-icon name="eye" class="h-4 w-4" /> @else <x-icon name="eye-off" class="h-4 w-4" /> @endif
                        </button>
                        <button wire:click="remove({{ $fi->id }})" wire:confirm="Remove this item?" class="text-xs text-accent hover:text-accent-hover transition-colors" title="Remove">
                            <x-icon name="trash-2" class="h-4 w-4" />
                        </button>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-muted py-4 text-center">No featured organizations yet. Add one below.</p>
        @endif

        <form wire:submit="addOrganization" class="flex items-end gap-3 pt-2 border-t border-border">
            <div class="flex-1">
                <flux:select wire:model="newOrgId" label="Organization" placeholder="Select an organization...">
                    <option value="">Select an organization...</option>
                    @foreach($availableOrgs as $o)
                        <option value="{{ $o->id }}">{{ $o->name }} ({{ $o->type }})</option>
                    @endforeach
                </flux:select>
            </div>
            <div class="w-36">
                <flux:select wire:model="newOrgPlacement" label="Placement">
                    <option value="featured">Featured</option>
                    <option value="popular">Popular</option>
                    <option value="promoted">Promoted</option>
                </flux:select>
            </div>
            <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover">
                Add
            </flux:button>
        </form>
    </div>
</div>
