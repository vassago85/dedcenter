@props([
    'match',
    'organization' => null,
])

{{--
    Match Control Shell — the unified chrome wrapper for every page in
    the Match Control Center (Overview, Setup, Squadding, Scoring,
    Reports). Combines the three foundation pieces into one slot host
    so a Volt page just writes:

        <x-match-control-shell :match="$match" :organization="$organization">
            … tab body …
        </x-match-control-shell>

    Layout (top to bottom, full-width within a 1100-1200px content max):
       1. Match Control Header  — identity, status badge, primary CTA,
                                  secondary quick links.
       2. Match Progress strip  — the lifecycle stepper (clickable when
                                  the parent uses the lifecycle trait).
       3. Match Control Tabs    — the five-tab nav (Overview, Setup,
                                  Squadding, Scoring, Reports).
       4. Slot body             — whatever the page renders inside the
                                  active tab.

    The shell intentionally does NOT impose its own page padding /
    background — those come from the surrounding layout (`x-page-shell`
    or the Volt layout the page inherits). The shell is just the chrome
    that says "you're inside a match control center", consistent across
    every page so the MD never has to reorient between tabs.
--}}

<div {{ $attributes->merge(['class' => 'mx-auto w-full max-w-[1200px] space-y-4 sm:space-y-5']) }}>
    <x-match-control-header :match="$match" :organization="$organization" />

    <x-match-progress :match="$match" />

    <x-match-control-tabs :match="$match" :organization="$organization" />

    <div>
        {{ $slot }}
    </div>
</div>
