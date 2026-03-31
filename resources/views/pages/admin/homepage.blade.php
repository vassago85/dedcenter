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
            'availableOrgs' => Organization::approved()->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="space-y-8 max-w-4xl">
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
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" /></svg>
                            </button>
                            <button wire:click="moveDown({{ $fi->id }})" class="text-muted hover:text-white transition-colors" title="Move down">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
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
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $fi->active ? 'M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z' : 'M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88' }}" /></svg>
                        </button>
                        <button wire:click="remove({{ $fi->id }})" wire:confirm="Remove this item?" class="text-xs text-accent hover:text-accent-hover transition-colors" title="Remove">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
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
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" /></svg>
                            </button>
                            <button wire:click="moveDown({{ $fi->id }})" class="text-muted hover:text-white transition-colors" title="Move down">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
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
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $fi->active ? 'M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z' : 'M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88' }}" /></svg>
                        </button>
                        <button wire:click="remove({{ $fi->id }})" wire:confirm="Remove this item?" class="text-xs text-accent hover:text-accent-hover transition-colors" title="Remove">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
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
