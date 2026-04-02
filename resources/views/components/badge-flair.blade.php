{{-- Compact inline badge flair: shows top badges as tiny crests with optional profile link --}}
@props(['userId', 'limit' => 5, 'linkProfile' => true])

@php
    use App\Models\UserAchievement;
    use App\Http\Controllers\BadgeGalleryController;

    $badgeConfig = BadgeGalleryController::BADGE_CONFIG;
    $tierOrder = ['featured' => 0, 'elite' => 1, 'milestone' => 2, 'earned' => 3];

    $badges = UserAchievement::where('user_id', $userId)
        ->with('achievement')
        ->get()
        ->unique('achievement_id')
        ->sortBy(function ($b) use ($badgeConfig, $tierOrder) {
            $tier = $badgeConfig[$b->achievement->slug]['tier'] ?? 'earned';
            return ($tierOrder[$tier] ?? 9) * 1000 + ($b->achievement->sort_order ?? 99);
        })
        ->take($limit);

    $total = UserAchievement::where('user_id', $userId)->count();
    $remaining = max(0, $total - $badges->count());

    $familyColors = [
        'prs' => ['icon' => 'text-sky-300', 'bg' => 'bg-sky-400/10 border-sky-400/20'],
        'royal_flush' => ['icon' => 'text-amber-300', 'bg' => 'bg-amber-400/10 border-amber-400/20'],
    ];
@endphp

@if($badges->isNotEmpty())
    <div class="flex items-center gap-1">
        @foreach($badges as $userBadge)
            @php
                $a = $userBadge->achievement;
                $cfg = $badgeConfig[$a->slug] ?? [];
                $icon = $cfg['icon'] ?? 'target';
                $family = $a->competition_type ?? 'prs';
                $fc = $familyColors[$family] ?? $familyColors['prs'];
            @endphp
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-md border {{ $fc['bg'] }}"
                  title="{{ $a->label }}">
                <x-badge-icon :name="$icon" class="h-3.5 w-3.5 {{ $fc['icon'] }}" />
            </span>
        @endforeach

        @if($remaining > 0 && $linkProfile)
            <a href="{{ route('shooter.profile', $userId) }}"
               class="inline-flex h-6 items-center rounded-md border border-white/10 bg-white/5 px-1.5 text-[10px] font-medium text-zinc-400 transition-colors hover:text-white">
                +{{ $remaining }}
            </a>
        @elseif($remaining > 0)
            <span class="inline-flex h-6 items-center rounded-md border border-white/10 bg-white/5 px-1.5 text-[10px] font-medium text-zinc-400">
                +{{ $remaining }}
            </span>
        @endif
    </div>
@endif
