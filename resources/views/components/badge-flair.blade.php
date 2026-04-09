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
    @php $flairId = 'bf-' . $userId . '-' . Str::random(4); @endphp
    <div class="flex items-center gap-1" x-data="{ activePopover: null }" @click.outside="activePopover = null">
        @foreach($badges as $bi => $userBadge)
            @php
                $a = $userBadge->achievement;
                $cfg = $badgeConfig[$a->slug] ?? [];
                $icon = $cfg['icon'] ?? 'target';
                $tier = $cfg['tier'] ?? 'earned';
                $family = $a->competition_type ?? 'prs';
                $isDist = str_starts_with($icon, 'dist-');
                $medalFlairStyles = [
                    'medal-1' => 'border-amber-400/40 bg-gradient-to-b from-amber-400/22 to-yellow-600/10 text-amber-300 shadow-[0_0_10px_rgba(251,191,36,0.12)]',
                    'medal-2' => 'border-slate-300/35 bg-gradient-to-b from-slate-300/18 to-slate-400/8 text-slate-300 shadow-[0_0_10px_rgba(203,213,225,0.08)]',
                    'medal-3' => 'border-orange-400/35 bg-gradient-to-b from-orange-400/18 to-amber-700/8 text-orange-300 shadow-[0_0_10px_rgba(251,146,60,0.1)]',
                ];
                $styles = $familyStyles[$family] ?? $familyStyles['prs'];
                $isMedal = isset($medalFlairStyles[$icon]);
                $crestClass = $isMedal ? $medalFlairStyles[$icon] : (($isDist && isset($distFlairStyles[$icon])) ? $distFlairStyles[$icon] : ($styles[$tier] ?? $styles['earned']));
                $popId = $bi;
            @endphp
            <div class="relative">
                <button type="button"
                        @click.stop="activePopover = activePopover === {{ $popId }} ? null : {{ $popId }}"
                        class="group relative inline-flex items-center justify-center rounded-md border {{ $crestClass }} transition-transform duration-150 hover:scale-110 cursor-pointer h-6 w-6">
                    <x-badge-icon :name="$icon" class="h-3 w-3" />
                </button>

                <div x-show="activePopover === {{ $popId }}" x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="absolute left-1/2 z-50 mb-1 w-48 -translate-x-1/2 rounded-lg border border-border bg-surface p-2.5 shadow-xl"
                     :class="$el.getBoundingClientRect().top < 120 ? 'top-full mt-1' : 'bottom-full mb-1'">
                    <div class="flex items-start gap-2">
                        <div class="flex-shrink-0 inline-flex h-8 w-8 items-center justify-center rounded-lg border {{ $crestClass }}">
                            <x-badge-icon :name="$icon" class="h-4 w-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold text-primary leading-tight">{{ $a->label }}</p>
                            <p class="mt-0.5 text-[10px] leading-snug text-muted line-clamp-2">{{ $a->description }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        @if($remaining > 0 && $linkProfile)
            <a href="{{ route('shooter.profile', $userId) }}"
               class="inline-flex h-5 items-center rounded-md border border-white/10 bg-white/5 px-1.5 text-[9px] font-medium text-zinc-400 transition-colors hover:text-white hover:bg-white/10">
                +{{ $remaining }}
            </a>
        @elseif($remaining > 0)
            <span class="inline-flex h-5 items-center rounded-md border border-white/10 bg-white/5 px-1.5 text-[9px] font-medium text-zinc-400">
                +{{ $remaining }}
            </span>
        @endif
    </div>
@endif
