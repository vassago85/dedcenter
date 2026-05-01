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
    public array $newRoles = ['is_range_officer' => true];

    public function addStaff(): void
    {
        $this->validate([
            'email' => 'required|email',
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

        $pivotData = [
            'is_owner' => false,
            'is_match_director' => ! empty($this->newRoles['is_match_director']),
            'is_range_officer' => ! empty($this->newRoles['is_range_officer']),
            'is_shooter' => ! empty($this->newRoles['is_shooter']),
        ];

        $this->organization->admins()->attach($user->id, $pivotData);

        $roleNames = collect([
            'is_match_director' => 'Match Director',
            'is_range_officer' => 'Range Officer',
            'is_shooter' => 'Shooter',
        ])->filter(fn ($label, $key) => ! empty($pivotData[$key]))->values()->join(', ') ?: 'member';

        $this->reset(['email', 'newRoles']);
        $this->newRoles = ['is_range_officer' => true];
        Flux::toast("{$user->name} added as {$roleNames}.", variant: 'success');
    }

    public function toggleRole(int $userId, string $roleKey): void
    {
        if (! in_array($roleKey, ['is_match_director', 'is_range_officer', 'is_shooter'])) {
            return;
        }

        $pivot = $this->organization->admins()->where('user_id', $userId)->first();
        if (! $pivot) {
            return;
        }

        if ($pivot->pivot->is_owner && $roleKey === 'is_owner') {
            Flux::toast('Cannot change the owner role from here.', variant: 'danger');
            return;
        }

        $current = (bool) $pivot->pivot->{$roleKey};
        $this->organization->admins()->updateExistingPivot($userId, [$roleKey => ! $current]);
        Flux::toast('Role updated.', variant: 'success');
    }

    public function removeStaff(int $userId): void
    {
        $pivot = $this->organization->admins()->where('user_id', $userId)->first();

        if ($pivot && $pivot->pivot->is_owner) {
            Flux::toast('Cannot remove the owner.', variant: 'danger');
            return;
        }

        $this->organization->admins()->detach($userId);
        Flux::toast('Team member removed.', variant: 'success');
    }

    public function with(): array
    {
        return [
            'staff' => $this->organization->admins()->orderByRaw('organization_admins.is_owner DESC, organization_admins.is_match_director DESC')->get(),
        ];
    }
}; ?>

@php
    $roleMap = ['is_owner' => 'Owner', 'is_match_director' => 'Match Director', 'is_range_officer' => 'Range Officer', 'is_shooter' => 'Shooter'];
    $roleColors = ['is_owner' => 'amber', 'is_match_director' => 'blue', 'is_range_officer' => 'green', 'is_shooter' => 'zinc'];
    $isCurrentUserOwner = auth()->user()->isOrgOwner($organization);
@endphp

<div class="space-y-6 max-w-2xl">
    <div>
        <flux:heading size="xl">Team</flux:heading>
        <p class="mt-1 text-sm text-muted">{{ $organization->name }} — Organization owners manage banking in Settings. Here you add match directors, range officers, and shooters for day-to-day running.</p>
    </div>

    <div class="rounded-xl border border-border bg-surface overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border text-left text-muted">
                        <th class="px-6 py-3 font-medium">Name</th>
                        <th class="px-6 py-3 font-medium">Email</th>
                        <th class="px-6 py-3 font-medium">Roles</th>
                        <th class="px-6 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($staff as $member)
                        <tr class="hover:bg-surface-2/30 transition-colors" wire:key="staff-{{ $member->id }}">
                            <td class="px-6 py-3 font-medium text-primary">{{ $member->name }}</td>
                            <td class="px-6 py-3 text-secondary">{{ $member->email }}</td>
                            <td class="px-6 py-3">
                                <div class="flex gap-1.5 flex-wrap">
                                    @foreach($roleMap as $key => $label)
                                        @if($member->pivot->{$key})
                                            <flux:badge size="sm" color="{{ $roleColors[$key] }}">{{ $label }}</flux:badge>
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-6 py-3 text-right">
                                @if(! $member->pivot->is_owner || $isCurrentUserOwner)
                                    <div class="flex gap-1.5 justify-end flex-wrap">
                                        @if(! $member->pivot->is_owner)
                                            @foreach(['is_match_director' => 'MD', 'is_range_officer' => 'RO', 'is_shooter' => 'Shooter'] as $key => $short)
                                                <button wire:click="toggleRole({{ $member->id }}, '{{ $key }}')"
                                                        class="rounded-full px-2 py-0.5 text-xs font-medium transition-colors border {{ $member->pivot->{$key} ? 'bg-accent text-primary border-accent' : 'bg-surface-2 text-secondary border-border hover:bg-surface-2' }}">
                                                    {{ $short }}
                                                </button>
                                            @endforeach
                                            <flux:button size="sm" variant="ghost" class="!text-accent hover:!text-accent"
                                                         wire:click="removeStaff({{ $member->id }})"
                                                         wire:confirm="Remove {{ $member->name }} from the team?">
                                                Remove
                                            </flux:button>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl border border-dashed border-border bg-surface/50 p-6 space-y-5">
        <div class="space-y-1">
            <h3 class="text-sm font-medium text-secondary">Add Team Member</h3>
            <p class="text-xs text-muted">Enter the email of a registered user and choose their role(s).</p>
        </div>
        <form wire:submit="addStaff" class="space-y-5">
            <div class="space-y-2">
                <span class="block text-xs font-medium uppercase tracking-wide text-muted">Roles</span>
                <div class="flex flex-wrap gap-2">
                    @foreach(['is_match_director' => 'Match Director', 'is_range_officer' => 'Range Officer', 'is_shooter' => 'Shooter'] as $key => $label)
                        <label class="cursor-pointer select-none">
                            <input type="checkbox" wire:model="newRoles.{{ $key }}" class="sr-only peer" @checked(! empty($newRoles[$key]))>
                            <span class="inline-flex items-center whitespace-nowrap rounded-full border border-border bg-surface-2 px-3.5 py-1.5 text-xs font-medium text-secondary transition-colors hover:bg-surface peer-checked:border-accent peer-checked:bg-accent peer-checked:text-white">
                                {{ $label }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <flux:input wire:model="email" label="Email" type="email" placeholder="user@example.com" required />
                </div>
                <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover sm:w-auto w-full">Add Member</flux:button>
            </div>
        </form>
    </div>
</div>
