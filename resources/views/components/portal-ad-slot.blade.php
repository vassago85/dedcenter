@props([
    'organization',
    'placement',
    'variant' => 'block',
])

@php
    if (! (bool) \App\Models\Setting::get('advertising_enabled', false)) {
        return;
    }

    $assignment = app(\App\Services\PortalAdResolver::class)->resolve($organization, $placement);
@endphp

@if($assignment)
    <div {{ $attributes->class('portal-ad-slot') }}>
        <x-sponsor-assignment :assignment="$assignment" :variant="$variant" />
    </div>
@endif
