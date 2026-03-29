<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Flux\Flux;

new #[Layout('components.layouts.app')]
    #[Title('Settings — DeadCenter')]
    class extends Component {
    public string $name = '';
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->name = auth()->user()->name;
    }

    public function updateProfile(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        auth()->user()->update(['name' => $this->name]);
        Flux::toast('Profile updated.', variant: 'success');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        if (! Hash::check($this->current_password, auth()->user()->password)) {
            $this->addError('current_password', 'The current password is incorrect.');
            return;
        }

        auth()->user()->update(['password' => Hash::make($this->password)]);
        $this->reset(['current_password', 'password', 'password_confirmation']);
        Flux::toast('Password updated.', variant: 'success');
    }
}; ?>

<div class="space-y-8 max-w-2xl">
    <div>
        <flux:heading size="xl">Settings</flux:heading>
        <p class="mt-1 text-sm text-muted">Manage your account details.</p>
    </div>

    {{-- Profile --}}
    <div class="rounded-xl border border-border bg-surface p-6 space-y-5">
        <h2 class="text-lg font-semibold text-primary">Profile</h2>

        <form wire:submit="updateProfile" class="space-y-4">
            <div>
                <flux:input wire:model="name" label="Name" type="text" required />
                @error('name')
                    <p class="mt-1 text-sm text-accent">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-secondary mb-1">Email</label>
                <p class="text-sm text-muted">{{ auth()->user()->email }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-secondary mb-1">Role</label>
                <flux:badge size="sm" color="zinc">{{ auth()->user()->roleLabel() }}</flux:badge>
                @if(auth()->user()->organizations->isNotEmpty())
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach(auth()->user()->organizations as $org)
                            <flux:badge size="sm" color="{{ match($org->pivot->role) { 'owner' => 'amber', 'match_director' => 'blue', 'range_officer' => 'green', default => 'zinc' } }}">
                                {{ $org->name }}: {{ ucfirst(str_replace('_', ' ', $org->pivot->role)) }}
                            </flux:badge>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="pt-2">
                <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover">
                    Save Profile
                </flux:button>
            </div>
        </form>
    </div>

    {{-- Password --}}
    <div class="rounded-xl border border-border bg-surface p-6 space-y-5">
        <h2 class="text-lg font-semibold text-primary">Change Password</h2>

        <form wire:submit="updatePassword" class="space-y-4">
            <div>
                <flux:input wire:model="current_password" label="Current Password" type="password" required />
                @error('current_password')
                    <p class="mt-1 text-sm text-accent">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <flux:input wire:model="password" label="New Password" type="password" required />
                @error('password')
                    <p class="mt-1 text-sm text-accent">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <flux:input wire:model="password_confirmation" label="Confirm New Password" type="password" required />
            </div>

            <div class="pt-2">
                <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover">
                    Update Password
                </flux:button>
            </div>
        </form>
    </div>
</div>
