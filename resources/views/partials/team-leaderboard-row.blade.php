@php
    /**
     * Single team row for the per-match team leaderboard.
     *
     * Expected vars:
     *   $entry — object {team, members, total_score, member_count, category}
     *   $index — 0-based rank within the current group (Overall or per-category)
     *   $isPrs — bool, used to switch number formatting (PRS = ints, others = 1dp)
     */
    $medal = match($index) {
        0 => 'text-amber-400',
        1 => 'text-zinc-300',
        2 => 'text-amber-700',
        default => 'text-muted',
    };
@endphp

<div class="rounded-2xl border border-border bg-surface overflow-hidden" x-data="{ open: false }">
    <button @click="open = !open" class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-surface-2/50 transition-colors sm:px-6 sm:py-4">
        <div class="flex items-center gap-3 min-w-0">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-surface-2 text-sm font-black {{ $medal }} sm:h-10 sm:w-10 sm:text-base">{{ $index + 1 }}</span>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <p class="truncate text-sm font-bold text-primary sm:text-base">{{ $entry->team->name }}</p>
                    @if($entry->category)
                        <span class="inline-flex shrink-0 items-center rounded bg-sky-500/10 px-1.5 py-0.5 text-[10px] font-medium text-sky-300 ring-1 ring-inset ring-sky-500/20">
                            {{ $entry->category }}
                        </span>
                    @endif
                </div>
                <p class="text-xs text-muted">{{ $entry->member_count }} {{ Str::plural('member', $entry->member_count) }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-lg font-black text-amber-400 tabular-nums sm:text-2xl">{{ $isPrs ? $entry->total_score : number_format($entry->total_score, 1) }}</span>
            <x-icon name="chevron-down" x-bind:class="open && 'rotate-180'" class="h-5 w-5 text-muted transition-transform" />
        </div>
    </button>
    <div x-show="open" x-collapse>
        <div class="border-t border-border px-4 py-3 sm:px-6">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-muted"><th class="pb-1 font-medium">Shooter</th><th class="pb-1 font-medium text-right">Score</th></tr>
                </thead>
                <tbody class="divide-y divide-border/30">
                    @foreach($entry->members as $member)
                        <tr>
                            <td class="py-1.5 text-secondary">{{ $member->name }}</td>
                            <td class="py-1.5 text-right font-bold tabular-nums text-primary">{{ $isPrs ? $member->score : number_format($member->score, 1) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
