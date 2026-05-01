<?php

use App\Models\User;
use App\Models\UserAchievement;
use App\Models\Shooter;
use App\Enums\MatchStatus;
use App\Http\Controllers\BadgeGalleryController;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    class extends Component {
    public User $user;

    public function with(): array
    {
        $userId = $this->user->id;
        $badgeConfig = BadgeGalleryController::BADGE_CONFIG;

        $earned = UserAchievement::where('user_id', $userId)
            ->with(['achievement', 'match', 'stage'])
            ->orderBy('awarded_at', 'desc')
            ->get();

        $uniqueBadges = $earned->unique('achievement_id');

        $byCompetition = $earned->groupBy(fn ($b) => $b->achievement->competition_type ?? 'prs');

        // All Shooter rows for this user where the match has finished — we
        // render these as tappable cards linking to each per-shooter match
        // report (/scoreboard/{match}/report/{shooter}). The report route
        // is public, so spectators reading the profile can still open it.
        $shooterMatches = Shooter::where('user_id', $userId)
            ->whereHas('squad.match', fn ($q) => $q->where('status', MatchStatus::Completed))
            ->with(['squad.match' => fn ($q) => $q->select('id', 'name', 'date', 'location', 'scoring_type', 'status')])
            ->get()
            ->filter(fn ($s) => $s->squad && $s->squad->match)
            ->sortByDesc(fn ($s) => $s->squad->match->date)
            ->values();

        $matchesShot = $shooterMatches->count();

        $totalBadges = $earned->count();
        $uniqueCount = $uniqueBadges->count();

        $podiums = $earned->filter(
            fn ($b) => in_array($b->achievement->slug, ['podium-gold', 'podium-silver', 'podium-bronze'])
        )->count();

        return compact(
            'earned', 'uniqueBadges', 'byCompetition',
            'matchesShot', 'totalBadges', 'uniqueCount', 'podiums',
            'shooterMatches', 'badgeConfig'
        );
    }
}; ?>

<div class="mx-auto min-h-screen max-w-5xl px-4 py-14 sm:px-6 lg:px-8">

    {{-- Profile header --}}
    <header class="mb-12">
        <div class="flex items-center gap-5">
            <div class="grid h-16 w-16 place-items-center rounded-2xl border border-white/10 bg-white/5 text-2xl font-bold text-white/60 sm:h-20 sm:w-20 sm:text-3xl">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl">{{ $user->name }}</h1>
                <p class="mt-1 text-sm text-zinc-400">
                    Member since {{ $user->created_at->format('M Y') }}
                </p>
            </div>
        </div>

        {{-- Stats row --}}
        <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach([
                ['label' => 'Matches Shot', 'value' => $matchesShot],
                ['label' => 'Total Badges', 'value' => $totalBadges],
                ['label' => 'Unique Badges', 'value' => $uniqueCount],
                ['label' => 'Podiums', 'value' => $podiums],
            ] as $stat)
                <div class="rounded-2xl border border-white/8 bg-zinc-950/80 px-4 py-3 text-center">
                    <p class="text-2xl font-semibold text-white">{{ $stat['value'] }}</p>
                    <p class="mt-0.5 text-[11px] font-medium uppercase tracking-wide text-zinc-500">{{ $stat['label'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-8 h-px bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>
    </header>

    {{-- ============================================================
         MY MATCHES — tappable cards linking to each shooter's
         per-match report (/scoreboard/{match}/report/{shooter}).
         The report route is public, so this section renders for any
         visitor of the profile, not just the shooter themself.
    ============================================================= --}}
    @if($shooterMatches->isNotEmpty())
        <section class="mb-14">
            <div class="mb-6 flex items-center gap-3">
                <div class="grid h-9 w-9 place-items-center rounded-xl border border-white/8 bg-white/4">
                    <x-badge-icon name="flag" class="h-4 w-4 text-white/60" />
                </div>
                <h2 class="text-lg font-semibold tracking-tight text-white/90 sm:text-xl">Match History</h2>
                <span class="ml-auto rounded-full border border-white/8 bg-white/4 px-3 py-0.5 text-[11px] tabular-nums text-zinc-400">
                    {{ $shooterMatches->count() }} {{ \Illuminate\Support\Str::plural('match', $shooterMatches->count()) }}
                </span>
            </div>
            <div class="mb-8 h-px bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>

            <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach($shooterMatches as $sm)
                    @php
                        $m = $sm->squad->match;
                        $typeLabel = strtoupper($m->scoring_type ?? 'standard');
                        $typeClasses = match (strtolower($m->scoring_type ?? '')) {
                            'prs'      => 'bg-amber-500/10 text-amber-300 ring-amber-500/30',
                            'elr'      => 'bg-sky-500/10 text-sky-300 ring-sky-500/30',
                            default    => 'bg-rose-500/10 text-rose-300 ring-rose-500/30',
                        };
                    @endphp
                    <li>
                        <a href="{{ route('scoreboard.matches.report.view', [$m, $sm]) }}"
                           class="group flex h-full flex-col justify-between gap-3 rounded-2xl border border-white/8 bg-zinc-950/80 p-4 transition-colors hover:border-rose-400/40 hover:bg-zinc-900/80">
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="text-sm font-semibold leading-snug text-white group-hover:text-rose-100 sm:text-base">
                                        {{ $m->name }}
                                    </h3>
                                    <span class="shrink-0 rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider ring-1 ring-inset {{ $typeClasses }}">
                                        {{ $typeLabel }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-zinc-400">
                                    {{ optional($m->date)->format('d M Y') ?? '' }}
                                    @if(!empty($m->location))
                                        &bull; {{ $m->location }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center justify-between text-[11px] font-medium uppercase tracking-wide text-zinc-500 group-hover:text-rose-200/80">
                                <span>View Match Report</span>
                                <span aria-hidden="true">&rarr;</span>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if($earned->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-3xl border border-white/8 bg-zinc-950/80 px-6 py-16 text-center">
            <x-badge-icon name="award" class="mb-4 h-12 w-12 text-zinc-600" />
            <p class="text-lg font-semibold text-white/60">No badges earned yet</p>
            <p class="mt-1 text-sm text-zinc-500">Compete in matches to earn your first achievement.</p>
        </div>
    @else

        @php
            $competitionMeta = [
                'prs' => [
                    'label' => 'PRS Badges',
                    'iconColor' => 'text-sky-300/70',
                    'titleColor' => 'text-sky-200/90',
                    'divider' => 'from-transparent via-sky-400/20 to-transparent',
                    'accent' => 'sky',
                ],
                'royal_flush' => [
                    'label' => 'Royal Flush Badges',
                    'iconColor' => 'text-amber-300/70',
                    'titleColor' => 'text-amber-200/90',
                    'divider' => 'from-transparent via-amber-400/20 to-transparent',
                    'accent' => 'amber',
                ],
            ];

            $sectionIcons = ['prs' => 'target', 'royal_flush' => 'crown'];
            $tierOrder = ['featured' => 0, 'elite' => 1, 'milestone' => 2, 'earned' => 3];
        @endphp

        @foreach($byCompetition as $cType => $typeBadges)
            @php
                $meta = $competitionMeta[$cType] ?? [
                    'label' => ucfirst($cType),
                    'iconColor' => 'text-white/50',
                    'titleColor' => 'text-white/80',
                    'divider' => 'from-transparent via-white/8 to-transparent',
                    'accent' => 'white',
                ];
                $sIcon = $sectionIcons[$cType] ?? 'target';

                $sorted = $typeBadges->sortBy(function ($b) use ($badgeConfig, $tierOrder) {
                    $tier = $badgeConfig[$b->achievement->slug]['tier'] ?? 'earned';
                    return ($tierOrder[$tier] ?? 9) * 1000 + ($b->achievement->sort_order ?? 99);
                });
            @endphp

            <section class="mb-14">
                <div class="mb-6 flex items-center gap-3">
                    <div class="grid h-9 w-9 place-items-center rounded-xl border border-white/8 bg-white/4">
                        <x-badge-icon :name="$sIcon" class="h-4 w-4 {{ $meta['iconColor'] }}" />
                    </div>
                    <h2 class="text-lg font-semibold tracking-tight {{ $meta['titleColor'] }} sm:text-xl">{{ $meta['label'] }}</h2>
                    <span class="ml-auto rounded-full border border-white/8 bg-white/4 px-3 py-0.5 text-[11px] tabular-nums text-zinc-400">{{ $typeBadges->count() }} earned</span>
                </div>
                <div class="mb-8 h-px bg-gradient-to-r {{ $meta['divider'] }}"></div>

                <div class="space-y-4">
                    @foreach($sorted as $userBadge)
                        @php
                            $a = $userBadge->achievement;
                            $cfg = $badgeConfig[$a->slug] ?? [];
                            $icon = $cfg['icon'] ?? 'target';
                            $tier = $cfg['tier'] ?? 'earned';
                            $isFeatured = $tier === 'featured';
                            $match = $userBadge->match;
                            $stage = $userBadge->stage;
                            $md = $userBadge->metadata ?? [];
                        @endphp
                        <x-badge-card-earned
                            :achievement="$a"
                            :icon="$icon"
                            :tier="$tier"
                            :family="$cType"
                            :count="1"
                            :lastAwarded="$userBadge->awarded_at"
                            :matchName="$match?->name"
                            :matchLocation="$match?->location"
                            :stageName="$stage?->label"
                            :metadata="$md"
                        />
                    @endforeach
                </div>
            </section>
        @endforeach

    @endif

    <footer class="mt-16 border-t border-white/6 pt-6 text-center">
        <a href="{{ route('badges.preview') }}" class="text-xs font-medium text-zinc-400 transition-colors hover:text-white hover:underline">
            View all available badges &rarr;
        </a>
    </footer>
</div>
