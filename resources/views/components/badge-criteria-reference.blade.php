@props([
    'competitionType' => 'prs',
    'awardedSlugs' => [],
])

@php
    $achievements = \App\Models\Achievement::query()
        ->where('is_active', true)
        ->where('competition_type', $competitionType)
        ->orderByRaw("CASE category WHEN 'match_special' THEN 0 WHEN 'lifetime' THEN 1 WHEN 'repeatable' THEN 2 ELSE 3 END")
        ->orderBy('sort_order')
        ->get();

    $bcfg = \App\Http\Controllers\BadgeGalleryController::BADGE_CONFIG;
    $criteriaMap = \App\Http\Controllers\BadgeGalleryController::DETAILED_CRITERIA;

    $awardedSet = collect($awardedSlugs)->flip();

    $familyAccent = $competitionType === 'royal_flush'
        ? ['text' => 'text-amber-400/80', 'border' => 'border-amber-400/20', 'pillOk' => 'border-amber-400/35 bg-amber-400/10 text-amber-200', 'pillMiss' => 'border-white/8 bg-white/3 text-zinc-500']
        : ['text' => 'text-sky-400/80',   'border' => 'border-sky-400/20',   'pillOk' => 'border-sky-400/35 bg-sky-400/10 text-sky-200',     'pillMiss' => 'border-white/8 bg-white/3 text-zinc-500'];

    $categoryLabels = [
        'match_special' => 'Signature',
        'lifetime'      => 'Earned Once',
        'repeatable'    => 'Stackable',
    ];
@endphp

@if($achievements->isNotEmpty())
    <section class="mt-6">
        <div class="mb-3 flex items-center gap-2">
            <x-badge-icon name="book-open" class="h-3.5 w-3.5 {{ $familyAccent['text'] }}" />
            <span class="text-xs font-bold uppercase tracking-wider {{ $familyAccent['text'] }}">How to Earn Every Badge</span>
        </div>
        <p class="mb-3 text-xs text-muted">Tap any badge on a shooter row above to see what they earned. Every badge available in a {{ $competitionType === 'royal_flush' ? 'Royal Flush' : 'PRS' }} match is listed below with its exact criteria.</p>

        <div class="overflow-hidden rounded-2xl border {{ $familyAccent['border'] }} bg-app/40">
            @foreach($achievements->groupBy('category') as $category => $catBadges)
                @php $label = $categoryLabels[$category] ?? ucfirst((string) $category); @endphp
                <div class="border-b border-border/50 last:border-b-0">
                    <div class="flex items-center gap-2 border-b border-border/40 bg-surface/30 px-4 py-2">
                        <span class="text-[10px] font-bold uppercase tracking-wider {{ $familyAccent['text'] }}">{{ $label }}</span>
                        <span class="rounded-full border border-border bg-surface px-1.5 text-[9px] font-semibold tabular-nums text-muted">{{ $catBadges->count() }}</span>
                    </div>
                    <div class="divide-y divide-border/40">
                        @foreach($catBadges as $badge)
                            @php
                                $cfg = $bcfg[$badge->slug] ?? [];
                                $icon = $cfg['icon'] ?? 'target';
                                $tier = $cfg['tier'] ?? 'earned';
                                $criteria = $criteriaMap[$badge->slug] ?? $badge->description;
                                $wasAwarded = $awardedSet->has($badge->slug);
                            @endphp
                            <div class="flex items-start gap-3 px-4 py-3">
                                <div class="mt-0.5 scale-[0.7] origin-top-left">
                                    <x-badge-crest :icon="$icon" :tier="$tier" :family="$competitionType" />
                                </div>
                                <div class="min-w-0 flex-1 -ml-4">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <span class="text-sm font-bold text-primary">{{ $badge->label }}</span>
                                        @if($wasAwarded)
                                            <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider {{ $familyAccent['pillOk'] }}">
                                                <x-icon name="check" class="h-2.5 w-2.5" />
                                                Awarded this match
                                            </span>
                                        @else
                                            <span class="rounded-full border px-2 py-0.5 text-[9px] font-medium uppercase tracking-wider {{ $familyAccent['pillMiss'] }}">
                                                Not earned
                                            </span>
                                        @endif
                                        @if($badge->is_repeatable)
                                            <span class="rounded-full border border-border bg-surface px-2 py-0.5 text-[9px] font-medium uppercase tracking-wider text-muted">Stackable</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-xs leading-snug text-secondary">{{ $criteria }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif