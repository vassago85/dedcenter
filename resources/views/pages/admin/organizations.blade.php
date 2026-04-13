<?php

use App\Models\Organization;
use App\Notifications\OrganizationApprovedNotification;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Organizations')]
    class extends Component {
    public string $filter = 'pending';

    public function approve(int $id): void
    {
        $org = Organization::findOrFail($id);
        $org->update(['status' => 'active']);

        $org->admins()->syncWithoutDetaching([
            $org->created_by => ['is_owner' => true],
        ]);

        $owner = $org->admins()->wherePivot('is_owner', true)->first();
        if ($owner) {
            $owner->notify(new OrganizationApprovedNotification($org));
        }

        Flux::toast("'{$org->name}' approved and active. Creator is now the owner.", variant: 'success');
    }

    public function deactivate(int $id): void
    {
        $org = Organization::findOrFail($id);
        $org->update(['status' => 'inactive']);

        Flux::toast("'{$org->name}' deactivated.", variant: 'warning');
    }

    public function reactivate(int $id): void
    {
        $org = Organization::findOrFail($id);
        $org->update(['status' => 'active']);

        Flux::toast("'{$org->name}' reactivated.", variant: 'success');
    }

    public function toggleRoyalFlush(int $id): void
    {
        $org = Organization::findOrFail($id);

        if ($org->royal_flush_enabled) {
            $org->update(['royal_flush_enabled' => false]);
            Flux::toast("Royal Flush removed from '{$org->name}'.", variant: 'warning');
        } else {
            Organization::where('royal_flush_enabled', true)->update(['royal_flush_enabled' => false]);
            $org->update(['royal_flush_enabled' => true]);
            Flux::toast("'{$org->name}' is now the Royal Flush organization.", variant: 'success');
        }
    }

    public function deleteOrganization(int $id): void
    {
        $org = Organization::withCount('matches')->findOrFail($id);

        if ($org->matches_count > 0) {
            Flux::toast("Cannot delete '{$org->name}' — it still has {$org->matches_count} match(es). Delete or reassign them first.", variant: 'danger');
            return;
        }

        $org->admins()->detach();
        $org->children()->update(['parent_id' => null]);

        $name = $org->name;
        $org->delete();

        Flux::toast("'{$name}' has been permanently deleted.", variant: 'warning');
    }

    public function togglePortalEntitlement(int $id): void
    {
        $org = Organization::findOrFail($id);

        if ($org->portal_entitled) {
            $org->update([
                'portal_entitled' => false,
                'portal_enabled' => false,
            ]);
            Flux::toast("Public portal add-on removed from '{$org->name}'. Their portal URL is now disabled.", variant: 'warning');
        } else {
            $org->update(['portal_entitled' => true]);
            Flux::toast("'{$org->name}' may now enable the public portal under Organization → Settings.", variant: 'success');
        }
    }

    public function with(): array
    {
        $organizations = Organization::with(['creator', 'parent'])
            ->withCount(['children', 'matches', 'admins'])
            ->when($this->filter !== 'all', fn ($q) => $q->where('status', $this->filter))
            ->latest()
            ->get();

        return ['organizations' => $organizations];
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">Organizations</flux:heading>
        <p class="mt-1 text-sm text-muted">Approve and manage organizations.</p>
    </div>

    {{-- Filter --}}
    <div class="flex gap-2 flex-wrap">
        @foreach(['pending' => 'Pending', 'active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $value => $label)
            <button wire:click="$set('filter', '{{ $value }}')"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors {{ $filter === $value ? 'bg-accent text-primary' : 'bg-surface-2 text-secondary hover:bg-surface-2' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Table --}}
    <div class="rounded-xl border border-border bg-surface overflow-hidden">
        @if($organizations->isEmpty())
            <div class="px-6 py-12 text-center">
                <p class="text-muted">No organizations matching this filter.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-muted">
                            <th class="px-6 py-3 font-medium">Name</th>
                            <th class="px-6 py-3 font-medium">Type</th>
                            <th class="px-6 py-3 font-medium">Parent</th>
                            <th class="px-6 py-3 font-medium">Created By</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                            <th class="px-6 py-3 font-medium text-right">Matches</th>
                            <th class="px-6 py-3 font-medium text-right">Admins</th>
                            <th class="px-6 py-3 font-medium">Date</th>
                            <th class="px-6 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        @foreach($organizations as $org)
                            <tr class="hover:bg-surface-2/30 transition-colors" wire:key="org-{{ $org->id }}">
                                <td class="px-6 py-3 font-medium text-primary">
                                    <span class="inline-flex items-center gap-1.5">
                                        {{ $org->name }}
                                        @if($org->royal_flush_enabled)
                                            <span class="text-amber-400" title="Royal Flush Organization">♛</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="px-6 py-3 capitalize text-secondary">{{ $org->type }}</td>
                                <td class="px-6 py-3 text-muted text-xs">{{ $org->parent?->name ?? '—' }}</td>
                                <td class="px-6 py-3 text-secondary">{{ $org->creator?->name ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    @switch($org->status)
                                        @case('pending')
                                            <flux:badge size="sm" color="amber">Pending</flux:badge>
                                            @break
                                        @case('active')
                                            <flux:badge size="sm" color="green">Active</flux:badge>
                                            @break
                                        @case('inactive')
                                            <flux:badge size="sm" color="zinc">Inactive</flux:badge>
                                            @break
                                    @endswitch
                                </td>
                                <td class="px-6 py-3 text-right text-secondary">{{ $org->matches_count }}</td>
                                <td class="px-6 py-3 text-right text-secondary">{{ $org->admins_count }}</td>
                                <td class="px-6 py-3 text-muted text-xs">{{ $org->created_at->format('d M Y') }}</td>
                                <td class="px-6 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($org->status === 'pending')
                                            <flux:button size="sm" variant="primary" class="!bg-green-600 hover:!bg-green-700"
                                                         wire:click="approve({{ $org->id }})"
                                                         wire:confirm="Approve '{{ $org->name }}'? The creator will become the owner.">
                                                Approve
                                            </flux:button>
                                        @elseif($org->isActive())
                                            <flux:button size="sm" variant="ghost" class="!text-secondary hover:!text-primary"
                                                         href="{{ route('org.dashboard', $org) }}">
                                                Manage
                                            </flux:button>
                                            @if($org->portal_entitled)
                                                <flux:button size="sm" variant="ghost" class="!text-sky-400 hover:!text-sky-300"
                                                             wire:click="togglePortalEntitlement({{ $org->id }})"
                                                             wire:confirm="Remove paid public portal access for '{{ $org->name }}'? Their portal will go offline.">
                                                    Remove portal
                                                </flux:button>
                                            @else
                                                <flux:button size="sm" variant="ghost" class="!text-sky-400 hover:!text-sky-300"
                                                             wire:click="togglePortalEntitlement({{ $org->id }})"
                                                             wire:confirm="Grant paid public portal access for '{{ $org->name }}'? They can then enable it in org settings.">
                                                    Grant portal
                                                </flux:button>
                                            @endif
                                            @if($org->royal_flush_enabled)
                                                <flux:button size="sm" variant="ghost" class="!text-amber-400 hover:!text-amber-300"
                                                             wire:click="toggleRoyalFlush({{ $org->id }})"
                                                             wire:confirm="Remove Royal Flush from '{{ $org->name }}'?">
                                                    Remove RF
                                                </flux:button>
                                            @else
                                                <flux:button size="sm" variant="ghost" class="!text-amber-400 hover:!text-amber-300"
                                                             wire:click="toggleRoyalFlush({{ $org->id }})"
                                                             wire:confirm="Set '{{ $org->name }}' as the Royal Flush organization? Any other org will lose this designation.">
                                                    Set RF
                                                </flux:button>
                                            @endif
                                            <flux:button size="sm" variant="ghost" class="!text-amber-400 hover:!text-amber-300"
                                                         wire:click="deactivate({{ $org->id }})"
                                                         wire:confirm="Deactivate '{{ $org->name }}'? They will lose portal access.">
                                                Deactivate
                                            </flux:button>
                                        @elseif($org->isInactive())
                                            <flux:button size="sm" variant="primary" class="!bg-green-600 hover:!bg-green-700"
                                                         wire:click="reactivate({{ $org->id }})"
                                                         wire:confirm="Reactivate '{{ $org->name }}'?">
                                                Reactivate
                                            </flux:button>
                                        @endif
                                        @if($org->matches_count === 0 && ! $org->isActive())
                                            <flux:button size="sm" variant="ghost" class="!text-red-400 hover:!text-red-300"
                                                         wire:click="deleteOrganization({{ $org->id }})"
                                                         wire:confirm="Permanently delete '{{ $org->name }}'? This cannot be undone.">
                                                Delete
                                            </flux:button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
