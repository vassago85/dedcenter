@props(['size' => 'md', 'class' => ''])

@php
    $sizes = [
        'sm' => ['icon' => 'h-4 w-4', 'text' => 'text-sm'],
        'md' => ['icon' => 'h-5 w-5', 'text' => 'text-lg'],
        'lg' => ['icon' => 'h-7 w-7', 'text' => 'text-2xl'],
        'xl' => ['icon' => 'h-10 w-10', 'text' => 'text-4xl'],
    ];
    $s = $sizes[$size] ?? $sizes['md'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-2 {$class}"]) }}>
    <svg class="{{ $s['icon'] }} text-red-500" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="12" r="2.5" fill="currentColor"/>
        <line x1="12" y1="3" x2="12" y2="7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="12" y1="17" x2="12" y2="21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="3" y1="12" x2="7" y2="12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="17" y1="12" x2="21" y2="12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
    </svg>
    <span class="{{ $s['text'] }} font-bold tracking-tight">
        <span class="text-slate-900 dark:text-white/90">DEAD</span><span class="text-red-500">CENTER</span>
    </span>
</span>
