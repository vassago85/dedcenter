@php
    /**
     * PDF/HTML report header strip.
     *
     * The organizing body (the match's Organization) is the HERO of the
     * report — their logo anchors the left of the strip. DeadCenter is
     * credited as the platform that published the report, demoted to a
     * small "Published on" mini-lockup on the right. When the match has
     * no organization logo on file we gracefully fall back to DeadCenter
     * as the hero so the report still looks finished.
     *
     * The Royal Flush playing-card flourish that used to sit on the
     * right was removed — for Royal Flush matches the organization logo
     * IS the Royal Flush logo, so the flourish was redundant.
     *
     * @var \App\Models\ShootingMatch $match
     * @var string|null $subtitle  Optional section label (e.g. "Full Match Report", "Shooter Report")
     */
    $subtitle = $subtitle ?? null;
    $orgName  = $match->organization?->name;
    $orgLogo  = $match->organization?->logoUrl();
@endphp
<div class="pdf-header">
    <table class="pdf-header-table">
        <tr>
            <td class="brand-left">
                @if($orgLogo)
                    <img class="brand-hero-logo" src="{{ $orgLogo }}" alt="{{ $orgName }}">
                @else
                    <table class="brand-inner"><tr>
                        <td class="brand-mark">
                            <svg width="28" height="28" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
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
                @endif
            </td>
            <td class="brand-center">
                @if($subtitle)
                    <div class="eyebrow">{{ $subtitle }}</div>
                @endif
                <div class="match-name">{{ $match->name }}</div>
                <div class="match-meta">
                    {{ $match->date?->format('d M Y') }}
                    @if($match->location)<span class="dot">·</span>{{ $match->location }}@endif
                    @if($orgName)<span class="dot">·</span>{{ $orgName }}@endif
                </div>
            </td>
            <td class="brand-right">
                {{-- "Published on DeadCenter" mini-lockup. Only rendered when
                     the hero slot is occupied by the organization logo;
                     otherwise we'd be stamping DeadCenter twice. --}}
                @if($orgLogo)
                    <table class="published-by"><tr>
                        <td class="pb-label">Published on</td>
                        <td class="pb-mark">
                            <svg width="14" height="14" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="2" fill="#e10600"/>
                                <line x1="12" y1="3.5" x2="12" y2="7.5" stroke="#e10600" stroke-width="1.6" stroke-linecap="round"/>
                                <line x1="12" y1="16.5" x2="12" y2="20.5" stroke="#e10600" stroke-width="1.6" stroke-linecap="round"/>
                                <line x1="3.5" y1="12" x2="7.5" y2="12" stroke="#e10600" stroke-width="1.6" stroke-linecap="round"/>
                                <line x1="16.5" y1="12" x2="20.5" y2="12" stroke="#e10600" stroke-width="1.6" stroke-linecap="round"/>
                            </svg>
                        </td>
                        <td class="pb-word">
                            <span class="dead">DEAD</span><span class="center">CENTER</span>
                        </td>
                    </tr></table>
                @endif
            </td>
        </tr>
    </table>
</div>
