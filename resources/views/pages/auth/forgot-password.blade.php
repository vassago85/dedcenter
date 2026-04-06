<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')]
    #[Title('Forgot Password — DeadCenter')]
    class extends Component {
    public string $email = '';
    public bool $sent = false;

    public function sendResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(['email' => $this->email]);

        if ($status === Password::RESET_LINK_SENT) {
            $this->sent = true;
            $this->resetErrorBag();
        } else {
            $this->addError('email', __($status));
        }
    }
}; ?>

<div>
    <div class="rounded-xl border border-border bg-surface p-8">
        <h1 class="mb-2 text-center text-2xl font-bold text-primary">Forgot Password</h1>
        <p class="mb-6 text-center text-sm text-muted">
            Enter your email and we'll send you a link to reset your password.
        </p>

        @if($sent)
            <div class="mb-4 rounded-lg bg-green-600/10 border border-green-600/20 px-4 py-3 text-center text-sm text-green-500">
                We've emailed you a password reset link. Check your inbox.
            </div>
        @endif

        <form wire:submit="sendResetLink" class="space-y-5">
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

            <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover w-full">
                Send Reset Link
            </flux:button>
        </form>

        <p class="mt-6 text-center text-sm text-muted">
            <a href="{{ route('login') }}" class="text-accent hover:text-accent-hover font-medium" wire:navigate>
                Back to Sign In
            </a>
        </p>
    </div>
</div>
