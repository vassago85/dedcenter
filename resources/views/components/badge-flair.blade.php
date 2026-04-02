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

    $familyStyles = [
        'prs' => [
            'featured'  => 'border-sky-400/35 bg-gradient-to-b from-sky-400/18 to-sky-600/8 text-sky-200 shadow-[0_0_12px_rgba(56,189,248,0.1)]',
            'elite'     => 'border-sky-400/25 bg-gradient-to-b from-sky-400/14 to-sky-500/6 text-sky-200',
            'milestone' => 'border-sky-300/20 bg-gradient-to-b from-sky-400/10 to-sky-500/4 text-sky-300',
            'earned'    => 'border-white/12 bg-gradient-to-b from-white/7 to-white/3 text-sky-300/80',
        ],
        'royal_flush' => [
            'featured'  => 'border-amber-400/35 bg-gradient-to-b from-amber-400/18 to-orange-500/8 text-amber-200 shadow-[0_0_12px_rgba(251,191,36,0.1)]',
            'elite'     => 'border-amber-400/25 bg-gradient-to-b from-amber-400/14 to-orange-500/6 text-amber-200',
            'milestone' => 'border-amber-400/18 bg-gradient-to-b from-amber-400/10 to-orange-500/4 text-amber-300',
            'earned'    => 'border-white/12 bg-gradient-to-b from-white/7 to-white/3 text-amber-300/80',
        ],
    ];

    $distFlairStyles = [
        'dist-700' => 'border-red-400/35 bg-gradient-to-b from-red-400/18 to-orange-500/8 text-red-200 shadow-[0_0_10px_rgba(248,113,113,0.1)]',
        'dist-600' => 'border-orange-400/25 bg-gradient-to-b from-orange-400/14 to-amber-500/6 text-orange-200',
        'dist-500' => 'border-yellow-400/20 bg-gradient-to-b from-yellow-400/10 to-amber-500/4 text-yellow-300',
        'dist-400' => 'border-emerald-400/15 bg-gradient-to-b from-emerald-400/10 to-green-500/4 text-emerald-300/80',
    ];
@endphp

@if($badges->isNotEmpty())
    <div class="flex items-center gap-1.5">
        @foreach($badges as $userBadge)
            @php
                $a = $userBadge->achievement;
                $cfg = $badgeConfig[$a->slug] ?? [];
                $icon = $cfg['icon'] ?? 'target';
                $tier = $cfg['tier'] ?? 'earned';
                $family = $a->competition_type ?? 'prs';
                $isDist = str_starts_with($icon, 'dist-');
                $styles = $familyStyles[$family] ?? $familyStyles['prs'];
                $crestClass = ($isDist && isset($distFlairStyles[$icon])) ? $distFlairStyles[$icon] : ($styles[$tier] ?? $styles['earned']);
            @endphp
            <div x-data="{ open: false }" class="relative">
                <button type="button"
                        @mouseenter="$el._tipTimer = setTimeout(() => $el._showTip = true, 400)"
                        @mouseleave="clearTimeout($el._tipTimer); $el._showTip = false"
                        @click.stop="open = !open"
                        x-ref="trigger"
                        class="group relative inline-flex h-7 w-7 items-center justify-center rounded-lg border {{ $crestClass }} transition-transform duration-150 hover:scale-110 cursor-pointer">
                    <x-badge-icon :name="$icon" class="h-3.5 w-3.5" />
                </button>

                {{-- Popover on click --}}
                <div x-show="open"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 scale-95 translate-y-1"
                     x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     @click.outside="open = false"
                     class="absolute bottom-full left-1/2 z-50 mb-2 w-56 -translate-x-1/2 rounded-xl border border-border bg-surface p-3 shadow-xl">
                    <div class="flex items-start gap-2.5">
                        <div class="flex-shrink-0 inline-flex h-10 w-10 items-center justify-center rounded-xl border {{ $crestClass }}">
                            <x-badge-icon :name="$icon" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-primary leading-tight">{{ $a->label }}</p>
                            <p class="mt-0.5 text-[11px] leading-snug text-muted">{{ $a->description }}</p>
                        </div>
                    </div>
                    <a href="{{ route('badges.preview') }}" class="mt-2 block text-center text-[10px] font-medium text-amber-400 hover:underline">View all badges &rarr;</a>
                    <div class="absolute left-1/2 -bottom-1.5 -translate-x-1/2 h-3 w-3 rotate-45 border-r border-b border-border bg-surface"></div>
                </div>
            </div>
        @endforeach

        @if($remaining > 0 && $linkProfile)
            <a href="{{ route('shooter.profile', $userId) }}"
               class="inline-flex h-7 items-center rounded-lg border border-white/10 bg-white/5 px-2 text-[10px] font-medium text-zinc-400 transition-colors hover:text-white hover:bg-white/10">
                +{{ $remaining }}
            </a>
        @elseif($remaining > 0)
            <span class="inline-flex h-7 items-center rounded-lg border border-white/10 bg-white/5 px-2 text-[10px] font-medium text-zinc-400">
                +{{ $remaining }}
            </span>
        @endif
    </div>
@endif
