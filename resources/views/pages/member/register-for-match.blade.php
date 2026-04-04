<?php

use App\Models\ShootingMatch;
use App\Models\MatchRegistration;
use App\Models\MatchRegistrationCustomValue;
use App\Models\AmmoLoad;
use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Flux\Flux;

new #[Layout('components.layouts.app')]
    class extends Component {
    use WithFileUploads;

    public ShootingMatch $match;

    public int $step = 1;
    public string $contactNumber = '';
    public string $saId = '';
    public string $emergencyName = '';
    public string $emergencyNumber = '';
    public ?int $selectedRifleId = null;
    public ?int $selectedAmmoId = null;
    public ?int $selectedDivisionId = null;
    public ?int $selectedCategoryId = null;
    public array $customFieldValues = [];
    public $proofOfPayment;

    public function mount(ShootingMatch $match): void
    {
        $this->match = $match;

        $existing = MatchRegistration::where('match_id', $match->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($existing) {
            Flux::toast('You are already registered for this match.', variant: 'warning');
            $this->redirect(route('events.show', $match));
            return;
        }
    }

    public function getTitle(): string
    {
        return 'Register — ' . $this->match->name;
    }

    public function needsStep2(): bool
    {
        return $this->match->registrationFieldConfig('rifle') !== 'hidden'
            || $this->match->registrationFieldConfig('ammo') !== 'hidden'
            || $this->match->registrationFieldConfig('division') !== 'hidden'
            || $this->match->registrationFieldConfig('category') !== 'hidden'
            || $this->match->customFields()->exists();
    }

    public function totalSteps(): int
    {
        return $this->needsStep2() ? 3 : 2;
    }

    public function displayStep(): int
    {
        if (! $this->needsStep2() && $this->step === 3) {
            return 2;
        }

        return $this->step;
    }

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->validateStep1();
            $this->step = $this->needsStep2() ? 2 : 3;
        } elseif ($this->step === 2) {
            $this->validateStep2();
            $this->step = 3;
        }
    }

    public function prevStep(): void
    {
        if ($this->step === 3) {
            $this->step = $this->needsStep2() ? 2 : 1;
        } elseif ($this->step === 2) {
            $this->step = 1;
        }
    }

    private function validateStep1(): void
    {
        $rules = [
            'contactNumber' => 'required|string|max:50',
            'saId' => 'nullable|string|max:20',
        ];

        $ec = $this->match->registrationFieldConfig('emergency_contact');
        if ($ec === 'required') {
            $rules['emergencyName'] = 'required|string|max:255';
            $rules['emergencyNumber'] = 'required|string|max:50';
        } elseif ($ec === 'optional') {
            $rules['emergencyName'] = 'nullable|string|max:255';
            $rules['emergencyNumber'] = 'nullable|string|max:50';
        }

        $this->validate($rules);
    }

    private function validateStep2(): void
    {
        $rules = [];

        if ($this->match->registrationFieldConfig('rifle') === 'required') {
            $rules['selectedRifleId'] = 'required|exists:rifles,id';
        }
        if ($this->match->registrationFieldConfig('ammo') === 'required') {
            $rules['selectedAmmoId'] = 'required|exists:ammo_loads,id';
        }
        if ($this->match->registrationFieldConfig('division') === 'required') {
            $rules['selectedDivisionId'] = 'required|exists:match_divisions,id';
        }
        if ($this->match->registrationFieldConfig('category') === 'required') {
            $rules['selectedCategoryId'] = 'required|exists:match_categories,id';
        }

        foreach ($this->match->customFields()->orderBy('sort_order')->get() as $cf) {
            if ($cf->is_required && $cf->type !== 'checkbox') {
                $rules["customFieldValues.{$cf->id}"] = 'required|string|max:500';
            }
        }

        if (! empty($rules)) {
            $this->validate($rules);
        }
    }

    public function updatedSelectedRifleId($value): void
    {
        $this->selectedAmmoId = null;
    }

    public function submit(): void
    {
        $this->validateStep1();
        if ($this->needsStep2()) {
            $this->validateStep2();
        }

        if (! $this->match->isFree()) {
            $this->validate([
                'proofOfPayment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            ]);
        }

        $popPath = null;
        if ($this->proofOfPayment) {
            $popPath = $this->proofOfPayment->store(
                'proof-of-payment/' . auth()->id(),
                'public'
            );
        }

        $isFree = $this->match->isFree();
        $ref = MatchRegistration::generatePaymentReference(auth()->user());

        $registration = MatchRegistration::create([
            'match_id'                 => $this->match->id,
            'user_id'                  => auth()->id(),
            'payment_reference'        => $ref,
            'payment_status'           => $isFree ? 'confirmed' : ($popPath ? 'proof_submitted' : 'pending_payment'),
            'amount'                   => $this->match->entry_fee,
            'is_free_entry'            => $isFree,
            'contact_number'           => $this->contactNumber,
            'sa_id_number'             => $this->saId ?: null,
            'emergency_contact_name'   => $this->emergencyName ?: null,
            'emergency_contact_number' => $this->emergencyNumber ?: null,
            'rifle_id'                 => $this->selectedRifleId,
            'ammo_load_id'             => $this->selectedAmmoId,
            'division_id'              => $this->selectedDivisionId,
            'category_id'              => $this->selectedCategoryId,
            'proof_of_payment_path'    => $popPath,
        ]);

        foreach ($this->customFieldValues as $fieldId => $value) {
            if ($value !== null && $value !== '') {
                MatchRegistrationCustomValue::create([
                    'match_registration_id' => $registration->id,
                    'match_custom_field_id' => $fieldId,
                    'value'                 => $value,
                ]);
            }
        }

        if ($isFree) {
            $squad = $this->match->squads()->firstOrCreate(
                ['name' => 'Default'],
                ['sort_order' => 0]
            );
            $maxSort = $squad->shooters()->max('sort_order') ?? 0;
            \App\Models\Shooter::create([
                'squad_id'   => $squad->id,
                'name'       => auth()->user()->name,
                'user_id'    => auth()->id(),
                'sort_order' => $maxSort + 1,
            ]);
        }

        \App\Services\AchievementService::evaluateEarlyBird($this->match, auth()->id());

        Flux::toast(
            $isFree
                ? 'Registered! You are confirmed for this match.'
                : ($popPath ? 'Registered! Your proof of payment is under review.' : 'Registered! Please complete your EFT payment.'),
            variant: 'success'
        );

        $this->redirect(route('events.show', $this->match));
    }

    public function with(): array
    {
        $this->match->loadMissing(['organization', 'divisions', 'categories', 'customFields']);

        $rifles = auth()->user()->rifles()->get();
        $ammoLoads = $this->selectedRifleId
            ? AmmoLoad::where('rifle_id', $this->selectedRifleId)->get()
            : collect();

        $org = $this->match->organization;
        $bankDetails = [
            'bank_name'           => $org?->bank_name ?: Setting::get('bank_name', ''),
            'bank_account_name'   => $org?->bank_account_holder ?: Setting::get('bank_account_name', ''),
            'bank_account_number' => $org?->bank_account_number ?: Setting::get('bank_account_number', ''),
            'bank_branch_code'    => $org?->bank_branch_code ?: Setting::get('bank_branch_code', ''),
        ];

        return [
            'rifles'       => $rifles,
            'ammoLoads'    => $ammoLoads,
            'divisions'    => $this->match->divisions()->orderBy('sort_order')->get(),
            'categories'   => $this->match->categories()->orderBy('sort_order')->get(),
            'customFields' => $this->match->customFields()->orderBy('sort_order')->get(),
            'bankDetails'  => $bankDetails,
            'totalSteps'   => $this->totalSteps(),
            'displayStep'  => $this->displayStep(),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-6">
    {{-- Header --}}
    <div class="flex items-center gap-4">
        <flux:button href="{{ route('events.show', $match) }}" variant="ghost" size="sm" class="min-h-[44px] focus:ring-2 focus:ring-accent focus:outline-none">
            <svg class="mr-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
            Back
        </flux:button>
        <div>
            <flux:heading size="xl">Register for {{ $match->name }}</flux:heading>
            <p class="mt-1 text-base text-muted">
                {{ $match->date?->format('d M Y') }}
                @if($match->location) &bull; {{ $match->location }} @endif
            </p>
        </div>
    </div>

    {{-- Progress bar --}}
    <div>
        <div class="mb-2 flex items-center justify-between gap-2 text-base">
            <span class="font-medium text-secondary">Step {{ $displayStep }} of {{ $totalSteps }}</span>
            <span class="text-muted">
                @if($step === 1) Your Details
                @elseif($step === 2) Equipment & Details
                @else Confirm & Pay
                @endif
            </span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-surface-2">
            <div class="h-full rounded-full bg-accent transition-all duration-300 ease-out"
                 style="width: {{ ($displayStep / $totalSteps) * 100 }}%"></div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════ --}}
    {{-- STEP 1: Your Details                       --}}
    {{-- ═══════════════════════════════════════════ --}}
    @if($step === 1)
        <div class="rounded-xl border border-border bg-surface p-6 space-y-5">
            <h2 class="text-lg font-semibold text-primary">Your Details</h2>

            <div class="space-y-4">
                {{-- Contact number --}}
                <div>
                    <flux:input wire:model="contactNumber" label="Contact Number" placeholder="e.g. 082 123 4567" required />
                    @error('contactNumber')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- SA ID --}}
                <div>
                    <flux:input wire:model="saId" label="SA ID Number" placeholder="Optional" description="Only required by some events" />
                    @error('saId')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Emergency contact --}}
                @php $ecConfig = $match->registrationFieldConfig('emergency_contact'); @endphp
                @if($ecConfig !== 'hidden')
                    <div class="rounded-lg border border-border bg-surface-2/40 p-4 space-y-3">
                        <h3 class="text-sm font-medium text-secondary">
                            Emergency Contact
                            @if($ecConfig === 'optional')
                                <span class="text-xs text-muted">(optional)</span>
                            @endif
                        </h3>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <flux:input wire:model="emergencyName" label="Name" placeholder="Full name" :required="$ecConfig === 'required'" />
                                @error('emergencyName')
                                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <flux:input wire:model="emergencyNumber" label="Phone Number" placeholder="e.g. 082 123 4567" :required="$ecConfig === 'required'" />
                                @error('emergencyNumber')
                                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex justify-end pt-2">
                <flux:button wire:click="nextStep" variant="primary" class="min-h-[44px] !bg-accent hover:!bg-accent-hover focus:ring-2 focus:ring-accent focus:outline-none"
                             wire:loading.attr="disabled" wire:target="nextStep">
                    <span wire:loading.remove wire:target="nextStep" class="inline-flex items-center">
                        Next
                        <svg class="ml-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </span>
                    <span wire:loading wire:target="nextStep">Loading…</span>
                </flux:button>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════ --}}
    {{-- STEP 2: Equipment & Details                --}}
    {{-- ═══════════════════════════════════════════ --}}
    @if($step === 2)
        <div class="rounded-xl border border-border bg-surface p-6 space-y-5">
            <h2 class="text-lg font-semibold text-primary">Equipment & Details</h2>

            <div class="space-y-4">
                {{-- Rifle selection --}}
                @php $rifleConfig = $match->registrationFieldConfig('rifle'); @endphp
                @if($rifleConfig !== 'hidden')
                    <div>
                        <label class="mb-1 block text-sm font-medium text-secondary">
                            Rifle
                            @if($rifleConfig === 'optional') <span class="text-xs text-muted">(optional)</span> @endif
                        </label>
                        <select wire:model.live="selectedRifleId"
                                class="w-full min-h-[44px] rounded-lg border border-border bg-surface px-3 py-2.5 text-base text-primary focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent">
                            <option value="">— Select a rifle —</option>
                            @foreach($rifles as $rifle)
                                <option value="{{ $rifle->id }}">{{ $rifle->name }} ({{ $rifle->caliber }})</option>
                            @endforeach
                        </select>
                        @error('selectedRifleId')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                        @if($rifles->isEmpty())
                            <p class="mt-1.5 text-xs text-muted">
                                Don't have a rifle saved?
                                <a href="{{ route('equipment') }}" class="inline-flex min-h-[44px] items-center font-medium text-accent underline-offset-2 hover:underline focus:outline-none focus:ring-2 focus:ring-accent rounded">Add one</a>
                            </p>
                        @endif
                    </div>
                @endif

                {{-- Ammo load selection --}}
                @php $ammoConfig = $match->registrationFieldConfig('ammo'); @endphp
                @if($ammoConfig !== 'hidden')
                    <div>
                        <label class="mb-1 block text-sm font-medium text-secondary">
                            Ammo Load
                            @if($ammoConfig === 'optional') <span class="text-xs text-muted">(optional)</span> @endif
                        </label>
                        <select wire:model="selectedAmmoId"
                                class="w-full min-h-[44px] rounded-lg border border-border bg-surface px-3 py-2.5 text-base text-primary focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent disabled:opacity-50"
                                @if(!$selectedRifleId) disabled @endif>
                            <option value="">
                                @if($selectedRifleId)
                                    — Select an ammo load —
                                @else
                                    Select a rifle first
                                @endif
                            </option>
                            @foreach($ammoLoads as $ammo)
                                <option value="{{ $ammo->id }}">{{ $ammo->name }} — {{ $ammo->summary() }}</option>
                            @endforeach
                        </select>
                        @error('selectedAmmoId')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                {{-- Division --}}
                @php $divConfig = $match->registrationFieldConfig('division'); @endphp
                @if($divConfig !== 'hidden' && $divisions->isNotEmpty())
                    <div>
                        <label class="mb-1 block text-sm font-medium text-secondary">
                            Division
                            @if($divConfig === 'optional') <span class="text-xs text-muted">(optional)</span> @endif
                        </label>
                        <select wire:model="selectedDivisionId"
                                class="w-full min-h-[44px] rounded-lg border border-border bg-surface px-3 py-2.5 text-base text-primary focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent">
                            <option value="">— Select division —</option>
                            @foreach($divisions as $div)
                                <option value="{{ $div->id }}">{{ $div->name }}</option>
                            @endforeach
                        </select>
                        @error('selectedDivisionId')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                {{-- Category --}}
                @php $catConfig = $match->registrationFieldConfig('category'); @endphp
                @if($catConfig !== 'hidden' && $categories->isNotEmpty())
                    <div>
                        <label class="mb-1 block text-sm font-medium text-secondary">
                            Category
                            @if($catConfig === 'optional') <span class="text-xs text-muted">(optional)</span> @endif
                        </label>
                        <select wire:model="selectedCategoryId"
                                class="w-full min-h-[44px] rounded-lg border border-border bg-surface px-3 py-2.5 text-base text-primary focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent">
                            <option value="">— Select category —</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        @error('selectedCategoryId')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                {{-- Custom fields --}}
                @if($customFields->isNotEmpty())
                    <div class="border-t border-border pt-4 space-y-4">
                        <h3 class="text-sm font-medium text-muted uppercase tracking-wide">Additional Information</h3>
                        @foreach($customFields as $cf)
                            <div>
                                <label class="mb-1 block text-sm font-medium text-secondary">
                                    {{ $cf->label }}
                                    @if(!$cf->is_required) <span class="text-xs text-muted">(optional)</span> @endif
                                </label>

                                @if($cf->type === 'select')
                                    <select wire:model="customFieldValues.{{ $cf->id }}"
                                            class="w-full min-h-[44px] rounded-lg border border-border bg-surface px-3 py-2.5 text-base text-primary focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent">
                                        <option value="">— Select —</option>
                                        @foreach($cf->options ?? [] as $opt)
                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                @elseif($cf->type === 'checkbox')
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" wire:model="customFieldValues.{{ $cf->id }}" value="1"
                                               class="h-4 w-4 rounded border-border bg-surface text-accent focus:outline-none focus:ring-2 focus:ring-accent" />
                                        <span class="text-base text-secondary">Yes</span>
                                    </label>
                                @else
                                    <flux:input wire:model="customFieldValues.{{ $cf->id }}" placeholder="{{ $cf->label }}" />
                                @endif

                                @error("customFieldValues.{$cf->id}")
                                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                <flux:button wire:click="prevStep" variant="ghost" class="min-h-[44px] focus:ring-2 focus:ring-accent focus:outline-none"
                             wire:loading.attr="disabled" wire:target="prevStep">
                    <span wire:loading.remove wire:target="prevStep" class="inline-flex items-center">
                        <svg class="mr-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                        Back
                    </span>
                    <span wire:loading wire:target="prevStep">Loading…</span>
                </flux:button>
                <flux:button wire:click="nextStep" variant="primary" class="min-h-[44px] !bg-accent hover:!bg-accent-hover focus:ring-2 focus:ring-accent focus:outline-none"
                             wire:loading.attr="disabled" wire:target="nextStep">
                    <span wire:loading.remove wire:target="nextStep" class="inline-flex items-center">
                        Next
                        <svg class="ml-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </span>
                    <span wire:loading wire:target="nextStep">Loading…</span>
                </flux:button>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════ --}}
    {{-- STEP 3: Confirm & Pay                      --}}
    {{-- ═══════════════════════════════════════════ --}}
    @if($step === 3)
        <div class="space-y-5">
            {{-- Summary card --}}
            <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
                <h2 class="text-lg font-semibold text-primary">Registration Summary</h2>

                <dl class="divide-y divide-border text-base">
                    {{-- Personal details --}}
                    <div class="flex justify-between py-2.5">
                        <dt class="text-muted">Name</dt>
                        <dd class="font-medium text-primary">{{ auth()->user()->name }}</dd>
                    </div>
                    <div class="flex justify-between py-2.5">
                        <dt class="text-muted">Contact Number</dt>
                        <dd class="font-medium text-primary">{{ $contactNumber }}</dd>
                    </div>
                    @if($saId)
                        <div class="flex justify-between py-2.5">
                            <dt class="text-muted">SA ID</dt>
                            <dd class="font-medium text-primary">{{ $saId }}</dd>
                        </div>
                    @endif
                    @if($emergencyName)
                        <div class="flex justify-between py-2.5">
                            <dt class="text-muted">Emergency Contact</dt>
                            <dd class="text-right font-medium text-primary">{{ $emergencyName }} &bull; {{ $emergencyNumber }}</dd>
                        </div>
                    @endif

                    {{-- Equipment --}}
                    @if($selectedRifleId)
                        @php $rifle = $rifles->firstWhere('id', $selectedRifleId); @endphp
                        @if($rifle)
                            <div class="flex justify-between py-2.5">
                                <dt class="text-muted">Rifle</dt>
                                <dd class="font-medium text-primary">{{ $rifle->name }} ({{ $rifle->caliber }})</dd>
                            </div>
                        @endif
                    @endif
                    @if($selectedAmmoId)
                        @php $ammo = $ammoLoads->firstWhere('id', $selectedAmmoId); @endphp
                        @if($ammo)
                            <div class="flex justify-between py-2.5">
                                <dt class="text-muted">Ammo</dt>
                                <dd class="font-medium text-primary">{{ $ammo->name }}</dd>
                            </div>
                        @endif
                    @endif
                    @if($selectedDivisionId)
                        @php $div = $divisions->firstWhere('id', $selectedDivisionId); @endphp
                        @if($div)
                            <div class="flex justify-between py-2.5">
                                <dt class="text-muted">Division</dt>
                                <dd class="font-medium text-primary">{{ $div->name }}</dd>
                            </div>
                        @endif
                    @endif
                    @if($selectedCategoryId)
                        @php $cat = $categories->firstWhere('id', $selectedCategoryId); @endphp
                        @if($cat)
                            <div class="flex justify-between py-2.5">
                                <dt class="text-muted">Category</dt>
                                <dd class="font-medium text-primary">{{ $cat->name }}</dd>
                            </div>
                        @endif
                    @endif

                    {{-- Custom fields --}}
                    @foreach($customFields as $cf)
                        @if(!empty($customFieldValues[$cf->id] ?? null))
                            <div class="flex justify-between py-2.5">
                                <dt class="text-muted">{{ $cf->label }}</dt>
                                <dd class="font-medium text-primary">
                                    @if($cf->type === 'checkbox')
                                        {{ $customFieldValues[$cf->id] ? 'Yes' : 'No' }}
                                    @else
                                        {{ $customFieldValues[$cf->id] }}
                                    @endif
                                </dd>
                            </div>
                        @endif
                    @endforeach

                    {{-- Entry fee --}}
                    <div class="flex items-center justify-between py-3">
                        <dt class="font-semibold text-secondary">Entry Fee</dt>
                        <dd class="text-xl font-bold {{ $match->isFree() ? 'text-green-400' : 'text-primary' }}">
                            {{ $match->isFree() ? 'Free Entry' : 'R' . number_format($match->entry_fee, 2) }}
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Payment section (paid matches only) --}}
            @if(!$match->isFree())
                <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
                    <h2 class="text-lg font-semibold text-primary">Payment</h2>

                    <div class="rounded-lg border border-amber-800/50 bg-amber-900/20 p-4">
                        <p class="text-base font-medium text-amber-400">EFT Payment Required</p>
                        <p class="mt-1 text-base text-muted">Make your payment using the bank details below. You can upload proof now or later from the match page.</p>
                    </div>

                    {{-- Bank details --}}
                    <div class="rounded-lg border border-border bg-surface-2/50 p-4 space-y-2">
                        <h3 class="text-sm font-semibold text-primary">Bank Details</h3>
                        <dl class="grid grid-cols-1 gap-2 text-base sm:grid-cols-2">
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

                        <div>
                            <dt class="text-muted text-sm">Amount</dt>
                            <dd class="text-lg font-bold text-primary">R{{ number_format($match->entry_fee, 2) }}</dd>
                        </div>
                    </div>

                    {{-- Proof of payment upload --}}
                    <div class="space-y-2">
                        <label for="proof-of-payment-upload" class="block text-sm font-semibold text-primary">Upload Proof of Payment <span class="text-xs font-normal text-muted">(optional — can do later)</span></label>
                        <input id="proof-of-payment-upload" type="file" wire:model="proofOfPayment" accept=".jpg,.jpeg,.png,.pdf"
                               class="block w-full text-base text-muted file:mr-4 file:min-h-[44px] file:rounded-lg file:border-0 file:bg-accent file:px-4 file:py-2 file:text-base file:font-medium file:text-primary hover:file:bg-accent-hover file:cursor-pointer focus:outline-none focus:ring-2 focus:ring-accent" />
                        <p class="text-sm text-muted">JPG, PNG, or PDF. Max 5 MB.</p>
                        @error('proofOfPayment')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endif

            {{-- Action buttons --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:button wire:click="prevStep" variant="ghost" class="min-h-[44px] focus:ring-2 focus:ring-accent focus:outline-none"
                             wire:loading.attr="disabled" wire:target="prevStep">
                    <span wire:loading.remove wire:target="prevStep" class="inline-flex items-center">
                        <svg class="mr-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                        Back
                    </span>
                    <span wire:loading wire:target="prevStep">Loading…</span>
                </flux:button>
                <flux:button wire:click="submit" variant="primary"
                             class="min-h-[44px] !bg-green-600 hover:!bg-green-700 focus:ring-2 focus:ring-accent focus:outline-none"
                             wire:confirm="Confirm your registration for {{ $match->name }}?"
                             wire:loading.attr="disabled" wire:target="submit">
                    <span wire:loading.remove wire:target="submit" class="inline-flex items-center">
                        <svg class="mr-1.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        Confirm Registration
                    </span>
                    <span wire:loading wire:target="submit">Submitting…</span>
                </flux:button>
            </div>
        </div>
    @endif
</div>
