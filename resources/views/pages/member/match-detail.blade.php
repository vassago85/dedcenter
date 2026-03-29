<?php

use App\Models\ShootingMatch;
use App\Models\MatchRegistration;
use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Flux\Flux;

new #[Layout('components.layouts.app')]
    class extends Component {
    use WithFileUploads;

    public ShootingMatch $match;
    public ?MatchRegistration $registration = null;
    public $proofOfPayment;

    public function mount(ShootingMatch $match): void
    {
        $this->match = $match;
        $this->registration = MatchRegistration::where('match_id', $match->id)
            ->where('user_id', auth()->id())
            ->first();
    }

    public function getTitle(): string
    {
        return $this->match->name . ' — DeadCenter';
    }

    public function register(): void
    {
        if ($this->registration) {
            return;
        }

        $ref = MatchRegistration::generatePaymentReference(auth()->user());

        $this->registration = MatchRegistration::create([
            'match_id' => $this->match->id,
            'user_id' => auth()->id(),
            'payment_reference' => $ref,
            'payment_status' => $this->match->isFree() ? 'confirmed' : 'pending_payment',
            'amount' => $this->match->entry_fee,
        ]);

        if ($this->match->isFree()) {
            $this->createShooter();
            Flux::toast('Registered! You are confirmed for this match.', variant: 'success');
        } else {
            Flux::toast('Registered! Please make your EFT payment and upload proof.', variant: 'success');
        }
    }

    public function uploadProof(): void
    {
        $this->validate([
            'proofOfPayment' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $path = $this->proofOfPayment->store(
            'proof-of-payment/' . auth()->id(),
            'public'
        );

        $this->registration->update([
            'proof_of_payment_path' => $path,
            'payment_status' => 'proof_submitted',
        ]);

        $this->registration = $this->registration->fresh();
        $this->proofOfPayment = null;

        Flux::toast('Proof of payment uploaded. It will be reviewed by an administrator.', variant: 'success');
    }

    private function createShooter(): void
    {
        $squad = $this->match->squads()->firstOrCreate(
            ['name' => 'Default'],
            ['sort_order' => 0]
        );

        $maxSort = $squad->shooters()->max('sort_order') ?? 0;

        \App\Models\Shooter::create([
            'squad_id' => $squad->id,
            'name' => auth()->user()->name,
            'user_id' => auth()->id(),
            'sort_order' => $maxSort + 1,
        ]);
    }

    public function with(): array
    {
        return [
            'targetSets' => $this->match->targetSets()->with('gongs')->orderBy('sort_order')->get(),
            'bankDetails' => [
                'bank_name' => Setting::get('bank_name', ''),
                'bank_account_name' => Setting::get('bank_account_name', ''),
                'bank_account_number' => Setting::get('bank_account_number', ''),
                'bank_branch_code' => Setting::get('bank_branch_code', ''),
            ],
        ];
    }
}; ?>

<div class="space-y-8 max-w-4xl">
    {{-- Header --}}
    <div class="flex items-center gap-4">
        <flux:button href="{{ route('matches') }}" variant="ghost" size="sm">
            <svg class="mr-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
            Back
        </flux:button>
        <div>
            <flux:heading size="xl">{{ $match->name }}</flux:heading>
            <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-slate-400">
                @if($match->date)
                    <span>{{ $match->date->format('d M Y') }}</span>
                @endif
                @if($match->location)
                    <span>&bull; {{ $match->location }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Match Info --}}
    <div class="rounded-xl border border-slate-700 bg-slate-800 p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white">Match Information</h2>
            <span class="text-2xl font-bold {{ $match->entry_fee ? 'text-white' : 'text-green-400' }}">
                {{ $match->entry_fee ? 'R'.number_format($match->entry_fee, 2) : 'Free Entry' }}
            </span>
        </div>

        @if($match->notes)
            <p class="text-sm text-slate-300">{{ $match->notes }}</p>
        @endif

        {{-- Target sets summary --}}
        @if($targetSets->isNotEmpty())
            <div class="space-y-2">
                <h3 class="text-sm font-medium text-slate-400">Target Sets</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($targetSets as $ts)
                        <div class="rounded-lg border border-slate-600 bg-slate-700/50 px-3 py-2">
                            <span class="text-sm font-medium text-white">{{ $ts->label }}</span>
                            <span class="ml-1 text-xs text-slate-400">({{ $ts->gongs->count() }} targets)</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Registration Section --}}
    <div class="rounded-xl border border-slate-700 bg-slate-800 p-6 space-y-4">
        <h2 class="text-lg font-semibold text-white">Registration</h2>

        @if(! $registration)
            {{-- Not registered --}}
            <p class="text-sm text-slate-400">Register for this match to participate.</p>
            <flux:button wire:click="register" variant="primary" class="!bg-red-600 hover:!bg-red-700"
                         wire:confirm="Register for this match?">
                Register for this Match
            </flux:button>

        @elseif($registration->isConfirmed())
            {{-- Confirmed --}}
            <div class="rounded-lg border border-green-800 bg-green-900/20 p-4">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <span class="text-sm font-medium text-green-400">Your registration is confirmed!</span>
                </div>
                <p class="mt-1 text-xs text-slate-400">Reference: {{ $registration->payment_reference }}</p>
            </div>

        @elseif($registration->isRejected())
            {{-- Rejected --}}
            <div class="rounded-lg border border-red-800 bg-red-900/20 p-4">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <span class="text-sm font-medium text-red-400">Your registration was rejected.</span>
                </div>
                @if($registration->admin_notes)
                    <p class="mt-1 text-xs text-slate-400">Reason: {{ $registration->admin_notes }}</p>
                @endif
            </div>

        @elseif($registration->isProofSubmitted())
            {{-- Awaiting review --}}
            <div class="rounded-lg border border-blue-800 bg-blue-900/20 p-4">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <span class="text-sm font-medium text-blue-400">Your proof of payment is under review.</span>
                </div>
                <p class="mt-1 text-xs text-slate-400">Reference: {{ $registration->payment_reference }}</p>
            </div>

        @elseif($registration->isPending())
            {{-- Pending payment --}}
            <div class="space-y-4">
                <div class="rounded-lg border border-amber-800 bg-amber-900/20 p-4">
                    <p class="text-sm font-medium text-amber-400">Payment Required</p>
                    <p class="mt-1 text-xs text-slate-400">Please make an EFT payment using the details below and upload your proof of payment.</p>
                </div>

                {{-- Bank details --}}
                <div class="rounded-lg border border-slate-600 bg-slate-700/50 p-4 space-y-2">
                    <h3 class="text-sm font-semibold text-white">Bank Details</h3>
                    <dl class="grid grid-cols-1 gap-1 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-slate-400">Bank</dt>
                            <dd class="font-medium text-white">{{ $bankDetails['bank_name'] ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Account Name</dt>
                            <dd class="font-medium text-white">{{ $bankDetails['bank_account_name'] ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Account Number</dt>
                            <dd class="font-medium text-white">{{ $bankDetails['bank_account_number'] ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Branch Code</dt>
                            <dd class="font-medium text-white">{{ $bankDetails['bank_branch_code'] ?: '—' }}</dd>
                        </div>
                    </dl>

                    <flux:separator />

                    <dl class="grid grid-cols-1 gap-1 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-slate-400">Payment Reference</dt>
                            <dd class="font-mono font-bold text-red-400">{{ $registration->payment_reference }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Amount</dt>
                            <dd class="font-bold text-white">R{{ number_format($registration->amount, 2) }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Upload POP --}}
                <div class="space-y-3">
                    <h3 class="text-sm font-semibold text-white">Upload Proof of Payment</h3>
                    <form wire:submit="uploadProof">
                        <div class="flex items-end gap-3">
                            <div class="flex-1">
                                <input type="file" wire:model="proofOfPayment" accept=".jpg,.jpeg,.png,.pdf"
                                       class="block w-full text-sm text-slate-400 file:mr-4 file:rounded-lg file:border-0 file:bg-red-600 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-red-700 file:cursor-pointer" />
                                <p class="mt-1 text-xs text-slate-500">JPG, PNG, or PDF. Max 5MB.</p>
                                @error('proofOfPayment')
                                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <flux:button type="submit" variant="primary" class="!bg-red-600 hover:!bg-red-700">
                                Upload
                            </flux:button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>

    {{-- Scoreboard link --}}
    <div class="text-center">
        <flux:button href="{{ route('scoreboard', $match) }}" variant="ghost">
            View Scoreboard
        </flux:button>
    </div>
</div>
