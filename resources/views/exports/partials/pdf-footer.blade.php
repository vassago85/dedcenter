@php
    /** @var \App\Models\ShootingMatch $match */
    $generatedAt = $generatedAt ?? now();
@endphp
<div class="pdf-footer">
    <strong>DEADCENTER</strong>
    <span class="sep">/</span>
    {{ $match->organization?->name ?? 'Match Report' }}
    <span class="sep">/</span>
    Generated {{ $generatedAt->format('d M Y H:i') }}
    <span class="sep">/</span>
    deadcenter.co.za
</div>
