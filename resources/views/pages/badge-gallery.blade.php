@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Achievement> $achievements */
    $byCompetition = $achievements->groupBy('competition_type');
    $competitionMeta = [
        'prs' => [
            'label' => 'PRS Badges',
            'desc' => 'Badges earned during Precision Rifle Series (PRS) matches. Based on stage performance, accuracy, and consistency.',
            'accent' => 'emerald',
        ],
        'royal_flush' => [
            'label' => 'Royal Flush Badges',
            'desc' => 'Badges earned during Royal Flush relay competitions. Based on flush completions and special achievements.',
            'accent' => 'amber',
        ],
    ];
    $categoryMeta = [
        'match_special' => [
            'label' => 'Match Special',
            'tag' => 'Awarded per match',
            'color' => 'amber',
            'desc' => 'Unique to each match. Only one shooter (or none) can earn these per event.',
        ],
        'lifetime' => [
            'label' => 'Lifetime Milestones',
            'tag' => 'Earned once',
            'color' => 'purple',
            'desc' => 'One-time achievements that mark major milestones in a shooter\'s career.',
        ],
        'repeatable' => [
            'label' => 'Repeatable Badges',
            'tag' => 'Stackable',
            'color' => 'sky',
            'desc' => 'Earned every time the conditions are met. Stack count tracks how many times achieved.',
        ],
    ];
@endphp

<x-layouts.scoreboard>
    <div class="mx-auto min-h-screen max-w-4xl px-4 py-10 sm:px-6">
        <header class="mb-10 border-b border-border pb-8">
            <div class="flex items-center gap-3">
                <span class="text-3xl">🏅</span>
                <div>
                    <h1 class="text-3xl font-black text-primary sm:text-4xl">DeadCenter Badges</h1>
                    <p class="mt-1 text-sm text-muted">Every badge you can earn, how to earn it, and what it means.</p>
                </div>
            </div>
        </header>

        @forelse($byCompetition as $competitionType => $group)
            @php
                $meta = $competitionMeta[$competitionType] ?? ['label' => ucfirst($competitionType), 'desc' => '', 'accent' => 'zinc'];
                $matchSpecials = $group->where('category', 'match_special')->values();
                $lifetime = $group->where('category', 'lifetime')->values();
                $repeatable = $group->where('category', 'repeatable')->values();
            @endphp

            <section class="mb-14">
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-primary">{{ $meta['label'] }}</h2>
                    <p class="mt-1 text-sm text-muted">{{ $meta['desc'] }}</p>
                </div>

                @foreach([
                    'match_special' => $matchSpecials,
                    'lifetime' => $lifetime,
                    'repeatable' => $repeatable,
                ] as $catKey => $catBadges)
                    @if($catBadges->isNotEmpty())
                        @php $cat = $categoryMeta[$catKey]; @endphp
                        <div class="mb-8">
                            <div class="mb-3 flex flex-wrap items-center gap-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-{{ $cat['color'] }}-400">{{ $cat['label'] }}</span>
                                <span class="rounded-full bg-{{ $cat['color'] }}-600/20 px-2 py-0.5 text-[10px] font-bold text-{{ $cat['color'] }}-400">{{ $cat['tag'] }}</span>
                                <span class="text-[10px] text-zinc-500">&mdash; {{ $cat['desc'] }}</span>
                            </div>

                            <div class="{{ $catKey === 'match_special' ? 'space-y-3' : 'grid gap-2 sm:grid-cols-2' }}">
                                @foreach($catBadges as $badge)
                                    @if($catKey === 'match_special')
                                        <div class="overflow-hidden rounded-2xl border-2 border-amber-500/40 bg-gradient-to-br from-amber-900/20 via-zinc-900 to-zinc-900">
                                            <div class="flex items-start gap-4 p-4 sm:items-center sm:gap-5 sm:p-6">
                                                <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-2xl bg-amber-500/20 text-2xl sm:h-16 sm:w-16 sm:text-3xl" aria-hidden="true">
                                                    {{ $badgeIcons[$badge->slug] ?? '🏅' }}
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <h3 class="text-lg font-black text-amber-400 sm:text-xl">{{ $badge->label }}</h3>
                                                    <p class="mt-1 text-sm text-zinc-300">{{ $badge->description }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    @elseif($catKey === 'lifetime')
                                        <div class="flex items-start gap-3 rounded-xl border border-purple-500/20 bg-purple-900/10 px-4 py-3">
                                            <span class="mt-0.5 text-xl" aria-hidden="true">{{ $badgeIcons[$badge->slug] ?? '⭐' }}</span>
                                            <div class="min-w-0 flex-1">
                                                <span class="font-bold text-purple-300">{{ $badge->label }}</span>
                                                <p class="mt-0.5 text-xs text-zinc-400">{{ $badge->description }}</p>
                                            </div>
                                        </div>
                                    @else
                                        <div class="flex items-start gap-3 rounded-xl border border-sky-500/15 bg-sky-900/5 px-4 py-3">
                                            <span class="mt-0.5 text-lg" aria-hidden="true">{{ $badgeIcons[$badge->slug] ?? '🏅' }}</span>
                                            <div class="min-w-0 flex-1">
                                                <span class="font-bold text-sky-300">{{ $badge->label }}</span>
                                                <p class="mt-0.5 text-xs text-zinc-400">{{ $badge->description }}</p>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </section>
        @empty
            <p class="rounded-xl border border-border bg-surface/40 px-6 py-12 text-center text-muted">
                No badges configured yet.
            </p>
        @endforelse

        <footer class="mt-12 border-t border-border pt-8 text-center text-xs text-muted">
            &copy; {{ date('Y') }} DeadCenter &mdash;
            <a href="{{ url('/') }}" class="font-medium text-accent hover:underline">deadcenter.co.za</a>
        </footer>
    </div>
</x-layouts.scoreboard>
