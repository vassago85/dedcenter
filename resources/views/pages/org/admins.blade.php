<?php

use App\Models\Organization;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Organization Team')]
    class extends Component {
    public Organization $organization;

    public string $email = '';

    public string $newRole = 'range_officer';

    /**
     * Guard mutating team-management actions to org OWNERS only.
     *
     * The team route sits behind the `org.admin` middleware, which resolves
     * to range-officer access — so range officers can see the roster.
     * Mutating actions live at OWNER level via OrganizationPolicy::manageTeam.
     */
    protected function authorizeOwner(): void
    {
        abort_unless(
            auth()->user()?->can('manageTeam', $this->organization),
            403,
            'Only the organization owner can manage the team.',
        );
    }

    public function addStaff(): void
    {
        $this->authorizeOwner();

        $this->validate([
            'email' => 'required|email',
            'newRole' => 'required|in:director,range_officer',
        ]);

        $user = User::where('email', $this->email)->first();

        if (! $user) {
            $this->addError('email', 'No user found with that email. They need an account before they can be added.');

            return;
        }

        if ($this->organization->admins()->where('user_id', $user->id)->exists()) {
            $this->addError('email', 'This user is already on the team.');

            return;
        }

        $isDirector = $this->newRole === 'director';

        $this->organization->admins()->attach($user->id, [
            'is_owner' => false,
            'is_match_director' => $isDirector,
            'is_range_officer' => true,
            'is_shooter' => false,
        ]);

        $this->reset('email');
        $this->newRole = 'range_officer';
        Flux::toast($user->name.' added as '.($isDirector ? 'Director' : 'Range Officer').'.', variant: 'success');
    }

    public function setRole(int $userId, string $role): void
    {
        $this->authorizeOwner();

        if (! in_array($role, ['director', 'range_officer'], true)) {
            return;
        }

        $pivot = $this->organization->admins()->where('user_id', $userId)->first();
        if (! $pivot || $pivot->pivot->is_owner) {
            Flux::toast('Transfer ownership before changing an owner.', variant: 'danger');

            return;
        }

        $this->organization->admins()->updateExistingPivot($userId, [
            'is_match_director' => $role === 'director',
            'is_range_officer' => true,
            'is_shooter' => false,
        ]);
        Flux::toast('Role updated.', variant: 'success');
    }

    public function removeStaff(int $userId): void
    {
        $this->authorizeOwner();

        $pivot = $this->organization->admins()->where('user_id', $userId)->first();

        if ($pivot && $pivot->pivot->is_owner) {
            Flux::toast('Transfer ownership before removing an owner.', variant: 'danger');

            return;
        }

        $this->organization->admins()->detach($userId);
        Flux::toast('Team member removed.', variant: 'success');
    }

    public function transferOwnership(int $userId): void
    {
        $this->authorizeOwner();

        $target = $this->organization->admins()->where('user_id', $userId)->first();
        if (! $target || $target->pivot->is_owner) {
            return;
        }

        $actor = auth()->user();
        $actorOnTeam = $this->organization->admins()->where('user_id', $actor->id)->exists();

        $this->organization->admins()->updateExistingPivot($userId, [
            'is_owner' => true,
            'is_match_director' => true,
            'is_range_officer' => true,
            'is_shooter' => false,
        ]);

        if ($actorOnTeam && (int) $actor->id !== $userId) {
            $this->organization->admins()->updateExistingPivot($actor->id, [
                'is_owner' => false,
                'is_match_director' => true,
                'is_range_officer' => true,
            ]);
        }

        Flux::toast('Ownership transferred to '.$target->name.'.', variant: 'success');
    }

    public function with(): array
    {
        return [
            'staff' => $this->organization->admins()
                ->orderByRaw('organization_admins.is_owner DESC, organization_admins.is_match_director DESC')
                ->get(),
        ];
    }
}; ?>

@php
    $isCurrentUserOwner = auth()->user()->isOrgOwner($organization);
    $mine = $staff->firstWhere('id', auth()->id());
    $myRole = $mine
        ? org_membership_label($mine->pivot)
        : (auth()->user()->isOwner() ? 'Platform owner' : org_membership_label((object) ['is_owner' => false, 'is_match_director' => false, 'is_range_officer' => true]));
@endphp

<div class="space-y-6">
    <x-app-page-header
        eyebrow="{{ $myRole }}"
        title="Team"
        subtitle="{{ $organization->name }} — one role per person. Director covers every match. A range officer covers only the matches they are assigned to.">
    </x-app-page-header>

    <div class="overflow-hidden rounded-xl border border-border bg-surface">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border text-left text-muted">
                        <th class="px-6 py-3 font-medium">Name</th>
                        <th class="px-6 py-3 font-medium">Email</th>
                        <th class="px-6 py-3 font-medium">Role</th>
                        <th class="px-6 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($staff as $member)
                        @php
                            $roleLabel = org_membership_label($member->pivot);
                            $roleValue = $member->pivot->is_match_director ? 'director' : 'range_officer';
                        @endphp
                        <tr class="transition-colors hover:bg-surface-2/30" wire:key="staff-{{ $member->id }}">
                            <td class="px-6 py-3 font-medium text-primary">{{ $member->name }}</td>
                            <td class="px-6 py-3 text-secondary">{{ $member->email }}</td>
                            <td class="px-6 py-3">
                                <flux:badge size="sm" :color="$member->pivot->is_owner ? 'amber' : ($member->pivot->is_match_director ? 'blue' : 'green')">
                                    {{ $roleLabel }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-3 text-right">
                                @if($isCurrentUserOwner && ! $member->pivot->is_owner)
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <label class="sr-only" for="role-{{ $member->id }}">Role for {{ $member->name }}</label>
                                        <select id="role-{{ $member->id }}"
                                                class="rounded-lg border border-border bg-surface-2 px-2 py-1.5 text-xs font-medium text-primary"
                                                wire:change="setRole({{ $member->id }}, $event.target.value)">
                                            <option value="director" @selected($roleValue === 'director')>Director</option>
                                            <option value="range_officer" @selected($roleValue === 'range_officer')>Range Officer</option>
                                        </select>
                                        <button type="button"
                                                wire:click="transferOwnership({{ $member->id }})"
                                                wire:confirm="Make {{ $member->name }} the owner? You will keep Director access."
                                                class="rounded-lg border border-border px-2.5 py-1.5 text-xs font-semibold text-secondary hover:text-primary">
                                            Make owner
                                        </button>
                                        <button type="button"
                                                wire:click="removeStaff({{ $member->id }})"
                                                wire:confirm="Remove {{ $member->name }} from the team?"
                                                class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-accent hover:text-accent">
                                            Remove
                                        </button>
                                    </div>
                                @elseif($member->pivot->is_owner)
                                    <span class="text-xs text-muted">Owner</span>
                                @else
                                    <span class="text-xs text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($isCurrentUserOwner)
        <div class="space-y-5 rounded-xl border border-dashed border-border bg-surface/50 p-6">
            <div class="space-y-1">
                <h3 class="text-sm font-medium text-secondary">Add someone</h3>
                <p class="text-xs text-muted">They need a DeadCenter account. Pick one role.</p>
            </div>
            <form wire:submit="addStaff" class="space-y-4">
                <div class="max-w-md space-y-2">
                    <label for="new-role" class="block text-xs font-medium uppercase tracking-wide text-muted">Role</label>
                    <select id="new-role" wire:model="newRole"
                            class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary">
                        <option value="director">Director — runs every match for this organization</option>
                        <option value="range_officer">Range Officer — scores only the matches they are assigned to</option>
                    </select>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <flux:input wire:model="email" label="Email" type="email" placeholder="user@example.com" required />
                    </div>
                    <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover w-full sm:w-auto">Add to team</flux:button>
                </div>
            </form>
        </div>
    @endif
</div>
