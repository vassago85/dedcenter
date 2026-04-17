<?php

use App\Models\ContactSubmission;
use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.marketing', [
    'description' => 'Put your brand in front of competitive shooters. Advertise on leaderboards, results, and scoring screens on the DeadCenter platform.',
])]
    #[Title('Advertising Opportunities — DeadCenter')]
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

    public function submitContact(): void
    {
        $this->validate();

        ContactSubmission::create([
            'name'    => $this->contactName,
            'email'   => $this->contactEmail,
            'phone'   => $this->contactPhone ?: null,
            'company' => $this->contactCompany ?: null,
            'message' => $this->contactMessage,
            'source'  => 'advertising',
        ]);

        $this->submitted = true;
    }

    public function with(): array
    {
        $individualPrice = (int) Setting::get('advertising_individual_price', 500);
        $packagePrice    = (int) Setting::get('advertising_package_price', 1500);

        return [
            'overview'         => Setting::get('sponsor_info_overview', ''),
            'visibility'       => Setting::get('sponsor_info_visibility', ''),
            'matchbookSection' => Setting::get('sponsor_info_matchbook_section', ''),
            'reach'            => Setting::get('sponsor_info_reach', ''),
            'tiers'            => Setting::get('sponsor_info_tiers', ''),
            'customPackages'   => Setting::get('sponsor_info_custom_packages', ''),
            'contact'          => Setting::get('sponsor_info_contact', ''),
            'individualPrice'  => $individualPrice,
            'packagePrice'     => $packagePrice,
            'individualPriceFmt' => 'R' . number_format($individualPrice),
            'packagePriceFmt'    => 'R' . number_format($packagePrice),
        ];
    }
}; ?>

{{-- Hero --}}
<section class="relative overflow-hidden py-20 lg:py-28" style="border-bottom: 1px solid var(--lp-border);">
    <div class="pointer-events-none absolute inset-0" style="background: radial-gradient(ellipse 60% 50% at 50% 0%, rgba(225,6,0,0.08) 0%, transparent 70%);"></div>
    <div class="relative mx-auto max-w-4xl px-6 text-center">
        <span class="inline-block rounded-full px-4 py-1.5 text-xs font-semibold uppercase tracking-wider" style="background: rgba(225,6,0,0.1); color: var(--lp-red);">Advertise</span>
        <h1 class="mt-6 text-4xl font-bold tracking-tight lg:text-5xl" style="color: var(--lp-text);">Put Your Brand Where Shooters Look</h1>
        <p class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed" style="color: var(--lp-text-soft);">
            DeadCenter powers live scoring, leaderboards, and results for competitive shooting events across South Africa.
            Advertising on DeadCenter places your brand directly in front of engaged competitors and spectators.
        </p>
        <div class="mt-8">
            <a href="#contact" class="inline-block rounded-xl px-8 py-3.5 text-lg font-bold text-white transition-all" style="background: var(--lp-red); box-shadow: 0 4px 20px rgba(225,6,0,0.25);" onmouseover="this.style.background='var(--lp-red-hover)'" onmouseout="this.style.background='var(--lp-red)'">
                Get in Touch
            </a>
        </div>
    </div>
</section>

{{-- What is DeadCenter --}}
<section class="py-16 lg:py-20" style="border-bottom: 1px solid var(--lp-border); background: var(--lp-bg-2);">
    <div class="mx-auto max-w-5xl px-6">
        <div class="text-center">
            <h2 class="text-3xl font-bold tracking-tight" style="color: var(--lp-text);">The Platform</h2>
            <p class="mx-auto mt-4 max-w-2xl leading-relaxed" style="color: var(--lp-text-soft);">
                @if(filled($overview))
                    {{ $overview }}
                @else
                    DeadCenter is a competition scoring platform used by match directors, clubs, and federations.
                    Brands advertise on platform features — leaderboards, results, and scoring screens — reaching engaged competitors at every event.
                @endif
            </p>
        </div>
        <div class="mt-12 grid gap-6 sm:grid-cols-3">
            <div class="rounded-2xl p-6 text-center" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl" style="background: rgba(225,6,0,0.08);">
                    <x-icon name="chart-column" class="h-7 w-7" style="color: var(--lp-red);" />
                </div>
                <h3 class="text-lg font-semibold" style="color: var(--lp-text);">Live Scoring</h3>
                <p class="mt-2 text-sm" style="color: var(--lp-text-soft);">Real-time scoreboards viewed by competitors, spectators, and online audiences during every match.</p>
            </div>
            <div class="rounded-2xl p-6 text-center" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-amber-600/10">
                    <x-icon name="trophy" class="h-7 w-7 text-amber-500" />
                </div>
                <h3 class="text-lg font-semibold" style="color: var(--lp-text);">Leaderboards &amp; Results</h3>
                <p class="mt-2 text-sm" style="color: var(--lp-text-soft);">Season leaderboards and match results shared online, printed, and exported as PDFs after every event.</p>
            </div>
            <div class="rounded-2xl p-6 text-center" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-blue-600/10">
                    <x-icon name="book-open" class="h-7 w-7 text-blue-500" />
                </div>
                <h3 class="text-lg font-semibold" style="color: var(--lp-text);">Scoring Screens</h3>
                <p class="mt-2 text-sm" style="color: var(--lp-text-soft);">Brand visibility on the scoring app used by every scorer during the match &mdash; seen on tablets and devices at the range.</p>
            </div>
        </div>
    </div>
</section>

{{-- Visibility Surfaces --}}
<section id="surfaces" class="py-16 lg:py-20" style="border-bottom: 1px solid var(--lp-border);">
    <div class="mx-auto max-w-5xl px-6">
        <div class="mb-12 text-center">
            <h2 class="text-3xl font-bold tracking-tight" style="color: var(--lp-text);">Where Your Brand Appears</h2>
            <p class="mx-auto mt-3 max-w-xl" style="color: var(--lp-text-soft);">
                @if(filled($visibility))
                    {{ $visibility }}
                @else
                    Every surface is seen by active, engaged shooters &mdash; not passive pageviews.
                @endif
            </p>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="flex gap-4 rounded-2xl p-6" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg" style="background: rgba(225,6,0,0.08);">
                    <x-icon name="trophy" class="h-5 w-5" style="color: var(--lp-red);" />
                </div>
                <div>
                    <h3 class="font-semibold" style="color: var(--lp-text);">Leaderboard powered by [Your Brand]</h3>
                    <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">Your brand on match leaderboards &mdash; the page competitors check most. {{ $individualPriceFmt }} per event.</p>
                </div>
            </div>
            <div class="flex gap-4 rounded-2xl p-6" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-amber-600/10">
                    <x-icon name="chart-column" class="h-5 w-5 text-amber-500" />
                </div>
                <div>
                    <h3 class="font-semibold" style="color: var(--lp-text);">Results powered by [Your Brand]</h3>
                    <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">Brand presence on results pages shared post-match and live scoreboards. {{ $individualPriceFmt }} per event.</p>
                </div>
            </div>
            <div class="flex gap-4 rounded-2xl p-6" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-blue-600/10">
                    <x-icon name="book-open" class="h-5 w-5 text-blue-500" />
                </div>
                <div>
                    <h3 class="font-semibold" style="color: var(--lp-text);">Scoring powered by [Your Brand]</h3>
                    <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">Your brand visible on the scoring screen used by every scorer during the match. {{ $individualPriceFmt }} per event.</p>
                </div>
            </div>
            <div class="flex gap-4 rounded-2xl p-6" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-green-600/10">
                    <x-icon name="file-text" class="h-5 w-5 text-green-500" />
                </div>
                <div>
                    <h3 class="font-semibold" style="color: var(--lp-text);">Full Event Visibility Package</h3>
                    <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">All three placements (Leaderboard + Results + Scoring) for one event. {{ $packagePriceFmt }} &mdash; one brand across all surfaces.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- How It Works --}}
<section class="py-16 lg:py-20" style="border-bottom: 1px solid var(--lp-border); background: var(--lp-bg-2);">
    <div class="mx-auto max-w-5xl px-6">
        <div class="grid items-center gap-10 lg:grid-cols-2">
            <div>
                <span class="inline-block rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wider" style="background: rgba(59,130,246,0.1); color: #60a5fa;">Premium Product</span>
                <h2 class="mt-4 text-3xl font-bold tracking-tight" style="color: var(--lp-text);">How It Works</h2>
                <p class="mt-4 leading-relaxed" style="color: var(--lp-text-soft);">
                    @if(filled($matchbookSection))
                        {{ $matchbookSection }}
                    @else
                        Match directors get first option on the full visibility package for their event. If they pass, placements open to any brand. One brand per feature — clean, exclusive, premium.
                    @endif
                </p>
                <ul class="mt-6 space-y-3">
                    <li class="flex items-start gap-3">
                        <x-icon name="check" class="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-400" />
                        <span class="text-sm" style="color: var(--lp-text-soft);">Match director gets first option on the full package</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <x-icon name="check" class="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-400" />
                        <span class="text-sm" style="color: var(--lp-text-soft);">If declined, individual placements open to any brand</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <x-icon name="check" class="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-400" />
                        <span class="text-sm" style="color: var(--lp-text-soft);">Full package locks all 3 placements to one brand</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <x-icon name="check" class="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-400" />
                        <span class="text-sm" style="color: var(--lp-text-soft);">Feature-based wording: "[Feature] powered by [Brand]"</span>
                    </li>
                </ul>
            </div>
            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="space-y-4">
                    <div class="flex items-center gap-3 rounded-xl p-4" style="background: rgba(225,6,0,0.05); border: 1px solid rgba(225,6,0,0.15);">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg font-bold" style="background: var(--lp-red); color: white;">1</div>
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--lp-text);">Match Director creates the match</p>
                            <p class="text-xs" style="color: var(--lp-text-muted);">Stages, targets, venue, and schedule</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 rounded-xl p-4" style="background: rgba(59,130,246,0.05); border: 1px solid rgba(59,130,246,0.15);">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg font-bold bg-blue-600 text-white">2</div>
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--lp-text);">MD takes or declines the advertising package</p>
                            <p class="text-xs" style="color: var(--lp-text-muted);">Full package or individual placements open to public</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 rounded-xl p-4" style="background: rgba(16,185,129,0.05); border: 1px solid rgba(16,185,129,0.15);">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg font-bold bg-green-600 text-white">3</div>
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--lp-text);">Brand appears across platform features</p>
                            <p class="text-xs" style="color: var(--lp-text-muted);">Leaderboard, Results, and Scoring powered by your brand</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Tiers --}}
<section class="py-16 lg:py-20" style="border-bottom: 1px solid var(--lp-border);">
    <div class="mx-auto max-w-5xl px-6">
        <div class="mb-12 text-center">
            <h2 class="text-3xl font-bold tracking-tight" style="color: var(--lp-text);">Pricing</h2>
            <p class="mx-auto mt-3 max-w-xl" style="color: var(--lp-text-soft);">Simple, transparent pricing. Pay per feature, per event.</p>
        </div>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl p-6" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-3 inline-block rounded-lg px-3 py-1 text-xs font-bold uppercase tracking-wider" style="background: rgba(148,163,184,0.1); color: #94a3b8;">Individual</div>
                <h3 class="text-lg font-bold" style="color: var(--lp-text);">Leaderboard</h3>
                <p class="mt-1 text-2xl font-bold" style="color: var(--lp-red);">{{ $individualPriceFmt }}</p>
                <p class="mt-2 text-sm leading-relaxed" style="color: var(--lp-text-soft);">"Leaderboard powered by [Your Brand]" &mdash; the page competitors check most during and after the event.</p>
            </div>
            <div class="rounded-2xl p-6" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-3 inline-block rounded-lg px-3 py-1 text-xs font-bold uppercase tracking-wider" style="background: rgba(251,191,36,0.1); color: #fbbf24;">Individual</div>
                <h3 class="text-lg font-bold" style="color: var(--lp-text);">Results</h3>
                <p class="mt-1 text-2xl font-bold" style="color: var(--lp-red);">{{ $individualPriceFmt }}</p>
                <p class="mt-2 text-sm leading-relaxed" style="color: var(--lp-text-soft);">"Results powered by [Your Brand]" &mdash; visible on scoreboard, results page, and exported PDFs.</p>
            </div>
            <div class="rounded-2xl p-6" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-3 inline-block rounded-lg px-3 py-1 text-xs font-bold uppercase tracking-wider bg-blue-600/10 text-blue-400">Individual</div>
                <h3 class="text-lg font-bold" style="color: var(--lp-text);">Scoring</h3>
                <p class="mt-1 text-2xl font-bold" style="color: var(--lp-red);">{{ $individualPriceFmt }}</p>
                <p class="mt-2 text-sm leading-relaxed" style="color: var(--lp-text-soft);">"Scoring powered by [Your Brand]" &mdash; on the scoring screen used by every scorer during the match.</p>
            </div>
            <div class="rounded-2xl p-6" style="border: 1px solid rgba(225,6,0,0.3); background: var(--lp-surface); box-shadow: 0 0 0 1px rgba(225,6,0,0.15);">
                <div class="mb-3 inline-block rounded-lg px-3 py-1 text-xs font-bold uppercase tracking-wider" style="background: rgba(225,6,0,0.1); color: var(--lp-red);">Best Value</div>
                <h3 class="text-lg font-bold" style="color: var(--lp-text);">Full Package</h3>
                <p class="mt-1 text-2xl font-bold" style="color: var(--lp-red);">{{ $packagePriceFmt }}</p>
                <p class="mt-2 text-sm leading-relaxed" style="color: var(--lp-text-soft);">All three placements for one event. One brand across Leaderboard, Results, and Scoring.</p>
            </div>
        </div>
        <p class="mt-8 text-center text-sm" style="color: var(--lp-text-muted);">
            All prices per event. Contact us for season or multi-event packages.
        </p>
    </div>
</section>

{{-- Reach / Visibility --}}
<section class="py-16 lg:py-20" style="border-bottom: 1px solid var(--lp-border); background: var(--lp-bg-2);">
    <div class="mx-auto max-w-4xl px-6 text-center">
        <h2 class="text-3xl font-bold tracking-tight" style="color: var(--lp-text);">Reach &amp; Visibility</h2>
        <p class="mx-auto mt-4 max-w-2xl leading-relaxed" style="color: var(--lp-text-soft);">
            @if(filled($reach))
                {{ $reach }}
            @else
                Your brand is seen by registered shooters, live leaderboard viewers, and audiences accessing match results and shared materials before and after each event.
            @endif
        </p>
    </div>
</section>

{{-- Contact Form --}}
<section id="contact" class="py-20 lg:py-24">
    <div class="mx-auto max-w-2xl px-6">
        <div class="text-center">
            <h2 class="text-3xl font-bold tracking-tight" style="color: var(--lp-text);">Get Your Brand in Front of Shooters</h2>
            <p class="mx-auto mt-4 max-w-xl leading-relaxed" style="color: var(--lp-text-soft);">
                @if(filled($contact))
                    {{ $contact }}
                @else
                    Tell us about your brand and we'll put together an advertising package that fits your goals.
                @endif
            </p>
        </div>

        @if($submitted)
            <div class="mt-10 rounded-2xl p-8 text-center" style="border: 1px solid rgba(16,185,129,0.3); background: rgba(16,185,129,0.05);">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full" style="background: rgba(16,185,129,0.15);">
                    <x-icon name="check" class="h-7 w-7 text-green-400" />
                </div>
                <h3 class="text-xl font-bold" style="color: var(--lp-text);">Message Sent</h3>
                <p class="mt-2" style="color: var(--lp-text-soft);">Thanks for your interest. We'll be in touch soon.</p>
            </div>
        @else
            <form wire:submit="submitContact" class="mt-10 space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium" style="color: var(--lp-text-soft);">Name <span style="color: var(--lp-red);">*</span></label>
                        <input
                            wire:model="contactName"
                            type="text"
                            class="w-full rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 outline-none transition-colors focus:ring-2"
                            style="background: var(--lp-surface); border: 1px solid var(--lp-border); focus-ring-color: var(--lp-red);"
                            placeholder="Your name"
                        />
                        @error('contactName') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium" style="color: var(--lp-text-soft);">Email <span style="color: var(--lp-red);">*</span></label>
                        <input
                            wire:model="contactEmail"
                            type="email"
                            class="w-full rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 outline-none transition-colors focus:ring-2"
                            style="background: var(--lp-surface); border: 1px solid var(--lp-border);"
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
                            class="w-full rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 outline-none transition-colors focus:ring-2"
                            style="background: var(--lp-surface); border: 1px solid var(--lp-border);"
                            placeholder="Optional"
                        />
                        @error('contactPhone') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium" style="color: var(--lp-text-soft);">Company / Brand</label>
                        <input
                            wire:model="contactCompany"
                            type="text"
                            class="w-full rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 outline-none transition-colors focus:ring-2"
                            style="background: var(--lp-surface); border: 1px solid var(--lp-border);"
                            placeholder="Optional"
                        />
                        @error('contactCompany') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium" style="color: var(--lp-text-soft);">Message <span style="color: var(--lp-red);">*</span></label>
                    <textarea
                        wire:model="contactMessage"
                        rows="4"
                        class="w-full rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 outline-none transition-colors focus:ring-2"
                        style="background: var(--lp-surface); border: 1px solid var(--lp-border); resize: vertical;"
                        placeholder="Tell us about your brand and what you're looking for..."
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
                        <span wire:loading.remove>Send Message</span>
                        <span wire:loading>Sending...</span>
                    </button>
                </div>
            </form>
        @endif
    </div>
</section>
