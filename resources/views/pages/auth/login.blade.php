<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')]
    #[Title('Login — DeadCenter')]
    class extends Component {
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $this->addError('email', __('auth.failed'));
            return;
        }

        session()->regenerate();

        $redirect = auth()->user()->isOwner()
            ? route('admin.dashboard')
            : route('dashboard');

        $this->redirect($redirect, navigate: true);
    }
}; ?>

<div>
    <div class="rounded-xl border border-border bg-surface p-8">
        <h1 class="mb-6 text-center text-2xl font-bold text-primary">Sign In</h1>

        <form wire:submit="login" class="space-y-5">
            <div>
                <flux:input
                    wire:model="email"
                    label="Email"
                    type="email"
                    placeholder="you@example.com"
                    required
                    autofocus
                />
                @error('email')
                    <p class="mt-1 text-sm text-accent">{{ $message }}</p>
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
                    <p class="mt-1 text-sm text-accent">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-secondary cursor-pointer select-none">
                <input type="checkbox" wire:model="remember"
                    class="rounded border-border bg-surface-2 text-accent focus:ring-accent focus:ring-offset-app" />
                Remember me
            </label>

            <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover w-full">
                Sign In
            </flux:button>
        </form>

        <p class="mt-6 text-center text-sm text-muted">
            Don't have an account?
            <a href="{{ route('register') }}" class="text-accent hover:text-accent-hover font-medium" wire:navigate>
                Register
            </a>
        </p>
    </div>
</div>
