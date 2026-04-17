<?php

use App\Models\ContactSubmission;
use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.marketing', [
    'description' => 'Promote your brand to South Africa’s active shooting community through platform, club, and match sponsorship opportunities on DeadCenter.',
])]
    #[Title('Advertise with DeadCenter')]
    class extends Component {
        #[Validate('required|string|max:100')]
        public string $contactName = '';

        #[Validate('required|email|max:150')]
        public string $contactEmail = '';

        #[Validate('nullable|string|max:30')]
        public string $contactPhone = '';

        #[Validate('nullable|string|max:100')]
        public string $contactCompany = '';

        #[Validate('required|string|min:10|max:2000')]
        public string $contactMessage = '';

        public bool $submitted = false;

        public function mount(): void
        {
            $this->contactMessage = "I'm interested in promoting my brand on DeadCenter. I'd like to learn more about sponsorship options that fit my audience and goals.";
        }

        public function submitInterest(): void
        {
            $this->validate();

            ContactSubmission::create([
                'name' => $this->contactName,
                'email' => $this->contactEmail,
                'phone' => $this->contactPhone ?: null,
                'company' => $this->contactCompany ?: null,
                'message' => $this->contactMessage,
                'source' => 'advertise',
            ]);

            $this->submitted = true;
        }

        public function with(): array
        {
            return [
                'advertisingEnabled' => (bool) Setting::get('advertising_enabled', false),
            ];
        }
    }; ?>

{{-- Hero --}}
<section class="relative overflow-hidden py-20 lg:py-28" style="border-bottom: 1px solid var(--lp-border);">
    <div class="pointer-events-none absolute inset-0" style="background: radial-gradient(ellipse 60% 50% at 50% 0%, rgba(225,6,0,0.08) 0%, transparent 70%);"></div>
    <div class="relative mx-auto max-w-4xl px-6 text-center">
        <span class="inline-block rounded-full px-4 py-1.5 text-xs font-semibold uppercase tracking-wider" style="background: rgba(225,6,0,0.1); color: var(--lp-red);">For brands</span>
        <h1 class="mt-6 text-4xl font-bold tracking-tight lg:text-5xl" style="color: var(--lp-text);">Advertise with DeadCenter</h1>
        <p class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed" style="color: var(--lp-text-soft);">
            Reach South Africa’s active shooting community through club portals, match visibility, and platform-wide sponsorship opportunities.
        </p>
        <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row sm:flex-wrap">
            <a href="#interest" class="inline-flex items-center justify-center rounded-xl px-8 py-3.5 text-base font-bold text-white transition-all" style="background: var(--lp-red); box-shadow: 0 4px 20px rgba(225,6,0,0.25);" onmouseover="this.style.background='var(--lp-red-hover)'" onmouseout="this.style.background='var(--lp-red)'">
                Express Interest
            </a>
            @if($advertisingEnabled)
                <a href="{{ route('sponsor-marketplace') }}" class="inline-flex items-center justify-center rounded-xl border px-8 py-3.5 text-base font-semibold transition-colors hover:bg-white/5" style="border-color: var(--lp-border); color: var(--lp-text-soft);">
                    View Sponsor Opportunities
                </a>
            @endif
        </div>
    </div>
</section>

{{-- Why DeadCenter --}}
<section class="py-16 lg:py-20" style="border-bottom: 1px solid var(--lp-border); background: var(--lp-bg-2);">
    <div class="mx-auto max-w-5xl px-6">
        <div class="mx-auto max-w-3xl text-center">
            <h2 class="text-3xl font-bold tracking-tight" style="color: var(--lp-text);">Why DeadCenter</h2>
            <p class="mt-4 text-base leading-relaxed" style="color: var(--lp-text-soft);">
                DeadCenter sits at the centre of competitive shooting in South Africa — match discovery, registration, live scoring, results, and season standings. Your brand appears where participants and fans already pay attention: not generic banner inventory, but context-rich moments around real events.
            </p>
            <p class="mt-4 text-base leading-relaxed" style="color: var(--lp-text-soft);">
                That makes DeadCenter a strong fit for optics, ammunition, accessories, apparel, training, outdoor, hunting, and allied brands that want a credible, niche audience — serious shooters who register, compete, and come back event after event.
            </p>
        </div>
    </div>
</section>

{{-- Where your brand can appear --}}
<section class="py-16 lg:py-20" style="border-bottom: 1px solid var(--lp-border);">
    <div class="mx-auto max-w-5xl px-6">
        <div class="text-center">
            <h2 class="text-3xl font-bold tracking-tight" style="color: var(--lp-text);">Where your brand can appear</h2>
            <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                We work with you to match the right surfaces to your campaign — from nationwide visibility to club- or event-focused exposure.
            </p>
        </div>
        <div class="mt-12 grid gap-6 md:grid-cols-3">
            <div class="rounded-2xl p-6 text-left" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(225,6,0,0.08);">
                    <x-icon name="house" class="h-6 w-6" style="color: var(--lp-red);" aria-hidden="true" />
                </div>
                <h3 class="text-lg font-semibold" style="color: var(--lp-text);">Platform sponsorships</h3>
                <p class="mt-2 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                    Featured presence on DeadCenter’s public site — for example hero or strip placements on the main marketing experience — putting your brand alongside the story shooters see when they discover events and results.
                </p>
            </div>
            <div class="rounded-2xl p-6 text-left" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600/10">
                    <x-icon name="building-2" class="h-6 w-6 text-blue-400" aria-hidden="true" />
                </div>
                <h3 class="text-lg font-semibold" style="color: var(--lp-text);">Club portal sponsorships</h3>
                <p class="mt-2 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                    Partner with participating clubs and organizations on their branded public portals — home, match listings, leaderboards, and event pages — so your message travels with the communities shooters already follow.
                </p>
            </div>
            <div class="rounded-2xl p-6 text-left" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500/10">
                    <x-icon name="trophy" class="h-6 w-6 text-amber-400" aria-hidden="true" />
                </div>
                <h3 class="text-lg font-semibold" style="color: var(--lp-text);">Match sponsorship opportunities</h3>
                <p class="mt-2 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                    Visibility around specific events — leaderboards, results, scoring experiences, and related match content — ideal when you want to align tightly with match day and the competitors in the field.
                </p>
            </div>
        </div>
    </div>
</section>

{{-- Flexible options --}}
<section class="py-16 lg:py-20" style="border-bottom: 1px solid var(--lp-border); background: var(--lp-bg-2);">
    <div class="mx-auto max-w-5xl px-6">
        <div class="mx-auto max-w-3xl text-center">
            <h2 class="text-3xl font-bold tracking-tight" style="color: var(--lp-text);">Flexible sponsorship options</h2>
            <p class="mt-4 text-base leading-relaxed" style="color: var(--lp-text-soft);">
                Every partnership is shaped around your goals. Depending on timing and availability, opportunities may include flagship monthly placements, campaign-based visibility, club-focused packages, or event- and match-related integrations. We’ll recommend a mix that fits your brand — without locking you into a one-size-fits-all catalogue.
            </p>
        </div>
        <ul class="mx-auto mt-10 max-w-2xl space-y-3">
            <li class="flex items-start gap-3 text-sm" style="color: var(--lp-text-soft);">
                <x-icon name="check" class="mt-0.5 h-5 w-5 shrink-0 text-green-400" aria-hidden="true" />
                <span>Monthly or seasonal featured placements on high-traffic public surfaces</span>
            </li>
            <li class="flex items-start gap-3 text-sm" style="color: var(--lp-text-soft);">
                <x-icon name="check" class="mt-0.5 h-5 w-5 shrink-0 text-green-400" aria-hidden="true" />
                <span>Campaign-led bursts tied to product launches or key range dates</span>
            </li>
            <li class="flex items-start gap-3 text-sm" style="color: var(--lp-text-soft);">
                <x-icon name="check" class="mt-0.5 h-5 w-5 shrink-0 text-green-400" aria-hidden="true" />
                <span>Club- and federation-aligned packages for deeper community presence</span>
            </li>
            <li class="flex items-start gap-3 text-sm" style="color: var(--lp-text-soft);">
                <x-icon name="check" class="mt-0.5 h-5 w-5 shrink-0 text-green-400" aria-hidden="true" />
                <span>Event and match adjacency — where shooters are most engaged</span>
            </li>
        </ul>
        @if($advertisingEnabled)
            <p class="mt-10 text-center text-sm" style="color: var(--lp-text-muted);">
                See current open placements on the
                <a href="{{ route('sponsor-marketplace') }}" class="font-medium underline decoration-dotted underline-offset-2 hover:!text-white" style="color: var(--lp-red);">sponsor marketplace</a>.
            </p>
        @endif
    </div>
</section>

{{-- Trust --}}
<section class="py-16 lg:py-20" style="border-bottom: 1px solid var(--lp-border);">
    <div class="mx-auto max-w-3xl px-6 text-center">
        <h2 class="text-3xl font-bold tracking-tight" style="color: var(--lp-text);">Built for quality, not clutter</h2>
        <p class="mt-4 text-base leading-relaxed" style="color: var(--lp-text-soft);">
            We keep sponsorship inventory deliberately limited so placements stay valuable for brands and relevant for shooters. Category fit matters: we prioritise partners that make sense for precision rifle, ELR, and related disciplines. That curated approach protects the experience for everyone on the platform.
        </p>
    </div>
</section>

{{-- Final CTA + form --}}
<section id="interest" class="py-20 lg:py-24" style="background: var(--lp-bg-2); border-bottom: 1px solid var(--lp-border);">
    <div class="mx-auto max-w-2xl px-6">
        <div class="text-center">
            <h2 class="text-3xl font-bold tracking-tight" style="color: var(--lp-text);">Interested in promoting your brand on DeadCenter?</h2>
            <p class="mx-auto mt-4 max-w-xl leading-relaxed" style="color: var(--lp-text-soft);">
                Tell us a bit about your brand and what kind of audience or placement you’re interested in, and we’ll get in touch.
            </p>
        </div>

        @if($submitted)
            <div class="mt-10 rounded-2xl p-8 text-center" style="border: 1px solid rgba(16,185,129,0.3); background: rgba(16,185,129,0.05);">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full" style="background: rgba(16,185,129,0.15);">
                    <x-icon name="check" class="h-7 w-7 text-green-400" aria-hidden="true" />
                </div>
                <h3 class="text-xl font-bold" style="color: var(--lp-text);">Thank you</h3>
                <p class="mt-2" style="color: var(--lp-text-soft);">We’ve received your message and will respond as soon as we can.</p>
            </div>
        @else
            <form wire:submit="submitInterest" class="mt-10 space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium" style="color: var(--lp-text-soft);">Name <span style="color: var(--lp-red);">*</span></label>
                        <input
                            wire:model="contactName"
                            type="text"
                            class="w-full rounded-xl px-4 py-3 text-sm outline-none transition-colors focus:ring-2"
                            style="background: var(--lp-surface); border: 1px solid var(--lp-border); color: var(--lp-text); --tw-ring-color: var(--lp-red);"
                            placeholder="Your name"
                        />
                        @error('contactName') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium" style="color: var(--lp-text-soft);">Email <span style="color: var(--lp-red);">*</span></label>
                        <input
                            wire:model="contactEmail"
                            type="email"
                            class="w-full rounded-xl px-4 py-3 text-sm outline-none transition-colors focus:ring-2"
                            style="background: var(--lp-surface); border: 1px solid var(--lp-border); color: var(--lp-text); --tw-ring-color: var(--lp-red);"
                            placeholder="you@company.co.za"
                        />
                        @error('contactEmail') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium" style="color: var(--lp-text-soft);">Phone</label>
                        <input
                            wire:model="contactPhone"
                            type="tel"
                            class="w-full rounded-xl px-4 py-3 text-sm outline-none transition-colors focus:ring-2"
                            style="background: var(--lp-surface); border: 1px solid var(--lp-border); color: var(--lp-text); --tw-ring-color: var(--lp-red);"
                            placeholder="Optional"
                        />
                        @error('contactPhone') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium" style="color: var(--lp-text-soft);">Company / brand</label>
                        <input
                            wire:model="contactCompany"
                            type="text"
                            class="w-full rounded-xl px-4 py-3 text-sm outline-none transition-colors focus:ring-2"
                            style="background: var(--lp-surface); border: 1px solid var(--lp-border); color: var(--lp-text); --tw-ring-color: var(--lp-red);"
                            placeholder="Optional"
                        />
                        @error('contactCompany') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium" style="color: var(--lp-text-soft);">Message <span style="color: var(--lp-red);">*</span></label>
                    <textarea
                        wire:model="contactMessage"
                        rows="5"
                        class="w-full rounded-xl px-4 py-3 text-sm outline-none transition-colors focus:ring-2"
                        style="background: var(--lp-surface); border: 1px solid var(--lp-border); color: var(--lp-text); resize: vertical; --tw-ring-color: var(--lp-red);"
                        placeholder="Tell us about your brand and what you’re looking for…"
                    ></textarea>
                    @error('contactMessage') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
                <div class="pt-2 text-center">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 rounded-xl px-8 py-3.5 text-lg font-bold text-white transition-all disabled:opacity-60"
                        style="background: var(--lp-red); box-shadow: 0 4px 20px rgba(225,6,0,0.25);"
                        onmouseover="this.style.background='var(--lp-red-hover)'"
                        onmouseout="this.style.background='var(--lp-red)'"
                    >
                        <span wire:loading.remove>Express Interest</span>
                        <span wire:loading>Sending…</span>
                    </button>
                </div>
            </form>
        @endif

        @if($advertisingEnabled)
            <p class="mt-10 text-center text-xs" style="color: var(--lp-text-muted);">
                Looking for per-event placements with listed availability?
                <a href="{{ route('sponsorships') }}" class="underline underline-offset-2 hover:!text-white">View packages &amp; pricing</a>
            </p>
        @endif
    </div>
</section>
