@props([
    'match',
    'organization' => null,
])

{{--
    Backwards-compatibility alias.

    The old four-tab match hub (Overview / Configuration / Squadding /
    Scoreboard / Side Bet) is replaced by the consolidated five-tab
    Match Control Center nav (Overview / Setup / Squadding / Scoring /
    Reports). To avoid touching every page that still references
    <x-match-hub-tabs>, this component now just delegates to the new
    <x-match-control-tabs>. Pages that should host the full Match
    Control chrome (header + lifecycle stepper + tabs) should use
    <x-match-control-shell> directly instead of this component — but
    auxiliary pages (side-bet flows, public-facing scoreboard) keep
    using this alias so they at least get the new tab nav consistent
    with the rest of the app.
--}}
<x-match-control-tabs :match="$match" :organization="$organization" />
