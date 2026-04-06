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

    public string $caliber = '';
    public string $bullet_brand_type = '';
    public string $bullet_weight = '';
    public string $action_brand = '';
    public string $barrel_brand_length = '';
    public string $trigger_brand = '';
    public string $stock_chassis_brand = '';
    public string $muzzle_brake_silencer_brand = '';
    public string $scope_brand_type = '';
    public string $scope_mount_brand = '';
    public string $bipod_brand = '';
    public string $share_rifle_with = '';
    public string $contact_number = '';
    public string $sa_id_number = '';
    public array $customFieldValues = [];

    public function mount(ShootingMatch $match): void
    {
        $this->match = $match;
        $this->registration = MatchRegistration::where('match_id', $match->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($this->registration) {
            foreach ($this->registration->customValues as $cv) {
                $this->customFieldValues[$cv->match_custom_field_id] = $cv->value;
            }
        }
    }

    public function getTitle(): string
    {
        return $this->match->name . ' — DeadCenter';
    }

    public function loadProfile($profileId): void
    {
        if (! $profileId) return;
        $profile = auth()->user()->equipmentProfiles()->find($profileId);
        if (! $profile) return;

        foreach (\App\Models\UserEquipmentProfile::EQUIPMENT_FIELDS as $field) {
            $this->{$field} = $profile->{$field} ?? '';
        }
    }

    public function preRegister(): void
    {
        if ($this->registration) return;

        $this->registration = MatchRegistration::create([
            'match_id' => $this->match->id,
            'user_id' => auth()->id(),
            'payment_reference' => MatchRegistration::generatePaymentReference(auth()->user()),
            'payment_status' => 'pre_registered',
            'amount' => $this->match->entry_fee,
            'pre_registered_at' => now(),
        ]);

        \App\Services\AchievementService::evaluateEarlyBird($this->match, auth()->id());

        Flux::toast('Pre-registered! You\'ll be notified when full registration opens.', variant: 'success');
    }

    public function register(): void
    {
        if ($this->registration && !$this->registration->isPreRegistered()) {
            return;
        }

        $this->validate([
            'caliber' => 'required|string|max:255',
            'bullet_brand_type' => 'required|string|max:255',
            'bullet_weight' => 'required|string|max:100',
            'barrel_brand_length' => 'required|string|max:255',
            'trigger_brand' => 'required|string|max:255',
            'stock_chassis_brand' => 'required|string|max:255',
            'muzzle_brake_silencer_brand' => 'required|string|max:255',
            'scope_brand_type' => 'required|string|max:255',
            'scope_mount_brand' => 'required|string|max:255',
            'bipod_brand' => 'required|string|max:255',
            'contact_number' => 'required|string|max:50',
            'sa_id_number' => 'nullable|string|max:20',
            'action_brand' => 'nullable|string|max:255',
            'share_rifle_with' => 'nullable|string|max:255',
        ]);

        $data = [
            'payment_status' => $this->match->isFree() ? 'confirmed' : 'pending_payment',
            'amount' => $this->match->entry_fee,
            'sa_id_number' => $this->sa_id_number ?: null,
            'caliber' => $this->caliber,
            'bullet_brand_type' => $this->bullet_brand_type,
            'bullet_weight' => $this->bullet_weight,
            'action_brand' => $this->action_brand ?: null,
            'barrel_brand_length' => $this->barrel_brand_length,
            'trigger_brand' => $this->trigger_brand,
            'stock_chassis_brand' => $this->stock_chassis_brand,
            'muzzle_brake_silencer_brand' => $this->muzzle_brake_silencer_brand,
            'scope_brand_type' => $this->scope_brand_type,
            'scope_mount_brand' => $this->scope_mount_brand,
            'bipod_brand' => $this->bipod_brand,
            'share_rifle_with' => $this->share_rifle_with ?: null,
            'contact_number' => $this->contact_number,
        ];

        if ($this->registration && $this->registration->isPreRegistered()) {
            $this->registration->update($data);
            $this->registration = $this->registration->fresh();
        } else {
            $ref = MatchRegistration::generatePaymentReference(auth()->user());
            $this->registration = MatchRegistration::create([
                ...$data,
                'match_id' => $this->match->id,
                'user_id' => auth()->id(),
                'payment_reference' => $ref,
            ]);
            \App\Services\AchievementService::evaluateEarlyBird($this->match, auth()->id());
        }

        $this->saveCustomFieldValues();

        if ($this->match->isFree()) {
            $this->createShooter();
            Flux::toast('Registered! You are confirmed for this match.', variant: 'success');
        } else {
            Flux::toast('Registered! Please make your EFT payment and upload proof.', variant: 'success');
        }
    }

    private function saveCustomFieldValues(): void
    {
        if (! $this->registration) return;

        foreach ($this->match->customFields()->orderBy('sort_order')->get() as $cf) {
            if ($cf->is_required && empty($this->customFieldValues[$cf->id] ?? null) && $cf->type !== 'checkbox') {
                $this->addError("customFieldValues.{$cf->id}", "{$cf->label} is required.");
                return;
            }
        }

        foreach ($this->customFieldValues as $fieldId => $value) {
            \App\Models\MatchRegistrationCustomValue::updateOrCreate(
                [
                    'match_registration_id' => $this->registration->id,
                    'match_custom_field_id' => $fieldId,
                ],
                ['value' => $value ?? '']
            );
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

    public string $newTeamName = '';

    public function createTeam(): void
    {
        if (! $this->match->isTeamEvent()) return;
        if (! $this->registration || ! $this->registration->isConfirmed()) return;

        $this->validate(['newTeamName' => 'required|string|max:255']);
        $maxSort = $this->match->teams()->max('sort_order') ?? 0;
        $team = $this->match->teams()->create([
            'name' => $this->newTeamName,
            'max_size' => $this->match->team_size,
            'sort_order' => $maxSort + 1,
        ]);
        $shooter = $this->getOrCreateMyShooter();
        if ($shooter) {
            $shooter->update(['team_id' => $team->id]);
        }
        $this->reset('newTeamName');
        Flux::toast("Team '{$team->name}' created! Share the name so others can join.", variant: 'success');
    }

    public function joinTeam(int $teamId): void
    {
        if (! $this->match->isTeamEvent()) return;
        if (! $this->registration || ! $this->registration->isConfirmed()) return;

        $team = $this->match->teams()->findOrFail($teamId);
        if ($team->isFull()) {
            Flux::toast("{$team->name} is full.", variant: 'danger');
            return;
        }
        $shooter = $this->getOrCreateMyShooter();
        if ($shooter) {
            $shooter->update(['team_id' => $team->id]);
            Flux::toast("Joined {$team->name}!", variant: 'success');
        }
    }

    public function leaveTeam(): void
    {
        $shooter = $this->getMyShooter();
        if ($shooter) {
            $shooter->update(['team_id' => null]);
            Flux::toast('Left team.', variant: 'success');
        }
    }

    private function getMyShooter(): ?\App\Models\Shooter
    {
        return \App\Models\Shooter::where('user_id', auth()->id())
            ->whereIn('squad_id', $this->match->squads()->pluck('id'))
            ->first();
    }

    private function getOrCreateMyShooter(): ?\App\Models\Shooter
    {
        $existing = $this->getMyShooter();
        if ($existing) return $existing;

        if (! $this->registration || ! $this->registration->isConfirmed()) return null;

        $this->createShooter();
        return $this->getMyShooter();
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
        $this->match->loadMissing(['organization', 'staff', 'customFields']);

        $org = $this->match->organization;

        $registrants = $this->match->registrations()
            ->where('payment_status', 'confirmed')
            ->with('user:id,name')
            ->get();

        $squads = collect();
        $showSquads = in_array($this->match->status, [
            \App\Enums\MatchStatus::SquaddingOpen,
            \App\Enums\MatchStatus::Active,
            \App\Enums\MatchStatus::Completed,
        ]);
        if ($showSquads) {
            $squads = $this->match->squads()
                ->with(['shooters' => fn ($q) => $q->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get()
                ->reject(fn ($s) => in_array($s->name, ['Default', 'Unassigned']));
        }

        return [
            'targetSets' => $this->match->targetSets()->with('gongs')->orderBy('sort_order')->get(),
            'bankDetails' => [
                'bank_name' => $org?->bank_name ?: Setting::get('bank_name', ''),
                'bank_account_name' => $org?->bank_account_holder ?: Setting::get('bank_account_name', ''),
                'bank_account_number' => $org?->bank_account_number ?: Setting::get('bank_account_number', ''),
                'bank_branch_code' => $org?->bank_branch_code ?: Setting::get('bank_branch_code', ''),
            ],
            'registrants' => $registrants,
            'squads' => $squads,
            'showSquads' => $showSquads,
            'teams' => $this->match->isTeamEvent()
                ? $this->match->teams()
                    ->withCount('shooters')
                    ->with(['shooters:id,team_id,name'])
                    ->orderBy('sort_order')->get()
                : collect(),
            'unteamedCount' => $this->match->isTeamEvent()
                ? $this->match->shooters()->active()->whereNull('team_id')->count()
                : 0,
            'myShooter' => $this->getMyShooter(),
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
            <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-muted">
                @if($match->date)
                    <span>{{ $match->date->format('d M Y') }}</span>
                @endif
                @if($match->location)
                    <span>&bull; {{ $match->location }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Watch Live / View Results banner --}}
    <a href="{{ route('live', $match) }}"
       class="flex items-center justify-between gap-4 rounded-xl border {{ $match->status === \App\Enums\MatchStatus::Active ? 'border-green-700/50 bg-gradient-to-r from-green-900/30 to-surface' : 'border-border bg-surface' }} px-6 py-4 transition-colors hover:border-green-600/60">
        <div class="flex items-center gap-3">
            @if($match->status === \App\Enums\MatchStatus::Active)
                <span class="relative flex h-3 w-3">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex h-3 w-3 rounded-full bg-green-500"></span>
                </span>
                <span class="text-sm font-semibold text-green-400">Match is live &mdash; Watch Live Scores</span>
            @else
                <svg class="h-5 w-5 text-muted" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                </svg>
                <span class="text-sm font-semibold text-secondary">View Scoreboard &amp; Results</span>
            @endif
        </div>
        <svg class="h-5 w-5 {{ $match->status === \App\Enums\MatchStatus::Active ? 'text-green-400' : 'text-muted' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
    </a>

    {{-- Match Info --}}
    <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-primary">Match Information</h2>
            <span class="text-2xl font-bold {{ $match->entry_fee ? 'text-primary' : 'text-green-400' }}">
                {{ $match->entry_fee ? 'R'.number_format($match->entry_fee, 2) : 'Free Entry' }}
            </span>
        </div>

        @php
            $eventBlurb = $match->public_bio ?: $match->notes;
        @endphp
        @if($eventBlurb)
            <p class="text-sm text-secondary whitespace-pre-line">{{ $eventBlurb }}</p>
        @endif

        @if($match->staff->isNotEmpty())
            <div class="rounded-lg border border-border bg-surface-2/40 p-4">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-muted mb-2">Event team</h3>
                <ul class="space-y-2 text-sm">
                    @foreach($match->staff as $u)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-primary">{{ $u->name }}</span>
                            @if($u->pivot->role === 'match_director')
                                <span class="text-xs text-blue-400">Match Director</span>
                            @else
                                <span class="text-xs text-emerald-400">Range Officer</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Target sets summary --}}
        @if($targetSets->isNotEmpty())
            <div class="space-y-2">
                <h3 class="text-sm font-medium text-muted">Target Sets</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($targetSets as $ts)
                        <div class="rounded-lg border border-border bg-surface-2/50 px-3 py-2">
                            <span class="text-sm font-medium text-primary">{{ $ts->label }}</span>
                            <span class="ml-1 text-xs text-muted">({{ $ts->gongs->count() }} targets)</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Squadding link --}}
    @if($match->isSquaddingOpen() && $match->isSelfSquaddingEnabled() && $registration && $registration->isConfirmed())
        <a href="{{ route('matches.squadding', $match) }}"
           class="flex items-center justify-between gap-4 rounded-xl border border-indigo-700/50 bg-gradient-to-r from-indigo-900/30 to-surface px-6 py-4 transition-colors hover:border-indigo-600/60">
            <div class="flex items-center gap-3">
                <svg class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                </svg>
                <span class="text-sm font-semibold text-indigo-400">Squadding is open &mdash; Pick your squad</span>
            </div>
            <svg class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
        </a>
    @endif

    {{-- Registration Section --}}
    <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-primary">Registration</h2>
            <flux:badge size="sm" color="{{ $match->status->color() }}">{{ $match->status->label() }}</flux:badge>
        </div>

        @if($match->isRegistrationClosed() && !$registration)
            <div class="rounded-lg border border-amber-800 bg-amber-900/20 p-4">
                <p class="text-sm font-medium text-amber-400">Registration is closed.</p>
                <p class="mt-1 text-xs text-muted">This match is no longer accepting new entries.</p>
            </div>

        @elseif($match->isPreRegistration() && !$registration)
            <p class="text-sm text-muted">Express your interest in this match. You'll be notified when full registration opens so you can complete your entry and pay.</p>
            <flux:button wire:click="preRegister" variant="primary" class="!bg-violet-600 hover:!bg-violet-700"
                         wire:confirm="Pre-register for {{ $match->name }}?">
                Show Interest
            </flux:button>

        @elseif($match->isPreRegistration() && $registration && $registration->isPreRegistered())
            <div class="rounded-lg border border-violet-800 bg-violet-900/20 p-4">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-violet-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <span class="text-sm font-medium text-violet-400">You're pre-registered!</span>
                </div>
                <p class="mt-1 text-xs text-muted">You'll be notified when full registration opens. If you don't complete registration before it closes, your spot will be released.</p>
                @php $closes = $match->registration_closes_at ?? $match->defaultRegistrationCloseDate(); @endphp
                @if($closes)
                    <p class="mt-1 text-xs text-muted">Registration closes <strong class="text-violet-300">{{ $closes->format('j M Y, H:i') }}</strong>.</p>
                @endif
            </div>

        @elseif($match->isRegistrationOpen() && $registration && $registration->isPreRegistered())
            <div class="rounded-lg border border-sky-800 bg-sky-900/20 p-4 mb-4">
                <p class="text-sm font-medium text-sky-400">Registration is now open! Complete your details below.</p>
            </div>
            <form wire:submit="register" class="space-y-4">
                @include('pages.member.partials.equipment-form')
                <div class="flex justify-end pt-2">
                    <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover">Complete Registration</flux:button>
                </div>
            </form>

        @elseif(! $registration && $match->isRegistrationOpen())
            <p class="text-sm text-muted">Fill in your equipment details and register for this match.</p>
            @php $closes = $match->registration_closes_at ?? $match->defaultRegistrationCloseDate(); @endphp
            @if($closes)
                <p class="text-xs text-muted">Registration closes <strong class="text-sky-300">{{ $closes->format('j M Y, H:i') }}</strong>.</p>
            @endif
            <form wire:submit="register" class="space-y-4">
                @include('pages.member.partials.equipment-form')
                <div class="flex justify-end pt-2">
                    <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover">Register for this Match</flux:button>
                </div>
            </form>

        @elseif(! $registration && ($match->status === \App\Enums\MatchStatus::Draft || $match->status === \App\Enums\MatchStatus::Active || $match->status === \App\Enums\MatchStatus::Completed))
            @if($match->status !== \App\Enums\MatchStatus::Completed)
                <p class="text-sm text-muted">Fill in your equipment details and register for this match.</p>
                <form wire:submit="register" class="space-y-4">
                    @include('pages.member.partials.equipment-form')
                    <div class="flex justify-end pt-2">
                        <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover">Register for this Match</flux:button>
                    </div>
                </form>
            @endif

        @elseif($registration && $registration->isConfirmed())
            {{-- Confirmed --}}
            <div class="rounded-lg border border-green-800 bg-green-900/20 p-4">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <span class="text-sm font-medium text-green-400">Your registration is confirmed!</span>
                </div>
                <p class="mt-1 text-xs text-muted">Reference: {{ $registration->payment_reference }}</p>
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
                    <p class="mt-1 text-xs text-muted">Reason: {{ $registration->admin_notes }}</p>
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
                <p class="mt-1 text-xs text-muted">Reference: {{ $registration->payment_reference }}</p>
            </div>

        @elseif($registration->isPending())
            {{-- Pending payment --}}
            <div class="space-y-4">
                <div class="rounded-lg border border-amber-800 bg-amber-900/20 p-4">
                    <p class="text-sm font-medium text-amber-400">Payment Required</p>
                    <p class="mt-1 text-xs text-muted">Please make an EFT payment using the details below and upload your proof of payment.</p>
                </div>

                {{-- Bank details --}}
                <div class="rounded-lg border border-border bg-surface-2/50 p-4 space-y-2">
                    <h3 class="text-sm font-semibold text-primary">Bank Details</h3>
                    <dl class="grid grid-cols-1 gap-1 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-muted">Bank</dt>
                            <dd class="font-medium text-primary">{{ $bankDetails['bank_name'] ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Account Name</dt>
                            <dd class="font-medium text-primary">{{ $bankDetails['bank_account_name'] ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Account Number</dt>
                            <dd class="font-medium text-primary">{{ $bankDetails['bank_account_number'] ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Branch Code</dt>
                            <dd class="font-medium text-primary">{{ $bankDetails['bank_branch_code'] ?: '—' }}</dd>
                        </div>
                    </dl>

                    <flux:separator />

                    <dl class="grid grid-cols-1 gap-1 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-muted">Payment Reference</dt>
                            <dd class="font-mono font-bold text-accent">{{ $registration->payment_reference }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Amount</dt>
                            <dd class="font-bold text-primary">R{{ number_format($registration->amount, 2) }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Upload POP --}}
                <div class="space-y-3">
                    <h3 class="text-sm font-semibold text-primary">Upload Proof of Payment</h3>
                    <form wire:submit="uploadProof">
                        <div class="flex items-end gap-3">
                            <div class="flex-1">
                                <input type="file" wire:model="proofOfPayment" accept=".jpg,.jpeg,.png,.pdf"
                                       class="block w-full text-sm text-muted file:mr-4 file:rounded-lg file:border-0 file:bg-accent file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary hover:file:bg-accent-hover file:cursor-pointer" />
                                <p class="mt-1 text-xs text-muted">JPG, PNG, or PDF. Max 5MB.</p>
                                @error('proofOfPayment')
                                    <p class="mt-1 text-xs text-accent">{{ $message }}</p>
                                @enderror
                            </div>
                            <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover">
                                Upload
                            </flux:button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>

    {{-- Registered Shooters --}}
    @if($registrants->isNotEmpty() && !$match->isPreRegistration())
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-semibold text-primary">Registered Shooters</h2>
                <span class="rounded-full bg-surface-2 px-2.5 py-0.5 text-xs font-bold text-secondary">{{ $registrants->count() }}</span>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach($registrants as $reg)
                    <span class="rounded-lg border border-border bg-surface-2/50 px-3 py-1.5 text-sm text-secondary">{{ $reg->user?->name ?? 'Unknown' }}</span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Squads --}}
    @if($showSquads && $squads->isNotEmpty())
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <h2 class="text-lg font-semibold text-primary">Squads</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach($squads as $squad)
                    <div class="rounded-lg border border-border bg-surface-2/40 p-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold text-primary">{{ $squad->name }}</h3>
                            <span class="text-xs text-muted">{{ $squad->shooters->count() }} shooters</span>
                        </div>
                        @if($squad->shooters->isNotEmpty())
                            <ul class="space-y-1">
                                @foreach($squad->shooters as $shooter)
                                    <li class="flex items-center gap-2 text-sm {{ $shooter->user_id === auth()->id() ? 'text-green-400 font-medium' : 'text-secondary' }}">
                                        @if($shooter->user_id === auth()->id())
                                            <span class="h-1.5 w-1.5 rounded-full bg-green-400"></span>
                                        @else
                                            <span class="h-1.5 w-1.5 rounded-full bg-surface-2"></span>
                                        @endif
                                        {{ $shooter->name }}
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-xs text-muted">No shooters assigned yet.</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Team Selection --}}
    @if($match->isTeamEvent() && ($teams->isNotEmpty() || ($registration && $registration->isConfirmed())))
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-primary">Teams</h2>
                <p class="text-xs text-muted">Register individually, then pick a team</p>
            </div>
            @php $myTeamId = $myShooter?->team_id; @endphp

            @if($myTeamId)
                @php $currentTeam = $teams->firstWhere('id', $myTeamId); @endphp
                @if($currentTeam)
                    <div class="rounded-lg border border-green-800 bg-green-900/20 p-4">
                        <p class="text-sm font-medium text-green-400">You're on team: {{ $currentTeam->name }}</p>
                        <p class="text-xs text-muted mt-1">{{ $currentTeam->shooters_count }}/{{ $currentTeam->effectiveMaxSize() }} members</p>
                    </div>
                    <button wire:click="leaveTeam" class="text-xs text-muted hover:text-secondary transition-colors">Leave team</button>
                @endif
            @endif

            @if($unteamedCount > 0 && $registration && $registration->isConfirmed())
                <div class="rounded-lg border border-amber-800/40 bg-amber-900/10 px-4 py-3">
                    <p class="text-sm text-amber-400">{{ $unteamedCount }} confirmed {{ Str::plural('shooter', $unteamedCount) }} still looking for a team</p>
                </div>
            @endif

            @if($teams->isNotEmpty())
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach($teams as $team)
                        @php $isMyTeam = $myTeamId === $team->id; @endphp
                        <div class="rounded-lg border {{ $isMyTeam ? 'border-green-600 ring-2 ring-green-600/30' : 'border-border' }} bg-surface-2/40 p-4 space-y-2 {{ $team->isFull() && !$isMyTeam ? 'opacity-50' : '' }}">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-bold text-primary">{{ $team->name }}</span>
                                <span class="text-xs text-muted">{{ $team->shooters_count }}/{{ $team->effectiveMaxSize() }}</span>
                            </div>
                            @if($team->shooters->isNotEmpty())
                                <ul class="space-y-0.5">
                                    @foreach($team->shooters as $member)
                                        <li class="flex items-center gap-1.5 text-xs {{ $member->user_id === auth()->id() ? 'text-green-400 font-medium' : 'text-secondary' }}">
                                            @if($member->user_id === auth()->id())
                                                <span class="h-1.5 w-1.5 rounded-full bg-green-400 shrink-0"></span>
                                            @else
                                                <span class="h-1.5 w-1.5 rounded-full bg-surface-2 shrink-0"></span>
                                            @endif
                                            {{ $member->name }}
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-xs text-muted">No members yet — be the first!</p>
                            @endif
                            @if(!$isMyTeam && !$team->isFull() && $registration && $registration->isConfirmed())
                                <flux:button wire:click="joinTeam({{ $team->id }})" variant="primary" size="xs" class="w-full">Join</flux:button>
                            @elseif($isMyTeam)
                                <p class="text-center text-xs text-green-400/60">Your team</p>
                            @elseif($team->isFull())
                                <p class="text-center text-xs text-muted">Full</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if($registration && $registration->isConfirmed() && !$myTeamId)
                <div class="border-t border-border pt-4 space-y-3">
                    <h3 class="text-sm font-medium text-secondary">Create a New Team</h3>
                    <p class="text-xs text-muted">Create a team and share the name with friends so they can join.</p>
                    <div class="flex gap-3 items-end">
                        <div class="flex-1"><flux:input wire:model="newTeamName" placeholder="e.g. Team Alpha" /></div>
                        <flux:button wire:click="createTeam" size="sm" variant="primary" class="!bg-accent hover:!bg-accent-hover">Create</flux:button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Scoreboard links --}}
    <div class="flex items-center justify-center gap-3">
        <flux:button href="{{ route('live', $match) }}" variant="primary" class="{{ $match->status === \App\Enums\MatchStatus::Active ? '!bg-green-600 hover:!bg-green-700' : '!bg-surface-2 hover:!bg-surface-2' }}">
            {{ $match->status === \App\Enums\MatchStatus::Active ? 'Watch Live Scores' : 'View Results' }}
        </flux:button>
        <flux:button href="{{ route('scoreboard', $match) }}" variant="ghost">
            TV Scoreboard
        </flux:button>
    </div>
</div>
