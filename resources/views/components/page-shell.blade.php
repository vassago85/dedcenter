{{--
    x-page-shell — standard page container.

    DeadCenter UX Standard §3D + §4. Wrap every internal page content in
    this so container width, outer padding, and vertical section rhythm
    stay consistent across the platform.

    Usage
    ─────
        <x-page-shell>
            <x-app-page-header title="Matches" ... />
            <section> ... primary content ... </section>
            <section> ... secondary content ... </section>
        </x-page-shell>

    Each direct child `<section>` gets the standard `space-y-section`
    separation via `.dc-section-stack`.

    Props
    ─────
    width    string   'default' | 'narrow' | 'wide'. Default 'default'.
                      - default → ~1280px (most pages)
                      - narrow  → ~896px  (forms, settings, focused reads)
                      - wide    → ~1536px (dense admin tables)
    stack    bool     When true (default) adds vertical rhythm between
                      direct children. Set to false if you need custom layout.
--}}
@props([
    'width' => 'default',
    'stack' => true,
])

@php
    $widthClass = match ($width) {
        'narrow' => 'dc-page dc-page-narrow',
        'wide'   => 'dc-page dc-page-wide',
        default  => 'dc-page',
    };
    $stackClass = $stack ? 'dc-section-stack' : '';
@endphp

<div {{ $attributes->merge(['class' => "{$widthClass} {$stackClass}"]) }}>
    {{ $slot }}
</div>
