<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')]
    #[Title('Register — DeadCenter')]
    class extends Component {
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        event(new Registered($user));

        Auth::login($user);

        $this->redirect(route('dashboard'), navigate: true); // New users are always members
    }
}; ?>

<div>
    <div class="rounded-xl border border-slate-700 bg-slate-800 p-8 [&_label]:!text-slate-300">
        <h1 class="mb-6 text-center text-2xl font-bold text-white">Create Account</h1>

        <form wire:submit="register" class="space-y-5">
            <div>
                <flux:input
                    wire:model="name"
                    label="Name"
                    type="text"
                    placeholder="Your name"
                    required
                    autofocus
                />
                @error('name')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <flux:input
                    wire:model="email"
                    label="Email"
                    type="email"
                    placeholder="you@example.com"
                    required
                />
                @error('email')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <flux:input
                    wire:model="password"
                    label="Password"
                    type="password"
                    placeholder="••••••••"
                    required
                />
                @error('password')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <flux:input
                    wire:model="password_confirmation"
                    label="Confirm Password"
                    type="password"
                    placeholder="••••••••"
                    required
                />
            </div>

            <flux:button type="submit" variant="primary" class="!bg-red-600 hover:!bg-red-700 w-full">
                Create Account
            </flux:button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-400">
            Already have an account?
            <a href="{{ route('login') }}" class="text-red-400 hover:text-red-300 font-medium" wire:navigate>
                Sign In
            </a>
        </p>
    </div>
</div>
