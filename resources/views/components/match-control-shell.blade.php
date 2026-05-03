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

    {{--
        "Complete Match" password challenge.
        ─────────────────────────────────────
        Hosted at the shell so EVERY page in the Match Control Center
        gets the same password gate the moment a Completed transition
        is requested — no matter whether the request came from the
        lifecycle stepper, the header primary CTA, or a future surface
        we haven't built yet. The trait `HandlesMatchLifecycleTransitions`
        binds the form (`completeMatchPassword`) and the submit handler
        (`confirmCompleteMatch`); on a mismatched password the modal
        stays open with an inline error.

        Wrapped in `@auth` because anonymous users have no password
        to validate against and shouldn't be hitting these admin
        surfaces in the first place — defensive, not load-bearing.
    --}}
    @auth
    <flux:modal name="complete-match-password" class="md:max-w-md">
        <div class="space-y-5">
            <div class="space-y-1">
                <flux:heading size="lg">Confirm Match Completion</flux:heading>
                <flux:text class="text-sm text-muted">
                    Completing this match locks scores, awards achievements and fires
                    post-match emails. Enter your password to confirm.
                </flux:text>
            </div>

            <form wire:submit.prevent="confirmCompleteMatch" class="space-y-4">
                <flux:field>
                    <flux:label>Your password</flux:label>
                    <flux:input
                        type="password"
                        wire:model="completeMatchPassword"
                        autocomplete="current-password"
                        autofocus
                        required
                    />
                    @if($errors->has('completeMatchPassword'))
                        <flux:error name="completeMatchPassword" />
                    @endif
                </flux:field>

                {{-- Inline error driven by the trait property, since the --}}
                {{-- trait deliberately doesn't push to the validator — we --}}
                {{-- never want the password to round-trip through standard --}}
                {{-- validation channels. Alpine reactive expression so the --}}
                {{-- banner appears the instant the wire response lands, --}}
                {{-- without a full re-render flicker. --}}
                <div
                    x-data
                    x-show="$wire.completeMatchPasswordError"
                    x-cloak
                    class="rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-300"
                >
                    <span x-text="$wire.completeMatchPasswordError"></span>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover">
                        Complete Match
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
    @endauth
</div>
