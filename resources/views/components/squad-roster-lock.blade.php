{{--
    Shown once a match is Ready. Bulk squad changes stay visible and
    disabled. Individual moves stay available until the match is completed.
--}}
@props(['match', 'scoringUrl' => null])

@if($match && $match->bulkSquadEditsLocked())
    <section class="rounded-xl border border-border bg-surface px-4 py-3">
        <div class="flex items-start gap-3">
            <x-icon name="lock" class="mt-0.5 h-4 w-4 shrink-0 text-secondary" />
            <div class="min-w-0 space-y-1">
                <p class="text-sm font-semibold text-primary">{{ $match->bulkSquadEditRefusal() }}</p>
                @if($match->individualSquadEditsLocked())
                    <p class="text-sm text-secondary">{{ $match->individualSquadEditRefusal() }}</p>
                    @if($scoringUrl)
                        <a href="{{ $scoringUrl }}" wire:navigate class="inline-flex items-center gap-1 text-xs font-semibold text-accent hover:underline">
                            Open Scoring
                            <x-icon name="arrow-right" class="h-3 w-3" />
                        </a>
                    @endif
                @else
                    <p class="text-sm text-secondary">You can still move a shooter between squads.</p>
                @endif
            </div>
        </div>
    </section>
@endif
