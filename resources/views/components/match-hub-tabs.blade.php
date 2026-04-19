@props([
    'match',
    'organization' => null,
])

{{-- Persistent tab strip for the Match Hub. Renders the same five tabs
     on Overview / Configuration / Squadding / Scoreboard / Side Bet so
     an MD or admin never loses their way after clicking one tab.

     The component auto-detects whether the user is browsing via the
     org.* or admin.* route group and picks the correct route names.
     It also highlights the active tab based on the current route name. --}}

@php
    $currentRoute = request()->route()?->getName() ?? '';
    $isAdminContext = str_starts_with($currentRoute, 'admin.matches.') || $organization === null;

    if ($isAdminContext && $organization === null) {
        $overviewHref = route('admin.matches.hub', $match);
        $configHref = route('admin.matches.edit', $match);
        $squaddingHref = route('admin.matches.squadding', $match);
        $sideBetHref = $match->side_bet_enabled ? route('admin.matches.side-bet-report', $match) : null;
    } else {
        $overviewHref = route('org.matches.hub', [$organization, $match]);
        $configHref = route('org.matches.edit', [$organization, $match]);
        $squaddingHref = route('org.matches.squadding', [$organization, $match]);
        $sideBetHref = $match->side_bet_enabled ? route('org.matches.side-bet', [$organization, $match]) : null;
    }

    $scoreboardHref = route('scoreboard', $match);

    $isOverview = in_array($currentRoute, ['org.matches.hub', 'admin.matches.hub'], true);
    $isConfig = in_array($currentRoute, ['org.matches.edit', 'admin.matches.edit', 'org.matches.create', 'admin.matches.create'], true);
    $isSquadding = in_array($currentRoute, ['org.matches.squadding', 'admin.matches.squadding'], true);
    $isScoreboard = $currentRoute === 'scoreboard';
    $isSideBet = in_array($currentRoute, [
        'org.matches.side-bet',
        'org.matches.side-bet-report',
        'admin.matches.side-bet-report',
    ], true);

    $activeClasses = 'border-accent bg-surface text-primary';
    $inactiveClasses = 'border-transparent text-muted hover:text-primary hover:border-border';
    $baseClasses = 'rounded-t-lg border-b-2 px-4 py-2 text-sm font-semibold whitespace-nowrap transition-colors';
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2 border-b border-border pb-0 overflow-x-auto']) }}>
    <a href="{{ $overviewHref }}" wire:navigate
       class="{{ $baseClasses }} {{ $isOverview ? $activeClasses : $inactiveClasses }}">
        Overview
    </a>
    <a href="{{ $configHref }}" wire:navigate
       class="{{ $baseClasses }} {{ $isConfig ? $activeClasses : $inactiveClasses }}">
        Configuration
    </a>
    <a href="{{ $squaddingHref }}" wire:navigate
       class="{{ $baseClasses }} {{ $isSquadding ? $activeClasses : $inactiveClasses }}">
        Squadding
    </a>
    <a href="{{ $scoreboardHref }}" wire:navigate
       class="{{ $baseClasses }} {{ $isScoreboard ? $activeClasses : $inactiveClasses }}">
        Scoreboard
    </a>
    @if($sideBetHref)
        <a href="{{ $sideBetHref }}" wire:navigate
           class="{{ $baseClasses }} {{ $isSideBet ? $activeClasses : $inactiveClasses }}">
            Side Bet
        </a>
    @endif
</div>
