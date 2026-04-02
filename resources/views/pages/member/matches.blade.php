<?php

use App\Enums\MatchStatus;
use App\Models\ShootingMatch;
use Livewire\Volt\Component;

new class extends Component {
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

<x-layouts.app title="My Matches">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">My Matches</h1>
            <div class="flex items-center gap-3">
                <a href="{{ route('notifications') }}" class="relative flex items-center gap-1.5 text-sm text-muted hover:text-primary transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                    @if($unreadCount > 0)
                        <span class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-bold text-white">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                    @endif
                </a>
                <a href="{{ route('settings.notifications') }}" class="text-sm text-muted hover:text-primary transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
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
</x-layouts.app>
