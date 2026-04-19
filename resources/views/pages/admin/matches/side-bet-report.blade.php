<?php

use App\Models\ShootingMatch;
use App\Services\SideBetStandingsService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    class extends Component {
    public ShootingMatch $match;

    public function with(): array
    {
        if (!$this->match->side_bet_enabled || !$this->match->royal_flush_enabled) {
            return ['entries' => collect(), 'enabled' => false, 'gong_labels' => []];
        }

        $result = app(SideBetStandingsService::class)->build($this->match);

        return [
            'entries' => collect($result['entries']),
            'enabled' => true,
            'gong_labels' => $result['gong_labels'],
        ];
    }
}; ?>

<x-page-shell>
    <x-match-hub-tabs :match="$match" />

    <x-app-page-header
        eyebrow="Royal Flush · Side Bet"
        title="Side Bet Report"
        :subtitle="$match->name.' — '.($match->date?->format('d M Y') ?? '')"
        :crumbs="[
            ['label' => 'Matches', 'href' => route('admin.matches.index')],
            ['label' => $match->name, 'href' => route('admin.matches.hub', $match)],
            ['label' => 'Side Bet Report'],
        ]"
    >
        <x-slot:actions>
            <button
                type="button"
                onclick="window.print()"
                class="inline-flex items-center gap-2 rounded-lg border border-border bg-surface px-3.5 py-2 text-body font-semibold text-primary transition-colors hover:bg-surface-2 print:hidden"
            >
                <x-icon name="printer" class="h-4 w-4" />
                Print Report
            </button>
        </x-slot:actions>
    </x-app-page-header>

    <x-panel tone="warning" title="Side Bet tiebreaker rules" subtitle="Applied in order, cascading down through every gong size.">
        <ol class="ml-5 list-decimal space-y-1.5 text-body text-secondary leading-relaxed">
            <li>Rank by count of <strong class="text-primary">smallest-gong</strong> (highest-value) hits.</li>
            <li>If tied, the shooter who hit the <strong class="text-primary">furthest distance</strong> at that gong ranks higher; then second-furthest; etc.</li>
            <li>If still tied, drop to the <strong class="text-primary">next gong size</strong> and repeat (count → distances).</li>
            <li>Cascade continues all the way down through every gong size.</li>
        </ol>
    </x-panel>

    @if(!$enabled)
        <x-panel>
            <x-empty-state
                title="Side Bet is not enabled"
                description="Enable Royal Flush and Side Bet in the match settings first."
            >
                <x-slot:icon>
                    <x-icon name="info" class="h-6 w-6" />
                </x-slot:icon>
            </x-empty-state>
        </x-panel>
    @elseif($entries->isEmpty())
        <x-panel>
            <x-empty-state
                title="No participants yet"
                description="No shooters registered for the Side Bet yet."
            >
                <x-slot:icon>
                    <x-icon name="users" class="h-6 w-6" />
                </x-slot:icon>
            </x-empty-state>
        </x-panel>
    @else
        <x-data-table :count="$entries->count()">
            <x-slot:columns>
                <th class="w-12 text-center">#</th>
                <th>Shooter</th>
                <th>Squad</th>
                <th class="text-center">Small Gong Hits</th>
                <th>Distances</th>
                <th>Tiebreaker</th>
                <th class="text-right">Match Score</th>
            </x-slot:columns>

            <x-slot:rows>
                @foreach($entries as $entry)
                    @php
                        $rowClass = match($entry['rank']) {
                            1 => 'bg-amber-500/10 [&>td:first-child]:border-l-4 [&>td:first-child]:border-l-amber-400',
                            2 => 'bg-slate-400/5 [&>td:first-child]:border-l-4 [&>td:first-child]:border-l-slate-400',
                            3 => 'bg-orange-500/5 [&>td:first-child]:border-l-4 [&>td:first-child]:border-l-orange-600',
                            default => '',
                        };
                        $rankClass = match($entry['rank']) {
                            1 => 'text-amber-400 font-black',
                            2 => 'text-secondary font-bold',
                            3 => 'text-orange-500 font-bold',
                            default => 'text-muted font-medium',
                        };
                        $reason = $entry['tiebreaker_reason'];
                        $isLast = $entry['rank'] === $entries->count();
                    @endphp
                    <tr class="{{ $rowClass }}">
                        <td class="text-center text-lg {{ $rankClass }}">{{ $entry['rank'] }}</td>
                        <td class="font-semibold text-primary">{{ $entry['name'] }}</td>
                        <td class="text-muted">{{ $entry['squad_name'] }}</td>
                        <td class="text-center text-lg font-bold text-amber-400 tabular-nums">{{ $entry['small_gong_hits'] }}</td>
                        <td class="text-secondary">
                            @if(!empty($entry['distances']))
                                {{ implode('m, ', $entry['distances']) }}m
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-meta text-secondary">
                            @if($isLast)
                                <span class="text-muted">—</span>
                            @elseif($reason)
                                <span class="inline-flex items-center gap-1.5 rounded-md border border-border/60 bg-surface-2/40 px-2 py-1 font-medium">
                                    <x-icon name="trophy" class="h-3.5 w-3.5 text-amber-400" />
                                    {{ $reason }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-right font-bold tabular-nums text-primary">{{ $entry['total_score'] }}</td>
                    </tr>
                @endforeach
            </x-slot:rows>

            <x-slot:footer>
                <p class="text-meta text-muted">
                    Ranked by smallest-gong hits first; ties break on furthest distance at that gong, then cascade down through every gong size.
                    The <strong class="text-secondary">Tiebreaker</strong> column shows the decisive step that placed each shooter above the one directly below them.
                    &bull; {{ $entries->count() }} participants &bull; Generated {{ now()->format('d M Y H:i') }}
                </p>
            </x-slot:footer>
        </x-data-table>
    @endif
</x-page-shell>
