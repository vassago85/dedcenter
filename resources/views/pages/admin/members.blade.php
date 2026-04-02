<?php

use App\Models\Organization;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')]
    #[Title('Members')]
    class extends Component {
    use WithPagination;

    public string $search = '';
    public string $roleFilter = 'all';
    public ?int $expandedUserId = null;

    public string $addOrgUserId = '';
    public string $addOrgId = '';
    public string $addOrgRole = 'range_officer';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function toggleExpand(int $id): void
    {
        $this->expandedUserId = $this->expandedUserId === $id ? null : $id;
        $this->reset(['addOrgId', 'addOrgRole']);
        $this->addOrgRole = 'range_officer';
    }

    public function changeSiteRole(int $userId, string $newRole): void
    {
        if (! in_array($newRole, ['owner', 'match_director', 'shooter'])) {
            return;
        }

        if ($userId === auth()->id()) {
            Flux::toast('You cannot change your own role.', variant: 'danger');
            return;
        }

        $user = User::findOrFail($userId);
        $user->update(['role' => $newRole]);
        Flux::toast("{$user->name} is now {$user->roleLabel()}.", variant: 'success');
    }

    public function addToOrganization(int $userId): void
    {
        $this->validate([
            'addOrgId' => 'required|exists:organizations,id',
            'addOrgRole' => 'required|in:owner,match_director,range_officer',
        ]);

        $org = Organization::findOrFail($this->addOrgId);
        $user = User::findOrFail($userId);

        if ($org->admins()->where('user_id', $userId)->exists()) {
            Flux::toast("{$user->name} is already a member of {$org->name}.", variant: 'warning');
            return;
        }

        $org->admins()->attach($userId, ['role' => $this->addOrgRole]);
        $this->reset(['addOrgId', 'addOrgRole']);
        $this->addOrgRole = 'range_officer';
        Flux::toast("{$user->name} added to {$org->name} as " . str_replace('_', ' ', $this->addOrgRole ?: 'range officer') . ".", variant: 'success');
    }

    public function removeFromOrganization(int $userId, int $orgId): void
    {
        $org = Organization::findOrFail($orgId);
        $user = User::findOrFail($userId);

        $pivot = $org->admins()->where('user_id', $userId)->first();
        if ($pivot && $pivot->pivot->role === 'owner') {
            $ownerCount = $org->admins()->wherePivot('role', 'owner')->count();
            if ($ownerCount <= 1) {
                Flux::toast("Cannot remove the only owner of {$org->name}. Assign another owner first.", variant: 'danger');
                return;
            }
        }

        $org->admins()->detach($userId);
        Flux::toast("{$user->name} removed from {$org->name}.", variant: 'success');
    }

    public function changeOrgRole(int $userId, int $orgId, string $newRole): void
    {
        if (! in_array($newRole, ['owner', 'match_director', 'range_officer'])) {
            return;
        }

        $org = Organization::findOrFail($orgId);
        $org->admins()->updateExistingPivot($userId, ['role' => $newRole]);
        Flux::toast('Organization role updated.', variant: 'success');
    }

    public function with(): array
    {
        $users = User::query()
            ->withCount('registrations')
            ->with('organizations')
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q2) =>
                $q2->where('name', 'like', "%{$s}%")
                   ->orWhere('email', 'like', "%{$s}%")
            ))
            ->when($this->roleFilter !== 'all', fn ($q) => $q->where('role', $this->roleFilter))
            ->orderBy('name')
            ->paginate(25);

        $allOrgs = Organization::active()->orderBy('name')->get(['id', 'name']);

        return [
            'users' => $users,
            'allOrgs' => $allOrgs,
        ];
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">Members</flux:heading>
        <p class="mt-1 text-sm text-muted">Manage all registered users. Assign site roles and organization memberships.</p>
    </div>

    {{-- Search + Filter --}}
    <div class="flex flex-col sm:flex-row gap-3">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by name or email..." icon="magnifying-glass" />
        </div>
        <div class="flex gap-2 flex-wrap">
            @foreach(['all' => 'All', 'owner' => 'Owners', 'match_director' => 'Match Directors', 'shooter' => 'Shooters'] as $value => $label)
                <button wire:click="$set('roleFilter', '{{ $value }}')"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors {{ $roleFilter === $value ? 'bg-accent text-primary' : 'bg-surface-2 text-secondary hover:bg-surface-2' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Table --}}
    <div class="rounded-xl border border-border bg-surface overflow-hidden">
        @if($users->isEmpty())
            <div class="px-6 py-12 text-center">
                <p class="text-muted">No members found{{ $search ? " for \"{$search}\"" : '' }}.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-muted">
                            <th class="px-6 py-3 font-medium">Name</th>
                            <th class="px-6 py-3 font-medium">Email</th>
                            <th class="px-6 py-3 font-medium">Site Role</th>
                            <th class="px-6 py-3 font-medium">Verified</th>
                            <th class="px-6 py-3 font-medium">Organizations</th>
                            <th class="px-6 py-3 font-medium text-right">Matches</th>
                            <th class="px-6 py-3 font-medium">Joined</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($users as $member)
                            <tr class="hover:bg-surface-2/30 transition-colors cursor-pointer"
                                wire:click="toggleExpand({{ $member->id }})"
                                wire:key="member-{{ $member->id }}">
                                <td class="px-6 py-3 font-medium text-primary">{{ $member->name }}</td>
                                <td class="px-6 py-3 text-secondary text-xs">{{ $member->email }}</td>
                                <td class="px-6 py-3">
                                    @switch($member->role)
                                        @case('owner')
                                            <flux:badge size="sm" color="amber">Site Owner</flux:badge>
                                            @break
                                        @case('match_director')
                                            <flux:badge size="sm" color="blue">Match Director</flux:badge>
                                            @break
                                        @default
                                            <flux:badge size="sm" color="zinc">Shooter</flux:badge>
                                    @endswitch
                                </td>
                                <td class="px-6 py-3">
                                    @if($member->hasVerifiedEmail())
                                        <flux:badge size="sm" color="green">Yes</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="red">No</flux:badge>
                                    @endif
                                </td>
                                <td class="px-6 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse($member->organizations as $org)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-surface-2 px-2 py-0.5 text-xs text-secondary">
                                                {{ $org->name }}
                                                <span class="text-muted">({{ str_replace('_', ' ', $org->pivot->role) }})</span>
                                            </span>
                                        @empty
                                            <span class="text-muted text-xs">—</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-right text-secondary">{{ $member->registrations_count }}</td>
                                <td class="px-6 py-3 text-muted text-xs">{{ $member->created_at->format('d M Y') }}</td>
                            </tr>

                            {{-- Expanded row --}}
                            @if($expandedUserId === $member->id)
                                <tr class="bg-surface-2/20" wire:key="expand-{{ $member->id }}">
                                    <td colspan="7" class="px-6 py-4">
                                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                            {{-- Site Role --}}
                                            <div class="space-y-3">
                                                <h4 class="text-sm font-medium text-secondary">Change Site Role</h4>
                                                @if($member->id === auth()->id())
                                                    <p class="text-xs text-muted">You cannot change your own role.</p>
                                                @else
                                                    <div class="flex gap-2" wire:click.stop>
                                                        @foreach(['owner' => 'Site Owner', 'match_director' => 'Match Director', 'shooter' => 'Shooter'] as $rv => $rl)
                                                            <button wire:click="changeSiteRole({{ $member->id }}, '{{ $rv }}')"
                                                                    @if($member->role === $rv) disabled @endif
                                                                    wire:confirm="Change {{ $member->name }} to {{ $rl }}?"
                                                                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors {{ $member->role === $rv ? 'bg-accent text-primary cursor-default' : 'bg-surface-2 text-secondary hover:bg-surface-2 hover:text-primary' }}">
                                                                {{ $rl }}
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>

                                            {{-- Add to Org --}}
                                            <div class="space-y-3" wire:click.stop>
                                                <h4 class="text-sm font-medium text-secondary">Add to Organization</h4>
                                                <div class="flex gap-2 items-end">
                                                    <div class="flex-1">
                                                        <select wire:model="addOrgId" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:ring-1 focus:ring-accent">
                                                            <option value="">Select organization...</option>
                                                            @foreach($allOrgs as $org)
                                                                @unless($member->organizations->contains('id', $org->id))
                                                                    <option value="{{ $org->id }}">{{ $org->name }}</option>
                                                                @endunless
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="w-40">
                                                        <select wire:model="addOrgRole" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:ring-1 focus:ring-accent">
                                                            <option value="owner">Owner</option>
                                                            <option value="match_director">Match Director</option>
                                                            <option value="range_officer">Range Officer</option>
                                                        </select>
                                                    </div>
                                                    <flux:button size="sm" variant="primary" class="!bg-accent hover:!bg-accent-hover"
                                                                 wire:click="addToOrganization({{ $member->id }})">
                                                        Add
                                                    </flux:button>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Org memberships detail --}}
                                        @if($member->organizations->isNotEmpty())
                                            <div class="mt-4 space-y-2">
                                                <h4 class="text-sm font-medium text-secondary">Organization Memberships</h4>
                                                <div class="rounded-lg border border-border bg-surface overflow-hidden" wire:click.stop>
                                                    <table class="w-full text-sm">
                                                        <thead>
                                                            <tr class="border-b border-border text-left text-muted">
                                                                <th class="px-4 py-2 font-medium">Organization</th>
                                                                <th class="px-4 py-2 font-medium">Role</th>
                                                                <th class="px-4 py-2 font-medium text-right">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-border">
                                                            @foreach($member->organizations as $org)
                                                                <tr wire:key="org-{{ $member->id }}-{{ $org->id }}">
                                                                    <td class="px-4 py-2 text-primary">{{ $org->name }}</td>
                                                                    <td class="px-4 py-2">
                                                                        <select wire:change="changeOrgRole({{ $member->id }}, {{ $org->id }}, $event.target.value)"
                                                                                class="rounded-lg border border-border bg-surface-2 px-2 py-1 text-xs text-primary focus:border-accent focus:ring-1 focus:ring-accent">
                                                                            @foreach(['owner' => 'Owner', 'match_director' => 'Match Director', 'range_officer' => 'Range Officer'] as $rv => $rl)
                                                                                <option value="{{ $rv }}" @selected($org->pivot->role === $rv)>{{ $rl }}</option>
                                                                            @endforeach
                                                                        </select>
                                                                    </td>
                                                                    <td class="px-4 py-2 text-right">
                                                                        <flux:button size="sm" variant="ghost" class="!text-accent hover:!text-accent"
                                                                                     wire:click="removeFromOrganization({{ $member->id }}, {{ $org->id }})"
                                                                                     wire:confirm="Remove {{ $member->name }} from {{ $org->name }}?">
                                                                            Remove
                                                                        </flux:button>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-3 border-t border-border">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>
