@php
    /**
     * PDF header strip — DeadCenter brand left, match meta center, Royal Flush brand right (RF matches only).
     *
     * @var \App\Models\ShootingMatch $match
     * @var string|null $subtitle  Optional section label (e.g. "Full Match Report", "Shooter Report")
     */
    $subtitle = $subtitle ?? null;
    $isRf = (bool) ($match->royal_flush_enabled ?? false);
@endphp
<div class="pdf-header">
    <table class="pdf-header-table">
        <tr>
            <td class="brand-left">
                <table class="brand-inner"><tr>
                    <td class="brand-mark">
                        <svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="2" fill="#e10600"/>
                            <line x1="12" y1="3.5" x2="12" y2="7.5" stroke="#e10600" stroke-width="1.8" stroke-linecap="round"/>
                            <line x1="12" y1="16.5" x2="12" y2="20.5" stroke="#e10600" stroke-width="1.8" stroke-linecap="round"/>
                            <line x1="3.5" y1="12" x2="7.5" y2="12" stroke="#e10600" stroke-width="1.8" stroke-linecap="round"/>
                            <line x1="16.5" y1="12" x2="20.5" y2="12" stroke="#e10600" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </td>
                    <td class="brand-word">
                        <span class="dead">DEAD</span><span class="center">CENTER</span>
                    </td>
                </tr></table>
            </td>
            <td class="brand-center">
                @if($subtitle)
                    <div class="eyebrow">{{ $subtitle }}</div>
                @endif
                <div class="match-name">{{ $match->name }}</div>
                <div class="match-meta">
                    {{ $match->date?->format('d M Y') }}
                    @if($match->location)<span class="dot">·</span>{{ $match->location }}@endif
                    @if($match->organization)<span class="dot">·</span>{{ $match->organization->name }}@endif
                </div>
            </td>
            <td class="brand-right">
                @if($isRf)
                    <table class="rf-mark"><tr>
                        <td class="rf-suits">
                            <svg width="46" height="20" viewBox="0 0 46 20" xmlns="http://www.w3.org/2000/svg">
                                {{-- Spade — light so it reads on the dark header strip --}}
                                <path d="M6 2 C6 2 11 6 11 10 C11 12 9 13 8 13 C7.4 13 7 12.7 6.7 12.3 L7.5 16 L4.5 16 L5.3 12.3 C5 12.7 4.6 13 4 13 C3 13 1 12 1 10 C1 6 6 2 6 2 Z" fill="#f8fafc"/>
                                {{-- Heart --}}
                                <path d="M17 5 C17 3 15 2 14 2 C12.5 2 11.5 3 11.5 4.5 C11.5 3 10.5 2 9 2 C8 2 6 3 6 5 C6 8 11.5 12 11.5 12 C11.5 12 17 8 17 5 Z" fill="#e10600" transform="translate(6 0)"/>
                                {{-- Diamond --}}
                                <path d="M29 2 L33 9 L29 16 L25 9 Z" fill="#e10600"/>
                                {{-- Club — light so it reads on the dark header strip --}}
                                <path d="M41 3 C42.5 3 44 4.2 44 6 C44 7.2 43.3 8 42.5 8.3 C43.5 8.5 44.5 9.3 44.5 10.5 C44.5 12 43 13 41.5 13 C40.8 13 40.2 12.8 39.8 12.4 L40.7 16 L37.3 16 L38.2 12.4 C37.8 12.8 37.2 13 36.5 13 C35 13 33.5 12 33.5 10.5 C33.5 9.3 34.5 8.5 35.5 8.3 C34.7 8 34 7.2 34 6 C34 4.2 35.5 3 37 3 C38.3 3 39.2 3.8 39.5 4.8 C39.8 3.8 40.7 3 41 3 Z M38.5 5 C38.2 5 37.8 5.3 37.8 5.8 C37.8 6.3 38.2 6.6 38.5 6.6 C38.8 6.6 39.2 6.3 39.2 5.8 C39.2 5.3 38.8 5 38.5 5 Z" fill="#f8fafc"/>
                            </svg>
                        </td>
                        <td class="rf-word">
                            <div class="rf-line1">ROYAL</div>
                            <div class="rf-line2">FLUSH</div>
                        </td>
                    </tr></table>
                @endif
            </td>
        </tr>
    </table>
</div>
