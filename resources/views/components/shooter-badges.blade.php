@props(['userId', 'matchId' => null, 'competitionType' => null, 'compact' => false, 'grouped' => true])

@php
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

    $iconMap = [
        'prs-full-send'      => '🎯',
        'no-drop-stage'      => '🔥',
        'impact-chain'       => '⛓️',
        'high-efficiency'    => '📈',
        'first-blood'        => '🩸',
        'iron-shooter'       => '🛡️',
        'complete-shooter'   => '✅',
        'podium-gold'        => '🥇',
        'podium-silver'      => '🥈',
        'podium-bronze'      => '🥉',
        'first-full-send'    => '⭐',
        'first-podium'       => '⭐',
        'first-win'          => '⭐',
        'first-impact-chain' => '⭐',
        'deadcenter'         => '💀',
        'royal-flush'        => '👑',
        'flush-collector'    => '🏆',
        'small-gong-sniper'  => '🎯',
        'winning-hand'       => '🃏',
        'first-flush'        => '⭐',
    ];

    $colorMap = [
        'repeatable'    => 'bg-blue-600/20 text-blue-400 border-blue-600/30',
        'lifetime'      => 'bg-amber-600/20 text-amber-400 border-amber-600/30',
        'match_special' => 'bg-red-600/20 text-red-400 border-red-600/30',
    ];

    $grouped = $grouped ? $badges->groupBy(fn ($b) => $b->achievement->category) : collect(['all' => $badges]);

    $repeatableCounts = $badges
        ->filter(fn ($b) => $b->achievement->is_repeatable)
        ->groupBy(fn ($b) => $b->achievement->slug)
        ->map(fn ($group) => $group->count());
@endphp

@if($compact)
    <div class="flex flex-wrap gap-1">
        @foreach($badges->unique(fn ($b) => $b->achievement_id) as $badge)
            @php $a = $badge->achievement; @endphp
            <span
                class="inline-flex items-center gap-0.5 rounded-full border px-1.5 py-0.5 text-[9px] font-bold {{ $colorMap[$a->category] ?? '' }}"
                title="{{ $a->label }}: {{ $a->description }}"
            >
                {{ $iconMap[$a->slug] ?? '🏅' }}
                {{ $a->label }}
                @if($a->is_repeatable && ($repeatableCounts[$a->slug] ?? 0) > 1)
                    <span class="text-[8px] opacity-70">×{{ $repeatableCounts[$a->slug] }}</span>
                @endif
            </span>
        @endforeach
    </div>
@else
    @php
        $competitionTypes = $badges->groupBy(fn ($b) => $b->achievement->competition_type ?? 'prs');
        $typeLabels = ['prs' => 'PRS', 'royal_flush' => 'Royal Flush'];
    @endphp
    <div class="space-y-6">
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

            <div class="space-y-4">
                @if($typeGrouped->has('repeatable') && $typeGrouped['repeatable']->isNotEmpty())
                    <div>
                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Repeatable Badges</h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach($typeGrouped['repeatable']->unique(fn ($b) => $b->achievement_id) as $badge)
                                @php $a = $badge->achievement; @endphp
                                <div class="flex items-center gap-1.5 rounded-lg border px-3 py-2 {{ $colorMap['repeatable'] }}">
                                    <span class="text-base">{{ $iconMap[$a->slug] ?? '🏅' }}</span>
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold">{{ $a->label }}
                                            @if(($typeRepeatableCounts[$a->slug] ?? 0) > 1)
                                                <span class="text-[10px] opacity-70">×{{ $typeRepeatableCounts[$a->slug] }}</span>
                                            @endif
                                        </p>
                                        <p class="text-[10px] opacity-70">{{ $a->description }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($typeGrouped->has('lifetime') && $typeGrouped['lifetime']->isNotEmpty())
                    <div>
                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Lifetime Milestones</h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach($typeGrouped['lifetime'] as $badge)
                                @php $a = $badge->achievement; @endphp
                                <div class="flex items-center gap-1.5 rounded-lg border px-3 py-2 {{ $colorMap['lifetime'] }}">
                                    <span class="text-base">{{ $iconMap[$a->slug] ?? '⭐' }}</span>
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold">{{ $a->label }}</p>
                                        <p class="text-[10px] opacity-70">{{ $a->description }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($typeGrouped->has('match_special') && $typeGrouped['match_special']->isNotEmpty())
                    <div>
                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Match Specials</h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach($typeGrouped['match_special'] as $badge)
                                @php $a = $badge->achievement; @endphp
                                <div class="flex items-center gap-1.5 rounded-lg border px-3 py-2 {{ $colorMap['match_special'] }}">
                                    <span class="text-base">{{ $iconMap[$a->slug] ?? '💀' }}</span>
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold">{{ $a->label }}</p>
                                        <p class="text-[10px] opacity-70">{{ $a->description }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
