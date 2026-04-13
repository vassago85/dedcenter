@props([
    'placement',
    'variant' => 'block',
])

@php
    if (! (bool) \App\Models\Setting::get('advertising_enabled', false)) {
        return;
    }

    $assignment = app(\App\Services\PortalAdResolver::class)->resolveSiteWide($placement);
@endphp

@if($assignment)
    <div {{ $attributes->class('landing-ad-slot') }}>
        <x-sponsor-assignment :assignment="$assignment" :variant="$variant" />
    </div>
@endif
