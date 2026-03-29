<?php

use App\Models\Organization;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Organization Admins')]
    class extends Component {
    public Organization $organization;
    public string $email = '';

    public function addAdmin(): void
    {
        $this->validate(['email' => 'required|email']);

        $user = User::where('email', $this->email)->first();

        if (! $user) {
            $this->addError('email', 'No user found with that email.');
            return;
        }

        if ($this->organization->admins()->where('user_id', $user->id)->exists()) {
            $this->addError('email', 'This user is already an admin.');
            return;
        }

        $this->organization->admins()->attach($user->id, ['role' => 'admin']);
        $this->reset('email');
        Flux::toast("{$user->name} added as admin.", variant: 'success');
    }

    public function removeAdmin(int $userId): void
    {
        $pivot = $this->organization->admins()->where('user_id', $userId)->first();

        if ($pivot && $pivot->pivot->role === 'owner') {
            Flux::toast('Cannot remove the owner.', variant: 'danger');
            return;
        }

        $this->organization->admins()->detach($userId);
        Flux::toast('Admin removed.', variant: 'success');
    }

    public function with(): array
    {
        return [
            'admins' => $this->organization->admins()->orderByPivot('role')->get(),
        ];
    }
}; ?>

<div class="space-y-6 max-w-2xl">
    <div>
        <flux:heading size="xl">Admins</flux:heading>
        <p class="mt-1 text-sm text-slate-400">{{ $organization->name }} — Manage who can administer this organization.</p>
    </div>

    {{-- Current admins --}}
    <div class="rounded-xl border border-slate-700 bg-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700 text-left text-slate-400">
                        <th class="px-6 py-3 font-medium">Name</th>
                        <th class="px-6 py-3 font-medium">Email</th>
                        <th class="px-6 py-3 font-medium">Role</th>
                        <th class="px-6 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700">
                    @foreach($admins as $admin)
                        <tr class="hover:bg-slate-700/30 transition-colors" wire:key="admin-{{ $admin->id }}">
                            <td class="px-6 py-3 font-medium text-white">{{ $admin->name }}</td>
                            <td class="px-6 py-3 text-slate-300">{{ $admin->email }}</td>
                            <td class="px-6 py-3">
                                @if($admin->pivot->role === 'owner')
                                    <flux:badge size="sm" color="amber">Owner</flux:badge>
                                @else
                                    <flux:badge size="sm" color="blue">Admin</flux:badge>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-right">
                                @if($admin->pivot->role !== 'owner')
                                    <flux:button size="sm" variant="ghost" class="!text-red-400 hover:!text-red-300"
                                                 wire:click="removeAdmin({{ $admin->id }})"
                                                 wire:confirm="Remove {{ $admin->name }} as admin?">
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

    {{-- Add admin --}}
    <div class="rounded-xl border border-dashed border-slate-600 bg-slate-800/50 p-6 space-y-4">
        <h3 class="text-sm font-medium text-slate-300">Add Admin</h3>
        <p class="text-xs text-slate-500">Enter the email of a registered user to grant them admin access.</p>
        <form wire:submit="addAdmin" class="flex gap-3 items-end">
            <div class="flex-1">
                <flux:input wire:model="email" label="Email" type="email" placeholder="user@example.com" required />
            </div>
            <flux:button type="submit" size="sm" variant="primary" class="!bg-red-600 hover:!bg-red-700">Add Admin</flux:button>
        </form>
    </div>
</div>
