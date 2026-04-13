<?php

use App\Models\ContactSubmission;
use App\Models\ShootingMatch;
use App\Models\SponsorAssignment;
use App\Enums\MatchStatus;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.marketing', [
    'description' => 'Browse available advertising placements for upcoming shooting matches. Connect your brand with competitive shooters on the DeadCenter platform.',
])]
    #[Title('Advertise on DeadCenter')]
    class extends Component {

    #[Validate('required|string|max:100')]
    public string $contactName = '';

    #[Validate('required|email|max:150')]
    public string $contactEmail = '';

    #[Validate('nullable|string|max:100')]
    public string $contactCompany = '';

    #[Validate('required|string|min:10|max:2000')]
    public string $contactMessage = '';

    public ?int $selectedAssignment = null;
    public bool $showForm = false;
    public bool $submitted = false;

    public function openInterest(int $assignmentId, string $matchName, string $placementLabel): void
    {
        $this->selectedAssignment = $assignmentId;
        $this->contactMessage = "I'm interested in the \"{$placementLabel}\" placement for {$matchName}.";
        $this->showForm = true;
        $this->submitted = false;
    }

    public function submitInterest(): void
    {
        $this->validate();

        ContactSubmission::create([
            'name'    => $this->contactName,
            'email'   => $this->contactEmail,
            'company' => $this->contactCompany ?: null,
            'message' => $this->contactMessage,
            'source'  => 'marketplace',
        ]);

        $this->submitted = true;
        $this->showForm = false;
        $this->reset('contactName', 'contactEmail', 'contactCompany', 'contactMessage', 'selectedAssignment');
    }

    public function closeForm(): void
    {
        $this->showForm = false;
    }

    public function with(): array
    {
        $upcomingMatchIds = ShootingMatch::whereIn('status', [
                MatchStatus::PreRegistration,
                MatchStatus::RegistrationOpen,
                MatchStatus::RegistrationClosed,
                MatchStatus::SquaddingOpen,
                MatchStatus::Active,
            ])
            ->pluck('id');

        $assignments = SponsorAssignment::marketplace()
            ->whereIn('scope_id', $upcomingMatchIds)
            ->get();

        $matchIds = $assignments->pluck('scope_id')->unique();

        $matches = ShootingMatch::whereIn('id', $matchIds)
            ->orderBy('date')
            ->get()
            ->keyBy('id');

        $grouped = $assignments->groupBy('scope_id');

        return compact('grouped', 'matches');
    }
}; ?>

{{-- Hero --}}
<section class="relative overflow-hidden py-20 lg:py-28" style="border-bottom: 1px solid var(--lp-border);">
    <div class="pointer-events-none absolute inset-0" style="background: radial-gradient(ellipse 60% 50% at 50% 0%, rgba(225,6,0,0.08) 0%, transparent 70%);"></div>
    <div class="relative mx-auto max-w-4xl px-6 text-center">
        <span class="inline-block rounded-full px-4 py-1.5 text-xs font-semibold uppercase tracking-wider" style="background: rgba(225,6,0,0.1); color: var(--lp-red);">Advertising</span>
        <h1 class="mt-6 text-4xl font-bold tracking-tight lg:text-5xl" style="color: var(--lp-text);">Advertise on DeadCenter</h1>
        <p class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed" style="color: var(--lp-text-soft);">
            Put your brand in front of competitive shooters. Browse available advertising placements for upcoming matches and connect directly with the DeadCenter team.
        </p>
    </div>
</section>

{{-- How it works --}}
<section class="py-16 lg:py-20" style="border-bottom: 1px solid var(--lp-border); background: var(--lp-bg-2);">
    <div class="mx-auto max-w-5xl px-6">
        <h2 class="text-center text-3xl font-bold tracking-tight" style="color: var(--lp-text);">How It Works</h2>
        <div class="mt-12 grid gap-8 sm:grid-cols-3">
            <div class="text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl text-xl font-black text-white" style="background: var(--lp-red);">1</div>
                <h3 class="text-lg font-semibold" style="color: var(--lp-text);">Browse</h3>
                <p class="mt-2 text-sm leading-relaxed" style="color: var(--lp-text-soft);">See which upcoming events have advertising placements available — leaderboards, results, and scoring screens.</p>
            </div>
            <div class="text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl text-xl font-black text-white" style="background: var(--lp-red);">2</div>
                <h3 class="text-lg font-semibold" style="color: var(--lp-text);">Express Interest</h3>
                <p class="mt-2 text-sm leading-relaxed" style="color: var(--lp-text-soft);">Click "I'm Interested" on any placement. Fill in your details and we'll receive your enquiry immediately.</p>
            </div>
            <div class="text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl text-xl font-black text-white" style="background: var(--lp-red);">3</div>
                <h3 class="text-lg font-semibold" style="color: var(--lp-text);">Connect</h3>
                <p class="mt-2 text-sm leading-relaxed" style="color: var(--lp-text-soft);">We'll get back to you with pricing and logistics. Your brand goes live before, during, and after the match.</p>
            </div>
        </div>
    </div>
</section>

{{-- Available placements --}}
<section class="py-16 lg:py-20">
    <div class="mx-auto max-w-5xl px-6">
        <h2 class="text-center text-3xl font-bold tracking-tight" style="color: var(--lp-text);">Available Placements</h2>
        <p class="mx-auto mt-3 max-w-xl text-center text-sm leading-relaxed" style="color: var(--lp-text-soft);">These events currently have open advertising placements. Grab one before they're taken.</p>

        @if($grouped->isEmpty())
            <div class="mt-12 rounded-2xl p-10 text-center" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <p class="text-lg" style="color: var(--lp-text-muted);">No advertising placements are available right now. Check back soon or <a href="{{ route('advertise') }}" class="underline" style="color: var(--lp-red);">tell us you’re interested</a>.</p>
            </div>
        @else
            <div class="mt-12 space-y-8">
                @foreach($grouped as $matchId => $assignments)
                    @php $match = $matches[$matchId] ?? null; @endphp
                    @if($match)
                        <div class="rounded-2xl p-6" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                            <div class="mb-4">
                                <h3 class="text-xl font-bold" style="color: var(--lp-text);">{{ $match->name }}</h3>
                                <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm" style="color: var(--lp-text-muted);">
                                    @if($match->date)
                                        <span>{{ $match->date->format('d M Y') }}</span>
                                    @endif
                                    @if($match->location)
                                        <span>{{ $match->location }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach($assignments as $assignment)
                                    <div class="flex items-center justify-between rounded-xl p-4" style="border: 1px solid var(--lp-border); background: var(--lp-bg);">
                                        <div>
                                            <span class="text-sm font-medium" style="color: var(--lp-text);">{{ $assignment->placement_key->label() }}</span>
                                            @if($assignment->label_override)
                                                <span class="block text-xs" style="color: var(--lp-text-muted);">{{ $assignment->label_override }}</span>
                                            @endif
                                        </div>
                                        <button
                                            wire:click="openInterest({{ $assignment->id }}, '{{ addslashes($match->name) }}', '{{ addslashes($assignment->placement_key->label()) }}')"
                                            class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-semibold text-white transition-colors"
                                            style="background: var(--lp-red);"
                                            onmouseover="this.style.background='var(--lp-red-hover)'"
                                            onmouseout="this.style.background='var(--lp-red)'">
                                            I'm Interested
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</section>

{{-- Interest form modal --}}
@if($showForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);">
        <div class="w-full max-w-lg rounded-2xl p-6" style="background: var(--lp-surface); border: 1px solid var(--lp-border);" @click.outside="$wire.closeForm()">
            <h3 class="text-xl font-bold" style="color: var(--lp-text);">Express Interest</h3>
            <p class="mt-1 text-sm" style="color: var(--lp-text-muted);">Fill in your details and we'll be in touch.</p>

            <form wire:submit="submitInterest" class="mt-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium" style="color: var(--lp-text-soft);">Name *</label>
                    <input wire:model="contactName" type="text" required
                           class="mt-1 w-full rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2"
                           style="background: var(--lp-bg); border: 1px solid var(--lp-border); color: var(--lp-text); --tw-ring-color: var(--lp-red);" />
                    @error('contactName') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium" style="color: var(--lp-text-soft);">Email *</label>
                    <input wire:model="contactEmail" type="email" required
                           class="mt-1 w-full rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2"
                           style="background: var(--lp-bg); border: 1px solid var(--lp-border); color: var(--lp-text); --tw-ring-color: var(--lp-red);" />
                    @error('contactEmail') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium" style="color: var(--lp-text-soft);">Company</label>
                    <input wire:model="contactCompany" type="text"
                           class="mt-1 w-full rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2"
                           style="background: var(--lp-bg); border: 1px solid var(--lp-border); color: var(--lp-text); --tw-ring-color: var(--lp-red);" />
                    @error('contactCompany') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium" style="color: var(--lp-text-soft);">Message *</label>
                    <textarea wire:model="contactMessage" rows="4" required
                              class="mt-1 w-full rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2"
                              style="background: var(--lp-bg); border: 1px solid var(--lp-border); color: var(--lp-text); --tw-ring-color: var(--lp-red);"></textarea>
                    @error('contactMessage') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
                </div>
                <div class="flex items-center gap-3 pt-2">
                    <button type="submit"
                            class="rounded-lg px-6 py-2.5 text-sm font-semibold text-white transition-colors"
                            style="background: var(--lp-red);"
                            onmouseover="this.style.background='var(--lp-red-hover)'"
                            onmouseout="this.style.background='var(--lp-red)'">
                        Send Enquiry
                    </button>
                    <button type="button" wire:click="closeForm"
                            class="rounded-lg px-6 py-2.5 text-sm font-medium transition-colors"
                            style="color: var(--lp-text-muted);">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

{{-- Success toast --}}
@if($submitted)
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
         x-transition class="fixed bottom-6 right-6 z-50 rounded-xl px-6 py-4 text-sm font-medium text-white shadow-lg"
         style="background: var(--lp-red);">
        Thanks! We'll be in touch soon.
    </div>
@endif
