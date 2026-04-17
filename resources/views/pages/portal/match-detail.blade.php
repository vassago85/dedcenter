<?php

use App\Models\Organization;
use App\Models\Setting;
use App\Models\ShootingMatch;
use App\Models\MatchRegistration;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Flux\Flux;

new #[Layout('components.layouts.portal')]
    class extends Component {
    use WithFileUploads;

    public Organization $organization;
    public ShootingMatch $match;
    public ?MatchRegistration $registration = null;
    public $proofOfPayment;

    // Equipment / registration fields
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

    public function mount(Organization $organization, ShootingMatch $match): void
    {
        $this->organization = $organization;
        $this->match = $match;

        if (auth()->check()) {
            $this->registration = MatchRegistration::where('match_id', $match->id)
                ->where('user_id', auth()->id())
                ->first();

            if ($this->registration) {
                foreach ($this->registration->customValues as $cv) {
                    $this->customFieldValues[$cv->match_custom_field_id] = $cv->value;
                }
            }
        }
    }

    public function getTitle(): string
    {
        return $this->match->name . ' — ' . $this->organization->name;
    }

    public function loadProfile($profileId): void
    {
        if (! $profileId || ! auth()->check()) return;
        $profile = auth()->user()->equipmentProfiles()->find($profileId);
        if (! $profile) return;

        foreach (\App\Models\UserEquipmentProfile::EQUIPMENT_FIELDS as $field) {
            $this->{$field} = $profile->{$field} ?? '';
        }
    }

    public function preRegister(): void
    {
        if (! auth()->check()) { $this->redirect(route('login'), navigate: true); return; }
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
        if (! auth()->check()) {
            $this->redirect(route('login'), navigate: true);
            return;
        }

        if (! $this->match->canRegister() || $this->match->isRegistrationPastDeadline()) {
            Flux::toast('Registration is closed for this match.', variant: 'danger');
            return;
        }

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
            Flux::toast('Registered! You are confirmed.', variant: 'success');
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

        $path = $this->proofOfPayment->store('proof-of-payment/' . auth()->id(), 'public');

        $this->registration->update([
            'proof_of_payment_path' => $path,
            'payment_status' => 'proof_submitted',
        ]);

        $this->registration = $this->registration->fresh();
        $this->proofOfPayment = null;

        Flux::toast('Proof uploaded. It will be reviewed shortly.', variant: 'success');
    }

    public string $newTeamName = '';

    public function createTeam(): void
    {
        if (! auth()->check() || ! $this->match->isTeamEvent()) return;
        $this->validate(['newTeamName' => 'required|string|max:255']);
        $maxSort = $this->match->teams()->max('sort_order') ?? 0;
        $team = $this->match->teams()->create([
            'name' => $this->newTeamName,
            'max_size' => $this->match->team_size,
            'sort_order' => $maxSort + 1,
        ]);
        $shooter = $this->getMyShooter();
        if ($shooter) {
            $shooter->update(['team_id' => $team->id]);
        }
        $this->reset('newTeamName');
        Flux::toast("Team '{$team->name}' created!", variant: 'success');
    }

    public function joinTeam(int $teamId): void
    {
        if (! auth()->check() || ! $this->match->isTeamEvent()) return;
        $team = $this->match->teams()->findOrFail($teamId);
        if ($team->isFull()) {
            Flux::toast("{$team->name} is full.", variant: 'danger');
            return;
        }
        $shooter = $this->getMyShooter();
        if ($shooter) {
            $shooter->update(['team_id' => $team->id]);
            Flux::toast("Joined {$team->name}!", variant: 'success');
        }
    }

    public function leaveTeam(): void
    {
        if (! auth()->check()) return;
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

    private function createShooter(): void
    {
        $squad = $this->match->squads()->firstOrCreate(['name' => 'Default'], ['sort_order' => 0]);
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
        $this->match->loadMissing(['staff', 'customFields']);

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
                'bank_name' => $this->organization->bank_name ?: Setting::get('bank_name', ''),
                'bank_account_name' => $this->organization->bank_account_holder ?: Setting::get('bank_account_name', ''),
                'bank_account_number' => $this->organization->bank_account_number ?: Setting::get('bank_account_number', ''),
                'bank_branch_code' => $this->organization->bank_branch_code ?: Setting::get('bank_branch_code', ''),
            ],
            'registrants' => $registrants,
            'squads' => $squads,
            'showSquads' => $showSquads,
            'teams' => $this->match->isTeamEvent()
                ? $this->match->teams()->withCount('shooters')->orderBy('sort_order')->get()
                : collect(),
            'myShooter' => auth()->check() ? $this->getMyShooter() : null,
        ];
    }
}; ?>

<div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8 space-y-8">
    {{-- Header --}}
    <div class="flex items-center gap-4">
        <a href="{{ route('portal.matches', $organization) }}" class="inline-flex items-center rounded-lg px-3 py-1.5 text-sm font-medium text-secondary hover:text-primary hover:bg-white/10 transition-colors">
            <x-icon name="chevron-left" class="mr-1 h-4 w-4" />
            Back
        </a>
    </div>

    {{-- Match info --}}
    <div class="rounded-xl border border-white/10 bg-app p-8 space-y-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-primary">{{ $match->name }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-muted">
                    @if($match->date)
                        <span>{{ $match->date->format('d M Y') }}</span>
                    @endif
                    @if($match->location)
                        <span>&bull; {{ $match->location }}</span>
                    @endif
                </div>
            </div>
            <span class="text-3xl font-bold {{ $match->entry_fee ? 'text-primary' : 'text-green-400' }} whitespace-nowrap">
                {{ $match->entry_fee ? 'R'.number_format($match->entry_fee, 2) : 'Free' }}
            </span>
        </div>

        <x-portal-ad-slot class="mt-4" :organization="$organization" placement="portal_match_detail_banner" variant="block" />

        @php
            $eventBlurb = $match->public_bio ?: $match->notes;
        @endphp
        @if($eventBlurb)
            <p class="text-sm text-secondary leading-relaxed whitespace-pre-line">{{ $eventBlurb }}</p>
        @endif

        @if($match->staff->isNotEmpty())
            <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-muted mb-2">Event team</h3>
                <ul class="space-y-2 text-sm">
                    @foreach($match->staff as $u)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-primary">{{ $u->name }}</span>
                            @if($u->pivot->role === 'match_director')
                                <span class="text-xs text-sky-400">Match Director</span>
                            @else
                                <span class="text-xs text-emerald-400">Range Officer</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($targetSets->isNotEmpty())
            <div class="space-y-2">
                <h3 class="text-sm font-medium text-muted">Target Sets</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($targetSets as $ts)
                        <div class="rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                            <span class="text-sm font-medium text-primary">{{ $ts->label }}</span>
                            <span class="ml-1 text-xs text-muted">({{ $ts->gongs->count() }} targets)</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Registration --}}
    <div class="rounded-xl border border-white/10 bg-app p-8 space-y-4">
        <h2 class="text-xl font-bold text-primary">Registration</h2>

        @guest
            <p class="text-sm text-muted">Sign in or register to participate in this match.</p>
            <div class="flex gap-3">
                <a href="{{ route('login') }}" class="portal-bg-primary portal-bg-primary-hover rounded-lg px-5 py-2.5 text-sm font-medium text-primary transition-colors">Sign In</a>
                <a href="{{ route('register') }}" class="rounded-lg border border-white/20 bg-white/5 px-5 py-2.5 text-sm font-medium text-primary hover:bg-white/10 transition-colors">Register Account</a>
            </div>
        @endguest

        @auth
            @if($match->isRegistrationClosed() && !$registration)
                <div class="rounded-lg border border-amber-800 bg-amber-900/20 p-4">
                    <p class="text-sm font-medium text-amber-400">Registration is closed.</p>
                </div>

            @elseif($match->isPreRegistration() && !$registration)
                <p class="text-sm text-muted">Express your interest. You'll be notified when full registration opens so you can complete your entry and pay.</p>
                <button wire:click="preRegister" wire:confirm="Show interest in {{ $match->name }}?"
                        class="portal-bg-primary portal-bg-primary-hover rounded-lg px-6 py-2.5 text-sm font-semibold text-primary transition-colors">
                    Show Interest
                </button>

            @elseif($match->isPreRegistration() && $registration && $registration->isPreRegistered())
                <div class="rounded-lg border border-violet-800 bg-violet-900/20 p-4">
                    <p class="text-sm font-medium text-violet-400">You've shown interest! You'll be notified when registration opens.</p>
                    <p class="mt-1 text-xs text-muted">If you don't complete registration before it closes, your spot will be released.</p>
                    @php $closes = $match->registration_closes_at ?? $match->defaultRegistrationCloseDate(); @endphp
                    @if($closes)
                        <p class="mt-1 text-xs text-muted">Registration closes <strong class="text-violet-300">{{ $closes->format('j M Y, H:i') }}</strong>.</p>
                    @endif
                </div>

            @elseif($match->isRegistrationOpen() && $registration && $registration->isPreRegistered())
                <div class="rounded-lg border border-sky-800 bg-sky-900/20 p-4 mb-4">
                    <p class="text-sm font-medium text-sky-400">Registration is open! Complete your details below.</p>
                </div>
                <form wire:submit="register" class="space-y-4">
                    @include('pages.member.partials.equipment-form')
                    <div class="flex justify-end pt-2">
                        <button type="submit" class="portal-bg-primary portal-bg-primary-hover rounded-lg px-6 py-2.5 text-sm font-semibold text-primary transition-colors">Complete Registration</button>
                    </div>
                </form>

            @elseif(! $registration && $match->canRegister() && ! $match->isRegistrationPastDeadline())
                <p class="text-sm text-muted">Fill in your equipment details and register for this match.</p>
                <form wire:submit="register" class="space-y-4">
                    @include('pages.member.partials.equipment-form')
                    <div class="flex justify-end pt-2">
                        <button type="submit" class="portal-bg-primary portal-bg-primary-hover rounded-lg px-6 py-2.5 text-sm font-semibold text-primary transition-colors">Register for this Match</button>
                    </div>
                </form>

            @elseif($registration && $registration->isConfirmed())
                <div class="rounded-lg border border-green-800 bg-green-900/20 p-4">
                    <div class="flex items-center gap-2">
                        <x-icon name="circle-check" class="h-5 w-5 text-green-400" />
                        <span class="text-sm font-medium text-green-400">Your registration is confirmed!</span>
                    </div>
                    <p class="mt-1 text-xs text-muted">Reference: {{ $registration->payment_reference }}</p>
                </div>

            @elseif($registration->isRejected())
                <div class="rounded-lg border border-red-800 bg-red-900/20 p-4">
                    <div class="flex items-center gap-2">
                        <x-icon name="circle-x" class="h-5 w-5 text-accent" />
                        <span class="text-sm font-medium text-accent">Registration rejected.</span>
                    </div>
                    @if($registration->admin_notes)
                        <p class="mt-1 text-xs text-muted">Reason: {{ $registration->admin_notes }}</p>
                    @endif
                </div>

            @elseif($registration->isProofSubmitted())
                <div class="rounded-lg border border-blue-800 bg-blue-900/20 p-4">
                    <div class="flex items-center gap-2">
                        <x-icon name="clock" class="h-5 w-5 text-blue-400" />
                        <span class="text-sm font-medium text-blue-400">Proof of payment is under review.</span>
                    </div>
                    <p class="mt-1 text-xs text-muted">Reference: {{ $registration->payment_reference }}</p>
                </div>

            @elseif($registration->isPending())
                <div class="space-y-4">
                    <div class="rounded-lg border border-amber-800 bg-amber-900/20 p-4">
                        <p class="text-sm font-medium text-amber-400">Payment Required</p>
                        <p class="mt-1 text-xs text-muted">Make an EFT payment and upload your proof below.</p>
                    </div>

                    <div class="rounded-lg border border-white/10 bg-white/5 p-4 space-y-2">
                        <h3 class="text-sm font-semibold text-primary">Bank Details</h3>
                        <dl class="grid grid-cols-1 gap-1 text-sm sm:grid-cols-2">
                            <div><dt class="text-muted">Bank</dt><dd class="font-medium text-primary">{{ $bankDetails['bank_name'] ?: '—' }}</dd></div>
                            <div><dt class="text-muted">Account Name</dt><dd class="font-medium text-primary">{{ $bankDetails['bank_account_name'] ?: '—' }}</dd></div>
                            <div><dt class="text-muted">Account Number</dt><dd class="font-medium text-primary">{{ $bankDetails['bank_account_number'] ?: '—' }}</dd></div>
                            <div><dt class="text-muted">Branch Code</dt><dd class="font-medium text-primary">{{ $bankDetails['bank_branch_code'] ?: '—' }}</dd></div>
                        </dl>
                        <div class="border-t border-white/10 pt-2 mt-2"></div>
                        <dl class="grid grid-cols-1 gap-1 text-sm sm:grid-cols-2">
                            <div><dt class="text-muted">Reference</dt><dd class="font-mono font-bold portal-primary">{{ $registration->payment_reference }}</dd></div>
                            <div><dt class="text-muted">Amount</dt><dd class="font-bold text-primary">R{{ number_format($registration->amount, 2) }}</dd></div>
                        </dl>
                    </div>

                    <div class="space-y-3">
                        <h3 class="text-sm font-semibold text-primary">Upload Proof of Payment</h3>
                        <form wire:submit="uploadProof">
                            <div class="flex items-end gap-3">
                                <div class="flex-1">
                                    <input type="file" wire:model="proofOfPayment" accept=".jpg,.jpeg,.png,.pdf"
                                           class="block w-full text-sm text-muted file:mr-4 file:rounded-lg file:border-0 file:portal-bg-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary file:cursor-pointer" />
                                    <p class="mt-1 text-xs text-muted">JPG, PNG, or PDF. Max 5MB.</p>
                                    @error('proofOfPayment')
                                        <p class="mt-1 text-xs text-accent">{{ $message }}</p>
                                    @enderror
                                </div>
                                <button type="submit" class="portal-bg-primary portal-bg-primary-hover rounded-lg px-5 py-2.5 text-sm font-medium text-primary transition-colors">Upload</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endauth
    </div>

    {{-- Registered Shooters --}}
    @if($registrants->isNotEmpty() && !$match->isPreRegistration())
        <div class="rounded-xl border border-white/10 bg-app p-8 space-y-4">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-bold text-primary">Registered Shooters</h2>
                <span class="rounded-full bg-white/10 px-2.5 py-0.5 text-xs font-bold text-secondary">{{ $registrants->count() }}</span>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach($registrants as $reg)
                    <span class="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-sm text-secondary">{{ $reg->user?->name ?? 'Unknown' }}</span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Squads --}}
    @if($showSquads && $squads->isNotEmpty())
        <div class="rounded-xl border border-white/10 bg-app p-8 space-y-4">
            <h2 class="text-xl font-bold text-primary">Squads</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach($squads as $squad)
                    <div class="rounded-lg border border-white/10 bg-white/5 p-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold text-primary">{{ $squad->name }}</h3>
                            <span class="text-xs text-muted">{{ $squad->shooters->count() }} shooters</span>
                        </div>
                        @if($squad->shooters->isNotEmpty())
                            <ul class="space-y-1">
                                @foreach($squad->shooters as $shooter)
                                    <li class="flex items-center gap-2 text-sm {{ auth()->check() && $shooter->user_id === auth()->id() ? 'text-green-400 font-medium' : 'text-secondary' }}">
                                        @if(auth()->check() && $shooter->user_id === auth()->id())
                                            <span class="h-1.5 w-1.5 rounded-full bg-green-400"></span>
                                        @else
                                            <span class="h-1.5 w-1.5 rounded-full bg-white/20"></span>
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

            @auth
                @if($match->isSquaddingOpen() && $match->isSelfSquaddingEnabled() && $registration && $registration->isConfirmed())
                    <a href="{{ route('matches.squadding', $match) }}" class="inline-flex items-center rounded-lg portal-bg-primary portal-bg-primary-hover px-5 py-2.5 text-sm font-medium text-primary transition-colors">
                        Pick Your Squad
                    </a>
                @endif
            @endauth
        </div>
    @endif

    {{-- Team Selection --}}
    @if($match->isTeamEvent() && $teams->isNotEmpty() || ($match->isTeamEvent() && $registration && $registration->isConfirmed()))
        <div class="rounded-xl border border-white/10 bg-app p-8 space-y-4">
            <h2 class="text-xl font-bold text-primary">Teams</h2>
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

            @if($teams->isNotEmpty())
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach($teams as $team)
                        @php $isMyTeam = $myTeamId === $team->id; @endphp
                        <div class="rounded-lg border {{ $isMyTeam ? 'border-green-600 ring-2 ring-green-600/30' : 'border-white/10' }} bg-white/5 p-4 space-y-2 {{ $team->isFull() && !$isMyTeam ? 'opacity-50' : '' }}">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-bold text-primary">{{ $team->name }}</span>
                                <span class="text-xs text-muted">{{ $team->shooters_count }}/{{ $team->effectiveMaxSize() }}</span>
                            </div>
                            @if(!$isMyTeam && !$team->isFull() && $registration && $registration->isConfirmed() && $myShooter)
                                <button wire:click="joinTeam({{ $team->id }})" class="w-full rounded-lg portal-bg-primary portal-bg-primary-hover px-3 py-1.5 text-xs font-semibold text-primary transition-colors">
                                    Join
                                </button>
                            @elseif($isMyTeam)
                                <p class="text-center text-xs text-green-400/60">Your team</p>
                            @elseif($team->isFull())
                                <p class="text-center text-xs text-muted">Full</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @auth
                @if($registration && $registration->isConfirmed() && $myShooter && !$myTeamId)
                    <div class="border-t border-white/10 pt-4 space-y-3">
                        <h3 class="text-sm font-medium text-secondary">Create a New Team</h3>
                        <div class="flex gap-3 items-end">
                            <div class="flex-1">
                                <input type="text" wire:model="newTeamName" placeholder="e.g. Team Alpha"
                                       class="w-full rounded-lg border border-white/20 bg-white/5 px-3 py-2 text-sm text-primary placeholder-muted focus:border-white/40" />
                            </div>
                            <button wire:click="createTeam" class="portal-bg-primary portal-bg-primary-hover rounded-lg px-4 py-2 text-sm font-medium text-primary transition-colors">Create</button>
                        </div>
                    </div>
                @endif
            @endauth
        </div>
    @endif

    {{-- Scoreboard link --}}
    <div class="text-center">
        <a href="{{ route('scoreboard', $match) }}" class="inline-flex items-center rounded-lg border border-white/20 bg-white/5 px-5 py-2.5 text-sm font-medium text-primary hover:bg-white/10 transition-colors">
            View Scoreboard
        </a>
    </div>
</div>
