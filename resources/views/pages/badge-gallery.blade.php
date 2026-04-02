@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Achievement> $achievements */
    $byCompetition = $achievements->groupBy('competition_type');

    $competitionMeta = [
        'prs' => [
            'label' => 'Precision Rifle Series',
            'desc' => 'Earned during PRS matches. Based on stage performance, accuracy, and consistency under pressure.',
            'divider' => 'from-transparent via-sky-400/20 to-transparent',
            'titleColor' => 'text-sky-200/90',
            'descColor' => 'text-zinc-400',
            'iconColor' => 'text-sky-300/70',
            'headerIcon' => 'target',
        ],
        'royal_flush' => [
            'label' => 'Royal Flush',
            'desc' => 'Earned during Royal Flush relay competitions. Based on flush completions and elite distance shooting.',
            'divider' => 'from-transparent via-amber-400/20 to-transparent',
            'titleColor' => 'text-amber-200/90',
            'descColor' => 'text-zinc-400',
            'iconColor' => 'text-amber-300/70',
            'headerIcon' => 'crown',
        ],
    ];

    $sections = [
        'match_special' => [
            'label' => 'Signature Badges',
            'desc' => 'Unique to each match. Only one shooter (or none) can earn these per event.',
            'icon' => 'sparkles',
            'prs'  => ['text' => 'text-sky-300/80',   'line' => 'from-transparent via-sky-400/30 to-transparent',   'icon' => 'text-sky-400/70'],
            'rf'   => ['text' => 'text-amber-300/80', 'line' => 'from-transparent via-amber-400/30 to-transparent', 'icon' => 'text-amber-400/70'],
        ],
        'lifetime' => [
            'label' => 'Earned Once',
            'desc' => 'Earned once and kept forever. Prove it once and the badge is yours for life.',
            'icon' => 'award',
            'prs'  => ['text' => 'text-sky-300/60',   'line' => 'from-transparent via-white/10 to-transparent', 'icon' => 'text-sky-400/50'],
            'rf'   => ['text' => 'text-amber-400/60', 'line' => 'from-transparent via-white/10 to-transparent', 'icon' => 'text-amber-400/50'],
        ],
        'repeatable' => [
            'label' => 'Stackable',
            'desc' => 'Earned every time the conditions are met. Stack them across matches and seasons.',
            'icon' => 'layers',
            'prs'  => ['text' => 'text-white/40', 'line' => 'from-transparent via-white/8 to-transparent', 'icon' => 'text-white/30'],
            'rf'   => ['text' => 'text-white/40', 'line' => 'from-transparent via-white/8 to-transparent', 'icon' => 'text-white/30'],
        ],
    ];
@endphp

<x-layouts.scoreboard>
    <div class="mx-auto min-h-screen max-w-6xl px-4 py-14 sm:px-6 lg:px-8">

        <header class="mb-16">
            <div class="flex items-center gap-5">
                <div class="grid h-14 w-14 place-items-center rounded-2xl border border-white/10 bg-white/5 sm:h-16 sm:w-16">
                    <x-badge-icon name="deadcenter" class="h-7 w-7 text-white/60 sm:h-8 sm:w-8" />
                </div>
                <div>
                    <h1 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">Achievement Gallery</h1>
                    <p class="mt-1.5 text-sm text-zinc-400 sm:text-base">Every badge you can earn, how to earn it, and what it means.</p>
                </div>
            </div>
            <div class="mt-6 flex items-center gap-3">
                <span class="rounded-full border border-white/8 bg-white/4 px-3 py-0.5 text-[11px] tabular-nums text-zinc-400">{{ $achievements->count() }} badges total</span>
                <span class="rounded-full border border-sky-400/15 bg-sky-400/5 px-3 py-0.5 text-[11px] tabular-nums text-sky-300/70">{{ $byCompetition->get('prs', collect())->count() }} PRS</span>
                <span class="rounded-full border border-amber-400/15 bg-amber-400/5 px-3 py-0.5 text-[11px] tabular-nums text-amber-300/70">{{ $byCompetition->get('royal_flush', collect())->count() }} Royal Flush</span>
            </div>
            <div class="mt-8 h-px bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>
        </header>

        @forelse($byCompetition as $competitionType => $group)
            @php
                $cMeta = $competitionMeta[$competitionType] ?? [
                    'label' => ucfirst($competitionType), 'desc' => '', 'headerIcon' => 'target',
                    'divider' => 'from-transparent via-white/8 to-transparent',
                    'titleColor' => 'text-white/80', 'descColor' => 'text-zinc-400', 'iconColor' => 'text-white/50',
                ];
                $family = $competitionType;
                $familyKey = $competitionType === 'royal_flush' ? 'rf' : 'prs';

                $matchSpecials = $group->where('category', 'match_special')->sortBy('sort_order')->values();
                $lifetime      = $group->where('category', 'lifetime')->sortBy('sort_order')->values();
                $repeatable    = $group->where('category', 'repeatable')->sortBy('sort_order')->values();
            @endphp

            <section class="mb-20">
                <div class="mb-10">
                    <div class="flex items-center gap-3">
                        <div class="grid h-10 w-10 place-items-center rounded-xl border border-white/8 bg-white/4">
                            <x-badge-icon :name="$cMeta['headerIcon']" class="h-5 w-5 {{ $cMeta['iconColor'] }}" />
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold tracking-tight {{ $cMeta['titleColor'] }} sm:text-2xl">{{ $cMeta['label'] }}</h2>
                            <p class="mt-0.5 text-xs {{ $cMeta['descColor'] }} sm:text-sm">{{ $cMeta['desc'] }}</p>
                        </div>
                        <span class="ml-auto rounded-full border border-white/8 bg-white/4 px-3 py-0.5 text-[11px] tabular-nums text-zinc-500">{{ $group->count() }} badges</span>
                    </div>
                    <div class="mt-5 h-px bg-gradient-to-r {{ $cMeta['divider'] }}"></div>
                </div>

                @foreach(['match_special' => $matchSpecials, 'lifetime' => $lifetime, 'repeatable' => $repeatable] as $catKey => $catBadges)
                    @if($catBadges->isNotEmpty())
                        @php
                            $sec = $sections[$catKey];
                            $hs = $sec[$familyKey];
                        @endphp
                        <div class="mb-12">
                            <div class="mb-6">
                                <div class="flex items-center gap-2.5">
                                    <x-badge-icon :name="$sec['icon']" class="h-4 w-4 {{ $hs['icon'] }}" />
                                    <h3 class="text-[13px] font-semibold uppercase tracking-[0.14em] {{ $hs['text'] }}">{{ $sec['label'] }}</h3>
                                    <span class="rounded-full border border-white/6 bg-white/3 px-2 py-0.5 text-[10px] tabular-nums text-zinc-500">{{ $catBadges->count() }}</span>
                                </div>
                                <p class="mt-1.5 pl-6.5 text-xs text-zinc-500">{{ $sec['desc'] }}</p>
                                <div class="mt-3 h-px bg-gradient-to-r {{ $hs['line'] }}"></div>
                            </div>

                            <div class="grid gap-5 grid-cols-1 md:grid-cols-2 xl:grid-cols-3">
                                @foreach($catBadges as $badge)
                                    @php
                                        $cfg = $badgeConfig[$badge->slug] ?? [];
                                        $icon = $cfg['icon'] ?? 'target';
                                        $badgeTier = $cfg['tier'] ?? 'earned';
                                        $earnChip = $cfg['earnChip'] ?? null;
                                        $isFeaturedCard = $badgeTier === 'featured';
                                    @endphp
                                    <div>
                                        <x-badge-card
                                            :badge="$badge"
                                            :icon="$icon"
                                            :tier="$badgeTier"
                                            :family="$family"
                                            :earnChip="$earnChip"
                                        />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </section>
        @empty
            <div class="flex flex-col items-center justify-center rounded-3xl border border-white/8 bg-zinc-950/80 px-6 py-20 text-center">
                <x-badge-icon name="award" class="mb-4 h-12 w-12 text-zinc-600" />
                <p class="text-lg font-semibold text-white/60">No badges configured yet</p>
                <p class="mt-1 text-sm text-zinc-500">Achievements will appear here once they are set up.</p>
            </div>
        @endforelse

        <footer class="mt-20 border-t border-white/6 pt-8 text-center">
            <p class="text-xs text-zinc-500">
                &copy; {{ date('Y') }} DeadCenter &mdash;
                <a href="{{ url('/') }}" class="font-medium text-zinc-400 transition-colors hover:text-white hover:underline">deadcenter.co.za</a>
            </p>
        </footer>
    </div>
</x-layouts.scoreboard>
