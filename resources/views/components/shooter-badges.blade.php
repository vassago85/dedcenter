@props(['userId', 'matchId' => null, 'competitionType' => null, 'compact' => false, 'grouped' => true])

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

@if($compact)
    <div class="flex flex-wrap items-center gap-1.5">
        @foreach($badges->unique(fn ($b) => $b->achievement_id)->sortBy(fn ($b) => ($tierOrder[$badgeConfig[$b->achievement->slug]['tier'] ?? 'earned'] ?? 9)) as $badge)
            @php
                $a = $badge->achievement;
                $cfg = $badgeConfig[$a->slug] ?? [];
                $icon = $cfg['icon'] ?? 'target';
                $tier = $cfg['tier'] ?? 'earned';
                $family = $a->competition_type ?? 'prs';
                $isDist = str_starts_with($icon, 'dist-');
                $crest = ($isDist && isset($distCrestStyles[$icon])) ? $distCrestStyles[$icon] : ($crestStyles[$family][$tier] ?? $crestStyles['prs']['earned']);
                $count = $repeatableCounts[$a->slug] ?? 1;
            @endphp
            <div x-data="{ open: false }" class="relative">
                <button type="button" @click.stop="open = !open"
                        class="group relative inline-flex items-center justify-center rounded-lg border transition-transform duration-150 hover:scale-110 cursor-pointer {{ $crest }} {{ $isDist ? 'h-6 px-1.5 gap-0.5' : 'h-7 w-7' }}">
                    @if($isDist)
                        <x-badge-icon :name="$icon" class="h-3 w-3" />
                    @else
                        <x-badge-icon :name="$icon" class="h-3.5 w-3.5" />
                    @endif
                    @if($a->is_repeatable && $count > 1)
                        <span class="absolute -top-1 -right-1 flex h-3.5 min-w-[0.875rem] items-center justify-center rounded-full bg-white/15 px-0.5 text-[8px] font-bold text-white/80 backdrop-blur-sm">{{ $count }}</span>
                    @endif
                </button>
                <div x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95 translate-y-1" x-transition:enter-end="opacity-100 scale-100 translate-y-0" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" @click.outside="open = false"
                     class="absolute bottom-full left-1/2 z-50 mb-2 w-52 -translate-x-1/2 rounded-xl border border-border bg-surface p-3 shadow-xl">
                    <div class="flex items-start gap-2.5">
                        <div class="flex-shrink-0 inline-flex h-9 w-9 items-center justify-center rounded-xl border {{ $crest }}">
                            <x-badge-icon :name="$icon" class="h-4.5 w-4.5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-primary leading-tight">{{ $a->label }}</p>
                            <p class="mt-0.5 text-[11px] leading-snug text-muted">{{ $a->description }}</p>
                        </div>
                    </div>
                    <div class="absolute left-1/2 -bottom-1.5 -translate-x-1/2 h-3 w-3 rotate-45 border-r border-b border-border bg-surface"></div>
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
