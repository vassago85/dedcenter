@php
    $flashes = collect(['success' => 'success', 'status' => null, 'error' => 'danger'])
        ->filter(fn ($variant, $key) => session()->has($key))
        ->map(fn ($variant, $key) => [
            'duration' => 6000,
            'slots' => ['text' => session($key)],
            'dataset' => array_filter(['variant' => $variant]),
        ])
        ->values();
@endphp

@if ($flashes->isNotEmpty())
    <script>
        document.addEventListener('alpine:initialized', () => {
            @js($flashes).forEach((detail) => document.dispatchEvent(new CustomEvent('toast-show', { detail })));
        }, { once: true });
    </script>
@endif
