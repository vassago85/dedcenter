<?php

use App\Models\User;
use App\Notifications\EmailVerificationPin;
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
    public bool $accept_terms = false;

    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'accept_terms' => ['accepted'],
        ], [
            'accept_terms.accepted' => 'You must accept the Terms and Conditions and Privacy Policy.',
        ]);

        unset($validated['accept_terms']);
        $validated['password'] = Hash::make($validated['password']);
        $validated['accepted_terms_at'] = now();

        $user = User::create($validated);

        event(new Registered($user));

        $code = $user->generateVerificationCode();
        $user->notify(new EmailVerificationPin($code));

        Auth::login($user);

        $this->redirect(route('verification.notice'));
    }
}; ?>

<div>
    <div class="rounded-xl border border-border bg-surface p-8">
        <h1 class="mb-6 text-center text-2xl font-bold text-primary">Create Account</h1>

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
                    <p class="mt-1 text-sm text-accent">{{ $message }}</p>
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

            <div>
                <flux:input
                    wire:model="password_confirmation"
                    label="Confirm Password"
                    type="password"
                    placeholder="••••••••"
                    required
                />
            </div>

            <div>
                <label class="flex items-start gap-2 text-sm text-secondary cursor-pointer select-none">
                    <input type="checkbox" wire:model="accept_terms"
                        class="mt-0.5 rounded border-border bg-surface-2 text-accent focus:ring-accent focus:ring-offset-app" />
                    <span>
                        I agree to the
                        <a href="{{ route('terms') }}" target="_blank" class="text-accent hover:text-accent-hover font-medium">Terms and Conditions</a>
                        and
                        <a href="{{ route('privacy') }}" target="_blank" class="text-accent hover:text-accent-hover font-medium">Privacy Policy</a>
                    </span>
                </label>
                @error('accept_terms')
                    <p class="mt-1 text-sm text-accent">{{ $message }}</p>
                @enderror
            </div>

            <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover w-full">
                Create Account
            </flux:button>
        </form>

        <p class="mt-6 text-center text-sm text-muted">
            Already have an account?
            <a href="{{ route('login') }}" class="text-accent hover:text-accent-hover font-medium" wire:navigate>
                Sign In
            </a>
        </p>
    </div>
</div>
