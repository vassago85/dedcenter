<?php

use App\Enums\MatchStatus;
use App\Models\ShootingMatch;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('My Matches — DeadCenter')]
    class extends Component {
    public string $activeTab = 'upcoming';

    public function with(): array
    {
        $userId = auth()->id();

        $myMatchIds = \App\Models\MatchRegistration::where('user_id', $userId)
            ->pluck('match_id');

        $liveMatches = ShootingMatch::whereIn('id', $myMatchIds)
            ->activeLiveToday()
            ->withCount('shooters')
            ->latest('date')
            ->get();

        $upcomingMatches = ShootingMatch::whereIn('id', $myMatchIds)
            ->whereIn('status', [
                MatchStatus::PreRegistration,
                MatchStatus::RegistrationOpen,
                MatchStatus::RegistrationClosed,
                MatchStatus::SquaddingOpen,
                MatchStatus::SquaddingClosed,
            ])
            ->withCount('shooters')
            ->orderBy('date')
            ->get();

        $recentMatches = ShootingMatch::whereIn('id', $myMatchIds)
            ->where('status', MatchStatus::Completed)
            ->withCount('shooters')
            ->latest('date')
            ->take(10)
            ->get();

        $browseMatches = ShootingMatch::whereNotIn('id', $myMatchIds)
            ->whereIn('status', [
                MatchStatus::PreRegistration,
                MatchStatus::RegistrationOpen,
            ])
            ->withCount('shooters')
            ->orderBy('date')
            ->get();

        $unreadCount = auth()->user()->unreadNotifications()->count();

        return compact('liveMatches', 'upcomingMatches', 'recentMatches', 'browseMatches', 'unreadCount');
    }
}; ?>

<div>
    <div class="mx-auto max-w-3xl px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">My Matches</h1>
            <div class="flex items-center gap-3">
                <a href="{{ route('notifications') }}" class="relative flex items-center gap-1.5 text-sm text-muted hover:text-primary transition-colors">
                    <x-icon name="bell" class="h-5 w-5" />
                    @if($unreadCount > 0)
                        <span class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-bold text-white">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                    @endif
                </a>
                <a href="{{ route('settings.notifications') }}" class="text-sm text-muted hover:text-primary transition-colors">
                    <x-icon name="settings" class="h-5 w-5" />
                </a>
            </div>
        </div>

        {{-- Live Now --}}
        @if($liveMatches->isNotEmpty())
            <section class="mb-8">
                <h2 class="mb-3 flex items-center gap-2 text-lg font-semibold">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-red-600"></span>
                    </span>
                    Live Now
                </h2>
                <div class="space-y-3">
                    @foreach($liveMatches as $match)
                        <x-match-card :match="$match" />
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Tabs --}}
        <div class="mb-6 flex border-b border-border">
            @foreach(['upcoming' => 'Upcoming', 'recent' => 'Recent Results', 'browse' => 'Find Matches'] as $tab => $label)
                <button
                    wire:click="$set('activeTab', '{{ $tab }}')"
                    class="relative px-4 py-2.5 text-sm font-medium transition-colors {{ $activeTab === $tab ? 'text-primary' : 'text-muted hover:text-primary' }}"
                >
                    {{ $label }}
                    @if($tab === 'upcoming' && $upcomingMatches->isNotEmpty())
                        <span class="ml-1 rounded-full bg-red-600/20 px-1.5 py-0.5 text-[10px] text-red-500">{{ $upcomingMatches->count() }}</span>
                    @endif
                    @if($activeTab === $tab)
                        <span class="absolute inset-x-0 -bottom-px h-0.5 bg-red-600"></span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- Upcoming --}}
        @if($activeTab === 'upcoming')
            @if($upcomingMatches->isEmpty())
                <div class="rounded-xl border border-border bg-surface p-8 text-center">
                    <p class="text-sm text-muted">No upcoming matches. Browse available matches to register.</p>
                    <button wire:click="$set('activeTab', 'browse')" class="mt-3 text-sm font-medium text-red-500 hover:text-red-400 transition-colors">
                        Find Matches &rarr;
                    </button>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($upcomingMatches as $match)
                        <x-match-card :match="$match" />
                    @endforeach
                </div>
            @endif
        @endif

        {{-- Recent --}}
        @if($activeTab === 'recent')
            @if($recentMatches->isEmpty())
                <div class="rounded-xl border border-border bg-surface p-8 text-center">
                    <p class="text-sm text-muted">No completed matches yet.</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($recentMatches as $match)
                        <x-match-card :match="$match" />
                    @endforeach
                </div>
            @endif
        @endif

        {{-- Browse --}}
        @if($activeTab === 'browse')
            @if($browseMatches->isEmpty())
                <div class="rounded-xl border border-border bg-surface p-8 text-center">
                    <p class="text-sm text-muted">No matches currently accepting registrations.</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($browseMatches as $match)
                        <x-match-card :match="$match" />
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</div>
