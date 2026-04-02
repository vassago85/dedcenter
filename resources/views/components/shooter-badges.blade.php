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

    $categoryLabels = [
        'match_special' => 'Signature',
        'lifetime'      => 'Milestones',
        'repeatable'    => 'Earned',
    ];
@endphp

@if($compact)
    <div class="flex flex-wrap gap-1">
        @foreach($badges->unique(fn ($b) => $b->achievement_id)->sortBy(fn ($b) => ($tierOrder[$badgeConfig[$b->achievement->slug]['tier'] ?? 'earned'] ?? 9)) as $badge)
            @php
                $a = $badge->achievement;
                $cfg = $badgeConfig[$a->slug] ?? [];
                $icon = $cfg['icon'] ?? 'target';
                $tier = $cfg['tier'] ?? 'earned';
                $family = $a->competition_type ?? 'prs';
                $colors = $familyColors[$family][$tier] ?? $familyColors['prs']['earned'];
                $count = $repeatableCounts[$a->slug] ?? 1;
            @endphp
            <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $colors }}"
                  title="{{ $a->label }}: {{ $a->description }}">
                <x-badge-icon :name="$icon" class="h-3 w-3" />
                {{ $a->label }}
                @if($a->is_repeatable && $count > 1)
                    <span class="opacity-60">&times;{{ $count }}</span>
                @endif
            </span>
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
