@php
    use App\Enums\AlrhaClass;

    $backRoute = $isAdmin
        ? route('admin.matches.scoring', $match)
        : route('org.matches.scoring', [$organization, $match]);
    $printAction = $isAdmin
        ? route('admin.matches.score-cards.print', $match)
        : route('org.matches.score-cards.print', [$organization, $match]);
    $sparesAction = $isAdmin
        ? route('admin.matches.score-cards.print-spares', $match)
        : route('org.matches.score-cards.print-spares', [$organization, $match]);
    $scanRoute = url('/score/'.$match->id.'/score-cards');
@endphp

<x-layouts.app>
    <x-match-control-shell :match="$match" :organization="$organization">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-xl font-bold text-primary sm:text-2xl">ALRHA score cards</h1>
                <p class="mt-1 max-w-2xl text-sm text-muted">
                    Print one card per shooter. Shooters mark Hit or Miss boxes by hand. The match director scans the mark block on a phone, confirms the preview, and the scores import through the normal scoring pipeline.
                </p>
            </div>
            <a href="{{ $backRoute }}" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-medium text-muted transition-colors hover:border-accent hover:text-accent">
                Back to scoring
            </a>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            {{-- ─── Batch print card ─── --}}
            <article class="rounded-2xl border border-border bg-surface p-5 sm:p-6">
                <h2 class="text-base font-semibold text-primary">Print cards</h2>
                <p class="mt-0.5 text-xs text-muted">
                    {{ $printableCount }} eligible {{ \Illuminate\Support\Str::plural('shooter', $printableCount) }}
                    @if($skippedCount > 0)
                        · {{ $skippedCount }} skipped (no ALRHA class set on a dual-class match)
                    @endif
                </p>

                <form method="POST" action="{{ $printAction }}" target="_blank" class="mt-4 space-y-3">
                    @csrf

                    <div>
                        <label for="squad_id" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-muted">Relay</label>
                        <select id="squad_id" name="squad_id" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:outline-none">
                            <option value="">All relays</option>
                            @foreach($squads as $squad)
                                <option value="{{ $squad->id }}">{{ $squad->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if(count($classes) > 1)
                        <div>
                            <label for="class" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-muted">Class</label>
                            <select id="class" name="class" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:outline-none">
                                <option value="">Both classes</option>
                                @foreach($classes as $class)
                                    <option value="{{ $class->value }}">{{ $class->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <button type="submit" class="w-full rounded-lg bg-accent px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-accent-hover">
                        Print selected cards
                    </button>

                    <p class="text-[11px] text-muted">
                        Printing creates a fresh card ID per shooter. Reprinting replaces the previous card; an older card still imports against the layout it was printed with.
                    </p>
                </form>
            </article>

            {{-- ─── Reprint one shooter (walk-ins, mistakes) ─── --}}
            <article class="rounded-2xl border border-border bg-surface p-5 sm:p-6">
                <h2 class="text-base font-semibold text-primary">Reprint for one shooter</h2>
                <p class="mt-0.5 text-xs text-muted">
                    For a walk-in you just added, or a shooter who scribbled on their card. The previous card for that shooter becomes inert — only the newest card scans.
                </p>

                @if(count($shooterOptions) === 0)
                    <p class="mt-4 rounded-lg border border-dashed border-border bg-surface-2/40 px-3 py-4 text-center text-xs text-muted">
                        No eligible shooters yet. Add the shooter in the scoring app first, then come back here.
                    </p>
                @else
                    <form method="POST" action="{{ $printAction }}" target="_blank" class="mt-4 space-y-3">
                        @csrf
                        <div>
                            <label for="shooter_id" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-muted">Shooter</label>
                            <select id="shooter_id" name="shooter_id" required class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:outline-none">
                                <option value="">Pick a shooter…</option>
                                @foreach($shooterOptions as $opt)
                                    <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit" class="w-full rounded-lg border border-accent/40 bg-accent/10 px-4 py-2.5 text-sm font-bold text-accent transition-colors hover:bg-accent/20">
                            Reprint this shooter's card
                        </button>
                    </form>
                @endif
            </article>

            {{-- ─── Blank spare cards (unknown walk-ins) ─── --}}
            <article class="rounded-2xl border border-border bg-surface p-5 sm:p-6">
                <h2 class="text-base font-semibold text-primary">Print blank spares</h2>
                <p class="mt-0.5 text-xs text-muted">
                    Unassigned cards for walk-ins who aren't on the squad list yet. The MD picks the shooter in the scanner before confirming — the card is bound once, permanently, at that moment.
                </p>

                @php
                    $totalSpares = array_sum($spareCounts);
                @endphp
                @if($totalSpares > 0)
                    @php
                        $spareBreakdown = collect($spareCounts)
                            ->map(fn ($n, $cls) => AlrhaClass::from($cls)->label().': '.$n)
                            ->values()
                            ->implode(', ');
                    @endphp
                    <p class="mt-3 rounded-lg border border-amber-500/30 bg-amber-500/5 px-3 py-2 text-xs text-amber-200">
                        {{ $totalSpares }} unclaimed {{ \Illuminate\Support\Str::plural('spare', $totalSpares) }} already in circulation
                        @if($spareBreakdown !== '') ({{ $spareBreakdown }}) @endif.
                    </p>
                @endif

                <form method="POST" action="{{ $sparesAction }}" target="_blank" class="mt-4 space-y-3">
                    @csrf

                    <div>
                        <label for="spare_count" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-muted">How many?</label>
                        <input
                            type="number"
                            id="spare_count"
                            name="count"
                            min="1"
                            max="30"
                            value="5"
                            required
                            class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:outline-none"
                        >
                        <p class="mt-1 text-[10px] text-muted">1–30 per print run.</p>
                    </div>

                    @if($isDualClass)
                        <div>
                            <label for="spare_class" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-muted">Class</label>
                            <select id="spare_class" name="class" required class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:outline-none">
                                <option value="">Pick a class…</option>
                                @foreach($classes as $class)
                                    <option value="{{ $class->value }}">{{ $class->label() }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[10px] text-muted">Dual-class match: Hunters and Varmint have different distances, so spares are class-locked at print.</p>
                        </div>
                    @endif

                    <button type="submit" class="w-full rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-2.5 text-sm font-bold text-amber-300 transition-colors hover:bg-amber-500/20">
                        Print spare cards
                    </button>
                </form>
            </article>

            {{-- ─── Scan card ─── --}}
            <article class="rounded-2xl border border-border bg-surface p-5 sm:p-6">
                <h2 class="text-base font-semibold text-primary">Scan filled cards</h2>
                <p class="mt-0.5 text-xs text-muted">
                    Open the scanning page on the phone that will do the import. Frame the whole scan block in one still photo. You review every shot on screen and confirm before anything is written.
                </p>

                <a href="{{ $scanRoute }}" target="_blank" rel="noopener" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-accent/40 bg-accent/10 px-4 py-2.5 text-sm font-bold text-accent transition-colors hover:bg-accent/20">
                    Open card scanner
                </a>

                <ul class="mt-4 space-y-1.5 text-xs text-muted">
                    <li>Blank shots are left alone — a blank pair never wipes a stored score.</li>
                    <li>Unclear or conflicting marks (both boxes filled) are flagged and must be resolved before confirm is enabled.</li>
                    <li>Cold Bore marks are stored but kept out of the match total.</li>
                </ul>
            </article>
        </div>
    </x-match-control-shell>
</x-layouts.app>
