@props([
    'match',
    'organization' => null,
])

@php
    use App\Enums\MatchStatus;

    /*
    |--------------------------------------------------------------------------
    | Match Control Center — primary tab navigation.
    |--------------------------------------------------------------------------
    | Replaces the old `<x-match-hub-tabs>` (Overview / Configuration /
    | Squadding / Scoreboard / Side Bet) with the consolidated five-tab
    | structure called out in the Match Control Center spec:
    |
    |   Overview · Setup · Squadding · Scoring · Reports
    |
    | URL strategy:
    |   - Overview  → existing org.matches.hub / admin.matches.hub
    |   - Setup     → existing org.matches.edit / admin.matches.edit
    |                 (URL kept identical so MD bookmarks survive — only
    |                 the tab label changed from "Configuration" to "Setup")
    |   - Squadding → existing org.matches.squadding / admin.matches.squadding
    |   - Scoring   → NEW org.matches.scoring / admin.matches.scoring
    |   - Reports   → NEW org.matches.reports / admin.matches.reports
    |
    | Lifecycle-aware emphasis:
    |   The "primary focus" tab for the current lifecycle stage gets a
    |   subtle accent dot next to its label. Pre-Active stages point at
    |   Setup/Squadding; Active points at Scoring; Completed points at
    |   Reports. The dot is a hint, not a hard nav rule — every tab
    |   stays clickable so an MD can jump anywhere they need to.
    */
    $currentRoute = request()->route()?->getName() ?? '';
    $isAdminContext = str_starts_with($currentRoute, 'admin.matches.') || $organization === null;

    if ($isAdminContext && $organization === null) {
        $tabs = [
            'overview'  => ['label' => 'Overview',  'icon' => 'layout-dashboard', 'href' => route('admin.matches.hub', $match),       'active' => $currentRoute === 'admin.matches.hub'],
            'setup'     => ['label' => 'Setup',     'icon' => 'settings',         'href' => route('admin.matches.edit', $match),      'active' => in_array($currentRoute, ['admin.matches.edit', 'admin.matches.create'], true)],
            'squadding' => ['label' => 'Squadding', 'icon' => 'users',            'href' => route('admin.matches.squadding', $match), 'active' => $currentRoute === 'admin.matches.squadding'],
            'scoring'   => ['label' => 'Scoring',   'icon' => 'target',           'href' => route('admin.matches.scoring', $match),   'active' => $currentRoute === 'admin.matches.scoring'],
            'reports'   => ['label' => 'Reports',   'icon' => 'file-text',        'href' => route('admin.matches.reports', $match),   'active' => $currentRoute === 'admin.matches.reports'],
        ];
    } else {
        $tabs = [
            'overview'  => ['label' => 'Overview',  'icon' => 'layout-dashboard', 'href' => route('org.matches.hub', [$organization, $match]),       'active' => $currentRoute === 'org.matches.hub'],
            'setup'     => ['label' => 'Setup',     'icon' => 'settings',         'href' => route('org.matches.edit', [$organization, $match]),      'active' => in_array($currentRoute, ['org.matches.edit', 'org.matches.create'], true)],
            'squadding' => ['label' => 'Squadding', 'icon' => 'users',            'href' => route('org.matches.squadding', [$organization, $match]), 'active' => $currentRoute === 'org.matches.squadding'],
            'scoring'   => ['label' => 'Scoring',   'icon' => 'target',           'href' => route('org.matches.scoring', [$organization, $match]),   'active' => $currentRoute === 'org.matches.scoring'],
            'reports'   => ['label' => 'Reports',   'icon' => 'file-text',        'href' => route('org.matches.reports', [$organization, $match]),   'active' => $currentRoute === 'org.matches.reports'],
        ];
    }

    // Map current lifecycle status → recommended tab. The dot on that
    // tab's label is purely advisory — every tab stays navigable. Kept
    // as a single match expression so the policy is one place to edit.
    $primaryTab = match ($match->status) {
        MatchStatus::Draft, MatchStatus::PreRegistration, MatchStatus::RegistrationOpen => 'setup',
        MatchStatus::RegistrationClosed, MatchStatus::SquaddingOpen, MatchStatus::SquaddingClosed => 'squadding',
        MatchStatus::Ready, MatchStatus::Active => 'scoring',
        MatchStatus::Completed => 'reports',
    };
@endphp

<nav
    {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-1 border-b border-border overflow-x-auto']) }}
    aria-label="Match sections"
>
    @foreach($tabs as $key => $tab)
        @php
            $base = 'group inline-flex items-center gap-2 rounded-t-lg border-b-2 px-3.5 py-2.5 text-sm font-semibold whitespace-nowrap transition-colors';
            $state = $tab['active']
                ? 'border-accent text-primary bg-surface'
                : 'border-transparent text-muted hover:text-primary hover:border-border';
        @endphp
        <a href="{{ $tab['href'] }}" wire:navigate class="{{ $base }} {{ $state }}">
            <x-icon name="{{ $tab['icon'] }}" class="h-4 w-4 {{ $tab['active'] ? 'text-accent' : 'text-muted group-hover:text-primary' }}" />
            <span>{{ $tab['label'] }}</span>
            @if($key === $primaryTab && ! $tab['active'])
                {{-- Lifecycle-recommended tab indicator. Tiny accent --}}
                {{-- pulse so the MD's eye is drawn to "what's next" --}}
                {{-- without stealing focus from where they currently are. --}}
                <span class="relative flex h-1.5 w-1.5" aria-label="Recommended next step">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent opacity-60"></span>
                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-accent"></span>
                </span>
            @endif
        </a>
    @endforeach
</nav>
