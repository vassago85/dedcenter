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

        $redirect = auth()->user()->isAdmin()
            ? route('admin.dashboard')
            : route('dashboard');

        $this->redirect($redirect, navigate: true);
    }
}; ?>

<div>
    <div class="rounded-xl border border-slate-700 bg-slate-800 p-8 [&_label]:!text-slate-300">
        <h1 class="mb-6 text-center text-2xl font-bold text-white">Sign In</h1>

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

            <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer select-none">
                <input type="checkbox" wire:model="remember"
                    class="rounded border-slate-600 bg-slate-700 text-red-600 focus:ring-red-500 focus:ring-offset-slate-800" />
                Remember me
            </label>

            <flux:button type="submit" variant="primary" class="!bg-red-600 hover:!bg-red-700 w-full">
                Sign In
            </flux:button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-400">
            Don't have an account?
            <a href="{{ route('register') }}" class="text-red-400 hover:text-red-300 font-medium" wire:navigate>
                Register
            </a>
        </p>
    </div>
</div>
