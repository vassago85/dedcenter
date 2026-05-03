@props([
    'userId',
    'matchId' => null,
    'competitionType' => null,
    'compact' => false,
    'grouped' => true,
    // `trophy` is the dashboard hero strip mode: square crests laid out in
    // a horizontal flex, sized like the badge-gallery medallions (so the
    // dashboard reads as a personal trophy cabinet rather than the old
    // pill-card grid that crammed icon+title+description into 200px
    // rectangles and looked, in the user's words, like a "poor effort").
    // `compact` is preserved for the per-row scoreboard avatars-of-badges
    // affordance — different consumer, different size budget.
    'trophy' => false,
])

@php
    use App\Http\Controllers\BadgeGalleryController;

    $badgeConfig = BadgeGalleryController::BADGE_CONFIG;
    $tierOrder = ['featured' => 0, 'elite' => 1, 'milestone' => 2, 'earned' => 3];

    $query = \App\Models\UserAchievement::where('user_id', $userId)
        ->with('achievement');

    if ($matchId) {
        $query->where('match_id', $matchId);
    }

    if ($competitionType) {
        $query->whereHas('achievement', fn ($q) => $q->where('competition_type', $competitionType));
    }

    $badges = $query->orderBy('awarded_at', 'desc')->get();

    if ($badges->isEmpty()) {
        return;
    }

    $repeatableCounts = $badges
        ->filter(fn ($b) => $b->achievement->is_repeatable)
        ->groupBy(fn ($b) => $b->achievement->slug)
        ->map(fn ($group) => $group->count());

    $familyColors = [
        'prs' => [
            'featured'  => 'bg-sky-400/15 text-sky-200 border-sky-400/25',
            'elite'     => 'bg-sky-400/10 text-sky-300 border-sky-400/20',
            'milestone' => 'bg-sky-300/8 text-sky-300/80 border-sky-300/15',
            'earned'    => 'bg-white/5 text-sky-300/70 border-white/10',
        ],
        'royal_flush' => [
            'featured'  => 'bg-amber-400/15 text-amber-200 border-amber-400/25',
            'elite'     => 'bg-amber-400/10 text-amber-300 border-amber-400/20',
            'milestone' => 'bg-amber-300/8 text-amber-300/80 border-amber-300/15',
            'earned'    => 'bg-white/5 text-amber-300/70 border-white/10',
        ],
    ];

    $crestStyles = [
        'prs' => [
            'featured'  => 'border-sky-400/35 bg-gradient-to-b from-sky-400/18 to-sky-600/8 text-sky-200 shadow-[0_0_10px_rgba(56,189,248,0.1)]',
            'elite'     => 'border-sky-400/25 bg-gradient-to-b from-sky-400/14 to-sky-500/6 text-sky-200',
            'milestone' => 'border-sky-300/20 bg-gradient-to-b from-sky-400/10 to-sky-500/4 text-sky-300',
            'earned'    => 'border-white/12 bg-gradient-to-b from-white/7 to-white/3 text-sky-300/80',
        ],
        'royal_flush' => [
            'featured'  => 'border-amber-400/35 bg-gradient-to-b from-amber-400/18 to-orange-500/8 text-amber-200 shadow-[0_0_10px_rgba(251,191,36,0.1)]',
            'elite'     => 'border-amber-400/25 bg-gradient-to-b from-amber-400/14 to-orange-500/6 text-amber-200',
            'milestone' => 'border-amber-400/18 bg-gradient-to-b from-amber-400/10 to-orange-500/4 text-amber-300',
            'earned'    => 'border-white/12 bg-gradient-to-b from-white/7 to-white/3 text-amber-300/80',
        ],
    ];

    $distCrestStyles = [
        'dist-700' => 'border-red-400/35 bg-gradient-to-b from-red-400/18 to-orange-500/8 text-red-200 shadow-[0_0_10px_rgba(248,113,113,0.1)]',
        'dist-600' => 'border-orange-400/25 bg-gradient-to-b from-orange-400/14 to-amber-500/6 text-orange-200',
        'dist-500' => 'border-yellow-400/20 bg-gradient-to-b from-yellow-400/10 to-amber-500/4 text-yellow-300',
        'dist-400' => 'border-emerald-400/15 bg-gradient-to-b from-emerald-400/10 to-green-500/4 text-emerald-300/80',
    ];

    $categoryLabels = [
        'match_special' => 'Signature',
        'lifetime'      => 'Milestones',
        'repeatable'    => 'Earned',
    ];
@endphp

@if($trophy)
    {{--
        Trophy strip — the dashboard's "Welcome back, Paul" sidekick.
        Square crests, no text labels, hover lift + idle breathe on rarer
        tiers, click-through to the gallery. Sorted strongest-first
        (signature → elite → milestone → earned) so the eye lands on the
        rarest hardware first. Repeatable badges (e.g. podiums earned at
        multiple matches) get a count overlay so a 3× podium reads as one
        crest with a "×3" rather than three identical crests in a row.
    --}}
    @php
        $trophyBadges = $badges
            ->unique(fn ($b) => $b->achievement_id)
            ->sortBy(fn ($b) => ($tierOrder[$badgeConfig[$b->achievement->slug]['tier'] ?? 'earned'] ?? 9))
            ->values();
        // The breathe animation lives in resources/css/app.css under the
        // `.badge-trophy-*` selectors below — keeping it inline as a
        // <style> block rather than touching the global stylesheet so the
        // animation ships and ages with this component.
    @endphp

    <style>
        /* Stagger fade-in so the strip "draws itself" as the page settles
           rather than popping in all at once. The --i CSS var is set per
           crest below; an 80ms step keeps the whole strip done in well
           under a second even with a dozen badges. */
        @keyframes badge-trophy-in {
            from { opacity: 0; transform: translateY(8px) scale(0.92); }
            to   { opacity: 1; transform: translateY(0)   scale(1); }
        }
        /* Idle breathe — a 4s gentle scale loop only applied to the rarer
           tiers (featured + elite), so the eye is drawn to the hardware
           that's actually hard to earn instead of every commodity badge
           in the cabinet wobbling for attention. */
        @keyframes badge-trophy-breathe {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-2px); }
        }
        .badge-trophy-cell {
            animation: badge-trophy-in 480ms ease-out both;
            animation-delay: calc(var(--i, 0) * 70ms);
        }
        @media (prefers-reduced-motion: reduce) {
            /* Honour OS-level "reduce motion" — no entrance animation, no
               idle breathe. The hover lift stays because that's a direct
               response to user input rather than ambient motion. */
            .badge-trophy-cell        { animation: none !important; }
            .badge-trophy-cell-rare   { animation: none !important; }
        }
        .badge-trophy-cell-rare {
            animation:
                badge-trophy-in 480ms ease-out both,
                badge-trophy-breathe 4.2s ease-in-out infinite;
            /* Stagger the breathe phase too so the rare badges don't all
               pulse in lockstep (which reads as a single throbbing block
               rather than individual living trophies). */
            animation-delay: calc(var(--i, 0) * 70ms), calc(var(--i, 0) * 350ms);
        }
        .badge-trophy-cell:hover,
        .badge-trophy-cell-rare:hover {
            transform: translateY(-3px);
            transition: transform 180ms ease-out;
        }
    </style>

    <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
        @foreach($trophyBadges as $bi => $badge)
            @php
                $a = $badge->achievement;
                $cfg = $badgeConfig[$a->slug] ?? [];
                $icon = $cfg['icon'] ?? 'target';
                $tier = $cfg['tier'] ?? 'earned';
                $family = $a->competition_type ?? 'prs';
                $count = $repeatableCounts[$a->slug] ?? 1;
                $isRare = in_array($tier, ['featured', 'elite'], true);
                $criteria = \App\Http\Controllers\BadgeGalleryController::criteriaFor($a->slug);
                // Native browser title is good enough — Alpine popovers
                // would be nice but the dashboard already pays for Livewire
                // and we don't need another JS dance just to see badge copy
                // on hover. Tap on mobile still opens a long-press preview.
                $tooltip = trim(
                    $a->label
                    . ($a->is_repeatable && $count > 1 ? " ×{$count}" : '')
                    . ($a->description ? "\n— " . $a->description : '')
                    . ($criteria && $criteria !== $a->description ? "\nHow to earn: " . $criteria : '')
                );
            @endphp
            <a
                href="{{ route('badges.preview') }}#badge-{{ $a->slug }}"
                title="{{ $tooltip }}"
                aria-label="{{ $a->label }}{{ $a->is_repeatable && $count > 1 ? " ×{$count}" : '' }}"
                class="group relative block {{ $isRare ? 'badge-trophy-cell-rare' : 'badge-trophy-cell' }}"
                style="--i: {{ $bi }};"
            >
                <x-badge-crest :icon="$icon" :tier="$tier" :family="$family === 'royal_flush' ? 'royal_flush' : 'prs'" />
                @if($a->is_repeatable && $count > 1)
                    {{-- Count overlay: a podium earned 3× shows once with "×3" --}}
                    {{-- rather than three identical crests cluttering the strip. --}}
                    <span class="pointer-events-none absolute -top-1.5 -right-1.5 z-20 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full border border-white/15 bg-zinc-900/95 px-1 text-[10px] font-bold text-white shadow-md backdrop-blur-sm">
                        ×{{ $count }}
                    </span>
                @endif
            </a>
        @endforeach
    </div>
@elseif($compact)
    <div class="flex flex-wrap items-center gap-1" x-data="{ activePopover: null }" @click.outside="activePopover = null">
        @foreach($badges->unique(fn ($b) => $b->achievement_id)->sortBy(fn ($b) => ($tierOrder[$badgeConfig[$b->achievement->slug]['tier'] ?? 'earned'] ?? 9))->values() as $bi => $badge)
            @php
                $a = $badge->achievement;
                $cfg = $badgeConfig[$a->slug] ?? [];
                $icon = $cfg['icon'] ?? 'target';
                $tier = $cfg['tier'] ?? 'earned';
                $family = $a->competition_type ?? 'prs';
                $isDist = str_starts_with($icon, 'dist-');
                $crest = ($isDist && isset($distCrestStyles[$icon])) ? $distCrestStyles[$icon] : ($crestStyles[$family][$tier] ?? $crestStyles['prs']['earned']);
                $count = $repeatableCounts[$a->slug] ?? 1;
                $criteria = \App\Http\Controllers\BadgeGalleryController::criteriaFor($a->slug);
                $tierLabel = match($tier) {
                    'featured'  => 'Signature Badge',
                    'elite'     => 'Elite',
                    'milestone' => 'Lifetime Milestone',
                    default     => 'Earned',
                };
            @endphp
            <div class="relative">
                <button type="button" @click.stop="activePopover = activePopover === {{ $bi }} ? null : {{ $bi }}"
                        class="group relative inline-flex items-center justify-center rounded-md border transition-transform duration-150 hover:scale-110 cursor-pointer {{ $crest }} h-6 w-6"
                        aria-label="{{ $a->label }} — tap for earning criteria">
                    <x-badge-icon :name="$icon" class="h-3 w-3" />
                    @if($a->is_repeatable && $count > 1)
                        <span class="absolute -top-1 -right-1 flex h-3 min-w-[0.75rem] items-center justify-center rounded-full bg-white/15 px-0.5 text-[7px] font-bold text-white/80 backdrop-blur-sm">{{ $count }}</span>
                    @endif
                </button>
                <div x-show="activePopover === {{ $bi }}" x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="absolute left-1/2 z-50 w-72 -translate-x-1/2 rounded-xl border border-border bg-surface p-3 shadow-xl"
                     :class="$el.getBoundingClientRect().top < 200 ? 'top-full mt-1' : 'bottom-full mb-1'">
                    <div class="flex items-start gap-2.5">
                        <div class="flex-shrink-0 inline-flex h-10 w-10 items-center justify-center rounded-lg border {{ $crest }}">
                            <x-badge-icon :name="$icon" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="block text-[9px] font-bold uppercase tracking-wider {{ $family === 'royal_flush' ? 'text-amber-400/70' : 'text-sky-400/70' }}">{{ $tierLabel }}@if($a->is_repeatable && $count > 1) · &times;{{ $count }}@endif</span>
                            <p class="mt-0.5 text-sm font-bold leading-tight text-primary">{{ $a->label }}</p>
                            <p class="mt-1 text-[11px] leading-snug text-secondary">{{ $a->description }}</p>
                        </div>
                    </div>
                    @if($criteria)
                        <div class="mt-2.5 rounded-md border border-border/60 bg-app/50 px-2.5 py-2">
                            <span class="block text-[9px] font-bold uppercase tracking-wider text-muted">How to earn it</span>
                            <p class="mt-1 text-[11px] leading-snug text-secondary">{{ $criteria }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@else
    @php
        $competitionTypes = $badges->groupBy(fn ($b) => $b->achievement->competition_type ?? 'prs');
        $typeLabels = ['prs' => 'PRS', 'royal_flush' => 'Royal Flush'];
    @endphp
    <div class="space-y-5">
        @foreach($competitionTypes as $cType => $typeBadges)
            @php
                $typeGrouped = $typeBadges->groupBy(fn ($b) => $b->achievement->category);
                $typeRepeatableCounts = $typeBadges
                    ->filter(fn ($b) => $b->achievement->is_repeatable)
                    ->groupBy(fn ($b) => $b->achievement->slug)
                    ->map(fn ($group) => $group->count());
            @endphp

            @if($competitionTypes->count() > 1)
                <h3 class="text-sm font-bold uppercase tracking-wider text-primary border-b border-border pb-1">{{ $typeLabels[$cType] ?? ucfirst($cType) }} Badges</h3>
            @endif

            <div class="space-y-3">
                @foreach(['match_special', 'lifetime', 'repeatable'] as $catKey)
                    @if($typeGrouped->has($catKey) && $typeGrouped[$catKey]->isNotEmpty())
                        <div>
                            <h4 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">{{ $categoryLabels[$catKey] }}</h4>
                            <div class="flex flex-wrap gap-2">
                                @foreach($typeGrouped[$catKey]->unique(fn ($b) => $b->achievement_id)->sortBy(fn ($b) => ($tierOrder[$badgeConfig[$b->achievement->slug]['tier'] ?? 'earned'] ?? 9)) as $badge)
                                    @php
                                        $a = $badge->achievement;
                                        $cfg = $badgeConfig[$a->slug] ?? [];
                                        $icon = $cfg['icon'] ?? 'target';
                                        $tier = $cfg['tier'] ?? 'earned';
                                        $colors = $familyColors[$cType][$tier] ?? $familyColors['prs']['earned'];
                                        $count = $typeRepeatableCounts[$a->slug] ?? 1;
                                    @endphp
                                    <div class="flex items-center gap-2 rounded-xl border px-3 py-2 {{ $colors }}">
                                        <x-badge-icon :name="$icon" class="h-4 w-4 flex-shrink-0" />
                                        <div class="min-w-0">
                                            <p class="text-xs font-bold">
                                                {{ $a->label }}
                                                @if($a->is_repeatable && $count > 1)
                                                    <span class="text-[10px] opacity-60">&times;{{ $count }}</span>
                                                @endif
                                            </p>
                                            <p class="text-[10px] opacity-60 leading-tight">{{ $a->description }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endforeach
    </div>
@endif
