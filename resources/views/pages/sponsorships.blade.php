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
                    <svg class="h-7 w-7" style="color: var(--lp-red);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" /></svg>
                </div>
                <h3 class="text-lg font-semibold" style="color: var(--lp-text);">Live Scoring</h3>
                <p class="mt-2 text-sm" style="color: var(--lp-text-soft);">Real-time scoreboards viewed by competitors, spectators, and online audiences during every match.</p>
            </div>
            <div class="rounded-2xl p-6 text-center" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-amber-600/10">
                    <svg class="h-7 w-7 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0 1 16.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.003 6.003 0 0 1-3.77 1.522m0 0a6.003 6.003 0 0 1-3.77-1.522" /></svg>
                </div>
                <h3 class="text-lg font-semibold" style="color: var(--lp-text);">Leaderboards &amp; Results</h3>
                <p class="mt-2 text-sm" style="color: var(--lp-text-soft);">Season leaderboards and match results shared online, printed, and exported as PDFs after every event.</p>
            </div>
            <div class="rounded-2xl p-6 text-center" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-blue-600/10">
                    <svg class="h-7 w-7 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
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
                    <svg class="h-5 w-5" style="color: var(--lp-red);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172" /></svg>
                </div>
                <div>
                    <h3 class="font-semibold" style="color: var(--lp-text);">Leaderboard powered by [Your Brand]</h3>
                    <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">Your brand on match leaderboards &mdash; the page competitors check most. {{ $individualPriceFmt }} per event.</p>
                </div>
            </div>
            <div class="flex gap-4 rounded-2xl p-6" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-amber-600/10">
                    <svg class="h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" /></svg>
                </div>
                <div>
                    <h3 class="font-semibold" style="color: var(--lp-text);">Results powered by [Your Brand]</h3>
                    <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">Brand presence on results pages shared post-match and live scoreboards. {{ $individualPriceFmt }} per event.</p>
                </div>
            </div>
            <div class="flex gap-4 rounded-2xl p-6" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-blue-600/10">
                    <svg class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                </div>
                <div>
                    <h3 class="font-semibold" style="color: var(--lp-text);">Scoring powered by [Your Brand]</h3>
                    <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">Your brand visible on the scoring screen used by every scorer during the match. {{ $individualPriceFmt }} per event.</p>
                </div>
            </div>
            <div class="flex gap-4 rounded-2xl p-6" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-green-600/10">
                    <svg class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
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
                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        <span class="text-sm" style="color: var(--lp-text-soft);">Match director gets first option on the full package</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        <span class="text-sm" style="color: var(--lp-text-soft);">If declined, individual placements open to any brand</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        <span class="text-sm" style="color: var(--lp-text-soft);">Full package locks all 3 placements to one brand</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
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

{{-- Reach / Footprint --}}
@if(filled($reach))
<section class="py-16 lg:py-20" style="border-bottom: 1px solid var(--lp-border); background: var(--lp-bg-2);">
    <div class="mx-auto max-w-4xl px-6 text-center">
        <h2 class="text-3xl font-bold tracking-tight" style="color: var(--lp-text);">Reach &amp; Footprint</h2>
        <p class="mx-auto mt-4 max-w-2xl leading-relaxed whitespace-pre-line" style="color: var(--lp-text-soft);">{{ $reach }}</p>
    </div>
</section>
@endif

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
                    <svg class="h-7 w-7 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
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
