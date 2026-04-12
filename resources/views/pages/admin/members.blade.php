<?php

use App\Models\Organization;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
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

    public string $addOrgId = '';
    public array $addOrgRoles = ['is_shooter' => true];

    // Bulk
    public array $selectedUserIds = [];
    public bool $selectAllOnPage = false;
    public string $bulkOrgId = '';
    public array $bulkRoles = [];
    public string $bulkImportEmails = '';
    public string $bulkImportOrgId = '';
    public array $bulkImportRoles = ['is_shooter' => true];

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->selectedUserIds = [];
        $this->selectAllOnPage = false;
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
        $this->selectedUserIds = [];
        $this->selectAllOnPage = false;
    }

    public function updatedSelectAllOnPage(bool $value): void
    {
        if ($value) {
            $this->selectedUserIds = $this->with()['users']->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        } else {
            $this->selectedUserIds = [];
        }
    }

    public function toggleExpand(int $id): void
    {
        $this->expandedUserId = $this->expandedUserId === $id ? null : $id;
        $this->reset(['addOrgId', 'addOrgRoles']);
        $this->addOrgRoles = ['is_shooter' => true];
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

    public function toggleOrgRole(int $userId, int $orgId, string $roleKey): void
    {
        if (! in_array($roleKey, ['is_owner', 'is_match_director', 'is_range_officer', 'is_shooter'])) {
            return;
        }

        $org = Organization::findOrFail($orgId);
        $pivot = $org->admins()->where('user_id', $userId)->first();

        if (! $pivot) {
            return;
        }

        $current = (bool) $pivot->pivot->{$roleKey};

        if ($roleKey === 'is_owner' && $current) {
            $ownerCount = $org->admins()->wherePivot('is_owner', true)->count();
            if ($ownerCount <= 1) {
                Flux::toast("Cannot remove the only owner of {$org->name}.", variant: 'danger');
                return;
            }
        }

        $org->admins()->updateExistingPivot($userId, [$roleKey => ! $current]);
        Flux::toast('Role updated.', variant: 'success');
    }

    public function addToOrganization(int $userId): void
    {
        $this->validate([
            'addOrgId' => 'required|exists:organizations,id',
        ]);

        $org = Organization::findOrFail($this->addOrgId);
        $user = User::findOrFail($userId);

        if ($org->admins()->where('user_id', $userId)->exists()) {
            Flux::toast("{$user->name} is already a member of {$org->name}.", variant: 'warning');
            return;
        }

        $pivotData = [
            'is_owner' => ! empty($this->addOrgRoles['is_owner']),
            'is_match_director' => ! empty($this->addOrgRoles['is_match_director']),
            'is_range_officer' => ! empty($this->addOrgRoles['is_range_officer']),
            'is_shooter' => ! empty($this->addOrgRoles['is_shooter']),
        ];

        $org->admins()->attach($userId, $pivotData);

        $roleNames = collect(['is_owner' => 'Owner', 'is_match_director' => 'Match Director', 'is_range_officer' => 'Range Officer', 'is_shooter' => 'Shooter'])
            ->filter(fn ($label, $key) => ! empty($pivotData[$key]))
            ->values()->join(', ');

        $this->reset(['addOrgId', 'addOrgRoles']);
        $this->addOrgRoles = ['is_shooter' => true];
        Flux::toast("{$user->name} added to {$org->name} as {$roleNames}.", variant: 'success');
    }

    public function removeFromOrganization(int $userId, int $orgId): void
    {
        $org = Organization::findOrFail($orgId);
        $user = User::findOrFail($userId);

        $pivot = $org->admins()->where('user_id', $userId)->first();
        if ($pivot && $pivot->pivot->is_owner) {
            $ownerCount = $org->admins()->wherePivot('is_owner', true)->count();
            if ($ownerCount <= 1) {
                Flux::toast("Cannot remove the only owner of {$org->name}. Assign another owner first.", variant: 'danger');
                return;
            }
        }

        $org->admins()->detach($userId);
        Flux::toast("{$user->name} removed from {$org->name}.", variant: 'success');
    }

    public function deleteMember(int $userId): void
    {
        if ($userId === auth()->id()) {
            Flux::toast('You cannot delete your own account.', variant: 'danger');
            return;
        }

        $user = User::findOrFail($userId);
        $name = $user->name;

        Organization::where('created_by', $userId)->update(['created_by' => null]);
        $user->organizations()->detach();
        $user->registrations()->delete();
        $user->achievements()->delete();
        $user->equipmentProfiles()->delete();
        $user->pushSubscriptions()->delete();
        $user->notifications()->delete();
        $user->delete();

        $this->expandedUserId = null;
        Flux::toast("{$name} has been deleted.", variant: 'success');
    }

    public function bulkDeleteMembers(): void
    {
        if (empty($this->selectedUserIds)) {
            Flux::toast('No members selected.', variant: 'warning');
            return;
        }

        $ids = collect($this->selectedUserIds)->map(fn ($id) => (int) $id)->reject(fn ($id) => $id === auth()->id());

        if ($ids->isEmpty()) {
            Flux::toast('You cannot delete your own account.', variant: 'danger');
            return;
        }

        $users = User::whereIn('id', $ids)->get();

        Organization::whereIn('created_by', $ids)->update(['created_by' => null]);

        DB::table('organization_admins')->whereIn('user_id', $ids)->delete();
        DB::table('match_registrations')->whereIn('user_id', $ids)->delete();
        DB::table('achievements')->whereIn('user_id', $ids)->delete();
        DB::table('user_equipment_profiles')->whereIn('user_id', $ids)->delete();
        DB::table('push_subscriptions')->whereIn('user_id', $ids)->delete();
        DB::table('notifications')->whereIn('notifiable_id', $ids)
            ->where('notifiable_type', User::class)->delete();

        User::whereIn('id', $ids)->delete();

        $this->selectedUserIds = [];
        $this->selectAllOnPage = false;
        $this->expandedUserId = null;
        Flux::toast("{$users->count()} member(s) deleted.", variant: 'success');
    }

    public function bulkAssignRoles(): void
    {
        if (empty($this->selectedUserIds) || empty($this->bulkOrgId) || empty($this->bulkRoles)) {
            Flux::toast('Select users, an organization, and at least one role.', variant: 'warning');
            return;
        }

        $org = Organization::findOrFail($this->bulkOrgId);
        $pivotData = [
            'is_owner' => in_array('is_owner', $this->bulkRoles),
            'is_match_director' => in_array('is_match_director', $this->bulkRoles),
            'is_range_officer' => in_array('is_range_officer', $this->bulkRoles),
            'is_shooter' => in_array('is_shooter', $this->bulkRoles),
        ];

        $added = 0;
        $updated = 0;

        foreach ($this->selectedUserIds as $userId) {
            if ($org->admins()->where('user_id', $userId)->exists()) {
                $org->admins()->updateExistingPivot($userId, $pivotData);
                $updated++;
            } else {
                $org->admins()->attach($userId, $pivotData);
                $added++;
            }
        }

        $this->selectedUserIds = [];
        $this->selectAllOnPage = false;
        $this->bulkRoles = [];
        Flux::toast("Roles applied: {$added} added, {$updated} updated in {$org->name}.", variant: 'success');
    }

    public function bulkImportMembers(): void
    {
        if (empty($this->bulkImportOrgId) || empty(trim($this->bulkImportEmails))) {
            Flux::toast('Select an organization and enter at least one email.', variant: 'warning');
            return;
        }

        $org = Organization::findOrFail($this->bulkImportOrgId);
        $emails = preg_split('/[\s,;]+/', trim($this->bulkImportEmails));
        $emails = array_filter(array_unique(array_map('trim', $emails)));

        $pivotData = [
            'is_owner' => ! empty($this->bulkImportRoles['is_owner']),
            'is_match_director' => ! empty($this->bulkImportRoles['is_match_director']),
            'is_range_officer' => ! empty($this->bulkImportRoles['is_range_officer']),
            'is_shooter' => ! empty($this->bulkImportRoles['is_shooter']),
        ];

        $added = 0;
        $notFound = [];

        foreach ($emails as $email) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                $notFound[] = $email;
                continue;
            }

            if ($org->admins()->where('user_id', $user->id)->exists()) {
                $org->admins()->updateExistingPivot($user->id, $pivotData);
            } else {
                $org->admins()->attach($user->id, $pivotData);
            }
            $added++;
        }

        $this->bulkImportEmails = '';
        $msg = "{$added} member(s) imported to {$org->name}.";
        if (! empty($notFound)) {
            $msg .= ' Not found: ' . implode(', ', array_slice($notFound, 0, 5));
            if (count($notFound) > 5) {
                $msg .= ' (+' . (count($notFound) - 5) . ' more)';
            }
        }
        Flux::toast($msg, variant: empty($notFound) ? 'success' : 'warning');
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

@php
    $roleMap = ['is_owner' => 'Owner', 'is_match_director' => 'Match Director', 'is_range_officer' => 'Range Officer', 'is_shooter' => 'Shooter'];
    $roleColors = ['is_owner' => 'amber', 'is_match_director' => 'blue', 'is_range_officer' => 'green', 'is_shooter' => 'zinc'];
@endphp

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

    {{-- Bulk Action Bar --}}
    @if(! empty($selectedUserIds))
        <div class="rounded-xl border border-accent/30 bg-accent/5 p-4 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-primary">{{ count($selectedUserIds) }} member(s) selected</span>
                <button wire:click="$set('selectedUserIds', []); $set('selectAllOnPage', false)" class="text-xs text-muted hover:text-secondary">Clear</button>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 items-end">
                <div class="flex-1">
                    <label class="block text-xs font-medium text-secondary mb-1">Organization</label>
                    <select wire:model="bulkOrgId" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:ring-1 focus:ring-accent">
                        <option value="">Select organization...</option>
                        @foreach($allOrgs as $org)
                            <option value="{{ $org->id }}">{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-1.5 flex-wrap">
                    @foreach($roleMap as $key => $label)
                        <label class="cursor-pointer">
                            <input type="checkbox" wire:model="bulkRoles" value="{{ $key }}" class="sr-only peer">
                            <span class="inline-block rounded-full px-3 py-1.5 text-xs font-medium transition-colors border peer-checked:bg-accent peer-checked:text-primary peer-checked:border-accent bg-surface-2 text-secondary border-border hover:bg-surface-2">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <flux:button size="sm" variant="primary" class="!bg-accent hover:!bg-accent-hover" wire:click="bulkAssignRoles">
                    Apply Roles
                </flux:button>
                <flux:button size="sm" variant="danger"
                             wire:click="bulkDeleteMembers"
                             wire:confirm="Permanently delete {{ count($selectedUserIds) }} selected member(s)? This removes all their data and cannot be undone.">
                    Delete Selected
                </flux:button>
            </div>
        </div>
    @endif

    {{-- Bulk Import --}}
    <details class="rounded-xl border border-dashed border-border bg-surface/50 group">
        <summary class="px-6 py-3 cursor-pointer text-sm font-medium text-secondary hover:text-primary transition-colors">
            Bulk Import Members by Email
        </summary>
        <div class="px-6 pb-5 pt-2 space-y-3">
            <div class="flex flex-col sm:flex-row gap-3 items-end">
                <div class="flex-1">
                    <label class="block text-xs font-medium text-secondary mb-1">Organization</label>
                    <select wire:model="bulkImportOrgId" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:ring-1 focus:ring-accent">
                        <option value="">Select organization...</option>
                        @foreach($allOrgs as $org)
                            <option value="{{ $org->id }}">{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-1.5 flex-wrap">
                    @foreach($roleMap as $key => $label)
                        <label class="cursor-pointer">
                            <input type="checkbox" wire:model="bulkImportRoles.{{ $key }}" class="sr-only peer" @checked(! empty($bulkImportRoles[$key]))>
                            <span class="inline-block rounded-full px-3 py-1.5 text-xs font-medium transition-colors border peer-checked:bg-accent peer-checked:text-primary peer-checked:border-accent bg-surface-2 text-secondary border-border hover:bg-surface-2">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <textarea wire:model="bulkImportEmails" rows="3" placeholder="user1@example.com, user2@example.com (one per line or comma-separated)"
                      class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder:text-muted focus:border-accent focus:ring-1 focus:ring-accent"></textarea>
            <flux:button size="sm" variant="primary" class="!bg-accent hover:!bg-accent-hover" wire:click="bulkImportMembers">
                Import Members
            </flux:button>
        </div>
    </details>

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
                            <th class="px-3 py-3 font-medium w-10">
                                <input type="checkbox" wire:model.live="selectAllOnPage"
                                       class="rounded border-border text-accent focus:ring-accent">
                            </th>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Email</th>
                            <th class="px-4 py-3 font-medium">Site Role</th>
                            <th class="px-4 py-3 font-medium">Verified</th>
                            <th class="px-4 py-3 font-medium">Organizations</th>
                            <th class="px-4 py-3 font-medium text-right">Matches</th>
                            <th class="px-4 py-3 font-medium">Joined</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($users as $member)
                            <tr class="hover:bg-surface-2/30 transition-colors cursor-pointer"
                                wire:click="toggleExpand({{ $member->id }})"
                                wire:key="member-{{ $member->id }}">
                                <td class="px-3 py-3" wire:click.stop>
                                    <input type="checkbox" wire:model.live="selectedUserIds" value="{{ $member->id }}"
                                           class="rounded border-border text-accent focus:ring-accent">
                                </td>
                                <td class="px-4 py-3 font-medium text-primary">{{ $member->name }}</td>
                                <td class="px-4 py-3 text-secondary text-xs">{{ $member->email }}</td>
                                <td class="px-4 py-3">
                                    @switch($member->role)
                                        @case('owner')
                                            <div class="flex flex-wrap gap-1">
                                                <flux:badge size="sm" color="amber">Site Owner</flux:badge>
                                                <flux:badge size="sm" color="zinc">Shooter</flux:badge>
                                            </div>
                                            @break
                                        @case('match_director')
                                            <div class="flex flex-wrap gap-1">
                                                <flux:badge size="sm" color="blue">Match Director</flux:badge>
                                                <flux:badge size="sm" color="zinc">Shooter</flux:badge>
                                            </div>
                                            @break
                                        @default
                                            <flux:badge size="sm" color="zinc">Shooter</flux:badge>
                                    @endswitch
                                </td>
                                <td class="px-4 py-3">
                                    @if($member->hasVerifiedEmail())
                                        <flux:badge size="sm" color="green">Yes</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="red">No</flux:badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse($member->organizations as $org)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-surface-2 px-2 py-0.5 text-xs text-secondary">
                                                {{ $org->name }}
                                                <span class="text-muted">
                                                    @php
                                                        $labels = collect($roleMap)->filter(fn ($l, $k) => $org->pivot->{$k})->values();
                                                    @endphp
                                                    ({{ $labels->join(', ') }})
                                                </span>
                                            </span>
                                        @empty
                                            <span class="text-muted text-xs">—</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right text-secondary">{{ $member->registrations_count }}</td>
                                <td class="px-4 py-3 text-muted text-xs">{{ $member->created_at->format('d M Y') }}</td>
                            </tr>

                            {{-- Expanded row --}}
                            @if($expandedUserId === $member->id)
                                <tr class="bg-surface-2/20" wire:key="expand-{{ $member->id }}">
                                    <td colspan="8" class="px-6 py-4">
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
                                                <div class="flex flex-col gap-2">
                                                    <select wire:model="addOrgId" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:ring-1 focus:ring-accent">
                                                        <option value="">Select organization...</option>
                                                        @foreach($allOrgs as $org)
                                                            @unless($member->organizations->contains('id', $org->id))
                                                                <option value="{{ $org->id }}">{{ $org->name }}</option>
                                                            @endunless
                                                        @endforeach
                                                    </select>
                                                    <div class="flex gap-1.5 flex-wrap">
                                                        @foreach($roleMap as $key => $label)
                                                            <label class="cursor-pointer">
                                                                <input type="checkbox" wire:model="addOrgRoles.{{ $key }}" class="sr-only peer" @checked(! empty($addOrgRoles[$key]))>
                                                                <span class="inline-block rounded-full px-3 py-1 text-xs font-medium transition-colors border peer-checked:bg-accent peer-checked:text-primary peer-checked:border-accent bg-surface-2 text-secondary border-border hover:bg-surface-2">{{ $label }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                    <div>
                                                        <flux:button size="sm" variant="primary" class="!bg-accent hover:!bg-accent-hover"
                                                                     wire:click="addToOrganization({{ $member->id }})">
                                                            Add
                                                        </flux:button>
                                                    </div>
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
                                                                <th class="px-4 py-2 font-medium">Roles</th>
                                                                <th class="px-4 py-2 font-medium text-right">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-border">
                                                            @foreach($member->organizations as $org)
                                                                <tr wire:key="org-{{ $member->id }}-{{ $org->id }}">
                                                                    <td class="px-4 py-2 text-primary">{{ $org->name }}</td>
                                                                    <td class="px-4 py-2">
                                                                        <div class="flex gap-1.5 flex-wrap">
                                                                            @foreach($roleMap as $key => $label)
                                                                                <button wire:click="toggleOrgRole({{ $member->id }}, {{ $org->id }}, '{{ $key }}')"
                                                                                        class="rounded-full px-2.5 py-0.5 text-xs font-medium transition-colors border {{ $org->pivot->{$key} ? 'bg-accent text-primary border-accent' : 'bg-surface-2 text-secondary border-border hover:bg-surface-2' }}">
                                                                                    {{ $label }}
                                                                                </button>
                                                                            @endforeach
                                                                        </div>
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

                                        {{-- Delete Member --}}
                                        @if($member->id !== auth()->id())
                                            <div class="mt-4 pt-4 border-t border-border" wire:click.stop>
                                                <flux:button size="sm" variant="danger"
                                                             wire:click="deleteMember({{ $member->id }})"
                                                             wire:confirm="Permanently delete {{ $member->name }}? This removes all their registrations, achievements, equipment profiles, and organization memberships. This cannot be undone.">
                                                    Delete Member
                                                </flux:button>
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
