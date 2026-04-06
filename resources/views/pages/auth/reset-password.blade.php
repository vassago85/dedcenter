<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')]
    #[Title('Reset Password — DeadCenter')]
    class extends Component {
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->query('email', '');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            session()->flash('status', __($status));
            $this->redirect(route('login'));
            return;
        }

        $this->addError('email', __($status));
    }
}; ?>

<div>
    <div class="rounded-xl border border-border bg-surface p-8">
        <h1 class="mb-2 text-center text-2xl font-bold text-primary">Reset Password</h1>
        <p class="mb-6 text-center text-sm text-muted">
            Enter your new password below.
        </p>

        <form wire:submit="resetPassword" class="space-y-5">
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
                    label="New Password"
                    type="password"
                    placeholder="••••••••"
                    required
                />
                @error('password')
                    <p class="mt-1 text-sm text-accent">{{ $message }}</p>
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

            <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover w-full">
                Reset Password
            </flux:button>
        </form>

        <p class="mt-6 text-center text-sm text-muted">
            <a href="{{ route('login') }}" class="text-accent hover:text-accent-hover font-medium" wire:navigate>
                Back to Sign In
            </a>
        </p>
    </div>
</div>
