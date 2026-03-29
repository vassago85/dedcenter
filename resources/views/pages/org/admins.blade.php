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

    public function addStaff(): void
    {
        $this->validate([
            'email' => 'required|email',
            'newRole' => 'required|in:match_director,range_officer',
        ]);

        $user = User::where('email', $this->email)->first();

        if (! $user) {
            $this->addError('email', 'No user found with that email.');
            return;
        }

        if ($this->organization->admins()->where('user_id', $user->id)->exists()) {
            $this->addError('email', 'This user is already on the team.');
            return;
        }

        $addedRole = $this->newRole;
        $this->organization->admins()->attach($user->id, ['role' => $addedRole]);
        $this->reset(['email', 'newRole']);
        $this->newRole = 'range_officer';
        Flux::toast("{$user->name} added as " . str_replace('_', ' ', $addedRole) . ".", variant: 'success');
    }

    public function changeRole(int $userId, string $role): void
    {
        if (! in_array($role, ['match_director', 'range_officer'])) {
            return;
        }

        $pivot = $this->organization->admins()->where('user_id', $userId)->first();
        if ($pivot && $pivot->pivot->role === 'owner') {
            Flux::toast('Cannot change the owner\'s role.', variant: 'danger');
            return;
        }

        $this->organization->admins()->updateExistingPivot($userId, ['role' => $role]);
        Flux::toast('Role updated.', variant: 'success');
    }

    public function removeStaff(int $userId): void
    {
        $pivot = $this->organization->admins()->where('user_id', $userId)->first();

        if ($pivot && $pivot->pivot->role === 'owner') {
            Flux::toast('Cannot remove the owner.', variant: 'danger');
            return;
        }

        $this->organization->admins()->detach($userId);
        Flux::toast('Team member removed.', variant: 'success');
    }

    public function with(): array
    {
        return [
            'staff' => $this->organization->admins()->orderByPivot('role')->get(),
        ];
    }
}; ?>

<div class="space-y-6 max-w-2xl">
    <div>
        <flux:heading size="xl">Team</flux:heading>
        <p class="mt-1 text-sm text-muted">{{ $organization->name }} — Manage match directors and range officers.</p>
    </div>

    <div class="rounded-xl border border-border bg-surface overflow-hidden">
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
                        <tr class="hover:bg-surface-2/30 transition-colors" wire:key="staff-{{ $member->id }}">
                            <td class="px-6 py-3 font-medium text-primary">{{ $member->name }}</td>
                            <td class="px-6 py-3 text-secondary">{{ $member->email }}</td>
                            <td class="px-6 py-3">
                                @if($member->pivot->role === 'owner')
                                    <flux:badge size="sm" color="amber">Owner</flux:badge>
                                @elseif($member->pivot->role === 'match_director')
                                    <flux:badge size="sm" color="blue">Match Director</flux:badge>
                                @else
                                    <flux:badge size="sm" color="green">Range Officer</flux:badge>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-right space-x-1">
                                @if($member->pivot->role !== 'owner')
                                    @if($member->pivot->role === 'range_officer')
                                        <flux:button size="sm" variant="ghost"
                                                     wire:click="changeRole({{ $member->id }}, 'match_director')"
                                                     wire:confirm="Promote {{ $member->name }} to Match Director?">
                                            Promote to MD
                                        </flux:button>
                                    @else
                                        <flux:button size="sm" variant="ghost"
                                                     wire:click="changeRole({{ $member->id }}, 'range_officer')"
                                                     wire:confirm="Change {{ $member->name }} to Range Officer?">
                                            Demote to RO
                                        </flux:button>
                                    @endif
                                    <flux:button size="sm" variant="ghost" class="!text-accent hover:!text-accent"
                                                 wire:click="removeStaff({{ $member->id }})"
                                                 wire:confirm="Remove {{ $member->name }} from the team?">
                                        Remove
                                    </flux:button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl border border-dashed border-border bg-surface/50 p-6 space-y-4">
        <h3 class="text-sm font-medium text-secondary">Add Team Member</h3>
        <p class="text-xs text-muted">Enter the email of a registered user and choose their role.</p>
        <form wire:submit="addStaff" class="flex gap-3 items-end">
            <div class="flex-1">
                <flux:input wire:model="email" label="Email" type="email" placeholder="user@example.com" required />
            </div>
            <div class="w-48">
                <label class="block text-sm font-medium text-secondary mb-1">Role</label>
                <select wire:model="newRole" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:ring-1 focus:ring-accent">
                    <option value="match_director">Match Director</option>
                    <option value="range_officer">Range Officer</option>
                </select>
            </div>
            <flux:button type="submit" size="sm" variant="primary" class="!bg-accent hover:!bg-accent-hover">Add</flux:button>
        </form>
    </div>
</div>
