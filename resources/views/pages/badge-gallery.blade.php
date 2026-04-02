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
        ],
        'royal_flush' => [
            'label' => 'Royal Flush',
            'desc' => 'Earned during Royal Flush relay competitions. Based on flush completions and elite distance shooting.',
            'divider' => 'from-transparent via-amber-400/20 to-transparent',
            'titleColor' => 'text-amber-200/90',
            'descColor' => 'text-zinc-400',
            'iconColor' => 'text-amber-300/70',
        ],
    ];

    $categoryMeta = [
        'match_special' => [
            'label' => 'Signature Badges',
            'desc' => 'Unique to each match. Only one shooter (or none) can earn these per event.',
        ],
        'lifetime' => [
            'label' => 'Lifetime Achievements',
            'desc' => 'Earned once and kept forever. Prove it once and the badge is yours for life.',
        ],
        'repeatable' => [
            'label' => 'Stackable Badges',
            'desc' => 'Earned every time the conditions are met. Stack them across matches.',
        ],
    ];
@endphp

<x-layouts.scoreboard>
    <div class="mx-auto min-h-screen max-w-6xl px-4 py-14 sm:px-6 lg:px-8">

        {{-- Page header --}}
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
            <div class="mt-8 h-px bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>
        </header>

        @forelse($byCompetition as $competitionType => $group)
            @php
                $meta = $competitionMeta[$competitionType] ?? [
                    'label' => ucfirst($competitionType),
                    'desc' => '',
                    'divider' => 'from-transparent via-white/8 to-transparent',
                    'titleColor' => 'text-white/80',
                    'descColor' => 'text-zinc-400',
                    'iconColor' => 'text-white/50',
                ];
                $sectionIcon = $sectionIcons[$competitionType] ?? 'target';
                $matchSpecials = $group->where('category', 'match_special')->values();
                $lifetime = $group->where('category', 'lifetime')->values();
                $repeatable = $group->where('category', 'repeatable')->values();
            @endphp

            <section class="mb-18">
                {{-- Section header --}}
                <div class="mb-10">
                    <div class="flex items-center gap-3">
                        <div class="grid h-10 w-10 place-items-center rounded-xl border border-white/8 bg-white/4">
                            <x-badge-icon :name="$sectionIcon" class="h-5 w-5 {{ $meta['iconColor'] }}" />
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold tracking-tight {{ $meta['titleColor'] }} sm:text-2xl">{{ $meta['label'] }}</h2>
                            <p class="mt-0.5 text-xs {{ $meta['descColor'] }} sm:text-sm">{{ $meta['desc'] }}</p>
                        </div>
                    </div>
                    <div class="mt-5 h-px bg-gradient-to-r {{ $meta['divider'] }}"></div>
                </div>

                @foreach([
                    'match_special' => $matchSpecials,
                    'lifetime'      => $lifetime,
                    'repeatable'    => $repeatable,
                ] as $catKey => $catBadges)
                    @if($catBadges->isNotEmpty())
                        @php $cat = $categoryMeta[$catKey]; @endphp
                        <x-badge-group
                            :label="$cat['label']"
                            :description="$cat['desc']"
                            :category="$catKey"
                            :badges="$catBadges"
                            :badgeConfig="$badgeConfig"
                            :family="$competitionType"
                        />
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

        {{-- Footer --}}
        <footer class="mt-20 border-t border-white/6 pt-8 text-center">
            <p class="text-xs text-zinc-500">
                &copy; {{ date('Y') }} DeadCenter &mdash;
                <a href="{{ url('/') }}" class="font-medium text-zinc-400 transition-colors hover:text-white hover:underline">deadcenter.co.za</a>
            </p>
        </footer>
    </div>
</x-layouts.scoreboard>
