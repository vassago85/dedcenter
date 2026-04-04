<?php

use App\Notifications\EmailVerificationPin;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')]
    #[Title('Verify Email — DeadCenter')]
    class extends Component {
    public string $code = '';
    public bool $resent = false;

    public function verify(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = auth()->user();

        if (! $user->verifyWithCode($this->code)) {
            $this->addError('code', 'Invalid or expired code. Please try again.');
            return;
        }

        if (! $user->isOnboarded()) {
            $this->redirect(route('welcome'), navigate: true);
            return;
        }

        if ($user->isOwner()) {
            $redirect = route('admin.dashboard');
        } elseif ($user->canScore()) {
            $redirect = route('dashboard');
        } else {
            $redirect = route('score');
        }

        $this->redirect($redirect, navigate: true);
    }

    public function resend(): void
    {
        $user = auth()->user();
        $code = $user->generateVerificationCode();
        $user->notify(new EmailVerificationPin($code));

        $this->resent = true;
        $this->code = '';
        $this->resetErrorBag();
    }
}; ?>

<div>
    <div class="rounded-xl border border-border bg-surface p-8">
        <h1 class="mb-2 text-center text-2xl font-bold text-primary">Verify Your Email</h1>
        <p class="mb-6 text-center text-sm text-muted">
            We sent a 6-digit code to <strong>{{ auth()->user()->email }}</strong>
        </p>

        @if($resent)
            <div class="mb-4 rounded-lg bg-green-600/10 border border-green-600/20 px-4 py-3 text-center text-sm text-green-500">
                A new code has been sent to your email.
            </div>
        @endif

        <form wire:submit="verify" class="space-y-5">
            <div>
                <flux:input
                    wire:model="code"
                    label="Verification Code"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="6"
                    placeholder="000000"
                    required
                    autofocus
                    class="text-center text-2xl tracking-[0.5em] font-mono"
                />
                @error('code')
                    <p class="mt-1 text-sm text-accent">{{ $message }}</p>
                @enderror
            </div>

            <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover w-full">
                Verify Email
            </flux:button>
        </form>

        <div class="mt-6 text-center">
            <button wire:click="resend" class="text-sm text-accent hover:text-accent-hover font-medium">
                Resend Code
            </button>
        </div>

        <div class="mt-4 text-center">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-muted hover:text-secondary transition-colors">
                    Sign out
                </button>
            </form>
        </div>
    </div>
</div>
