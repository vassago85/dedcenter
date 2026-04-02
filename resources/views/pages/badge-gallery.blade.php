@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Achievement> $achievements */
    $byCompetition = $achievements->groupBy('competition_type');

    $competitionMeta = [
        'prs' => [
            'label' => 'Precision Rifle Series',
            'desc' => 'Earned during PRS matches. Based on stage performance, accuracy, and consistency under pressure.',
            'accent' => 'blue',
            'divider' => 'from-transparent via-blue-500/30 to-transparent',
            'titleColor' => 'text-blue-200',
            'descColor' => 'text-slate-400',
        ],
        'royal_flush' => [
            'label' => 'Royal Flush',
            'desc' => 'Earned during Royal Flush relay competitions. Based on flush completions and elite distance shooting.',
            'accent' => 'amber',
            'divider' => 'from-transparent via-amber-500/30 to-transparent',
            'titleColor' => 'text-amber-200',
            'descColor' => 'text-stone-400',
        ],
    ];

    $categoryMeta = [
        'match_special' => [
            'label' => 'Match Special',
            'desc' => 'Unique awards decided within each event.',
        ],
        'lifetime' => [
            'label' => 'Lifetime Milestones',
            'desc' => 'One-time achievements marking major career moments.',
        ],
        'repeatable' => [
            'label' => 'Repeatable Badges',
            'desc' => 'Earned again whenever the conditions are met.',
        ],
    ];
@endphp

<x-layouts.scoreboard>
    <div class="mx-auto min-h-screen max-w-6xl px-4 py-12 sm:px-6 lg:px-8">

        {{-- Page header --}}
        <header class="mb-14">
            <div class="flex items-center gap-4 sm:gap-5">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl border border-zinc-700/50 bg-gradient-to-br from-zinc-700/30 to-zinc-900 sm:h-16 sm:w-16">
                    <x-badge-icon name="target" class="h-7 w-7 text-zinc-300 sm:h-8 sm:w-8" />
                </div>
                <div>
                    <h1 class="text-3xl font-black tracking-tight text-primary sm:text-4xl">Achievement Gallery</h1>
                    <p class="mt-1.5 text-sm text-muted sm:text-base">Every badge you can earn, how to earn it, and what it means.</p>
                </div>
            </div>
            <div class="mt-6 h-px bg-gradient-to-r from-transparent via-border to-transparent"></div>
        </header>

        @forelse($byCompetition as $competitionType => $group)
            @php
                $meta = $competitionMeta[$competitionType] ?? [
                    'label' => ucfirst($competitionType),
                    'desc' => '',
                    'accent' => 'zinc',
                    'divider' => 'from-transparent via-zinc-600/30 to-transparent',
                    'titleColor' => 'text-zinc-200',
                    'descColor' => 'text-zinc-400',
                ];
                $sectionIcon = $sectionIcons[$competitionType] ?? 'target';
                $matchSpecials = $group->where('category', 'match_special')->values();
                $lifetime = $group->where('category', 'lifetime')->values();
                $repeatable = $group->where('category', 'repeatable')->values();
            @endphp

            <section class="mb-16">
                {{-- Competition type section header --}}
                <div class="mb-8">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-zinc-700/40 bg-zinc-800/60">
                            <x-badge-icon :name="$sectionIcon" class="h-5 w-5 {{ $meta['titleColor'] }}" />
                        </div>
                        <div>
                            <h2 class="text-xl font-black tracking-tight {{ $meta['titleColor'] }} sm:text-2xl">{{ $meta['label'] }}</h2>
                            <p class="mt-0.5 text-xs {{ $meta['descColor'] }} sm:text-sm">{{ $meta['desc'] }}</p>
                        </div>
                    </div>
                    <div class="mt-4 h-px bg-gradient-to-r {{ $meta['divider'] }}"></div>
                </div>

                {{-- Category sub-groups --}}
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
                            :tier="$catKey"
                            :badges="$catBadges"
                            :badgeConfig="$badgeConfig"
                            :family="$competitionType"
                        />
                    @endif
                @endforeach
            </section>
        @empty
            <div class="flex flex-col items-center justify-center rounded-2xl border border-border bg-surface/40 px-6 py-20 text-center">
                <x-badge-icon name="award" class="mb-4 h-12 w-12 text-zinc-600" />
                <p class="text-lg font-semibold text-muted">No badges configured yet</p>
                <p class="mt-1 text-sm text-zinc-500">Achievements will appear here once they are set up.</p>
            </div>
        @endforelse

        {{-- Footer --}}
        <footer class="mt-16 border-t border-border pt-8 text-center">
            <p class="text-xs text-zinc-500">
                &copy; {{ date('Y') }} DeadCenter &mdash;
                <a href="{{ url('/') }}" class="font-medium text-secondary transition-colors hover:text-primary hover:underline">deadcenter.co.za</a>
            </p>
        </footer>
    </div>
</x-layouts.scoreboard>
