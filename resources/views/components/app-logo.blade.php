@props(['size' => 'md', 'variant' => 'dark', 'class' => ''])

@php
    $sizes = [
        'sm' => ['icon' => 'h-4 w-4', 'text' => 'text-sm'],
        'md' => ['icon' => 'h-5 w-5', 'text' => 'text-lg'],
        'lg' => ['icon' => 'h-7 w-7', 'text' => 'text-2xl'],
        'xl' => ['icon' => 'h-10 w-10', 'text' => 'text-4xl'],
    ];
    $s = $sizes[$size] ?? $sizes['md'];
    $isDark = $variant === 'dark';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-2 {$class}"]) }}>
    <svg class="{{ $s['icon'] }}" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="12" r="2" fill="var(--logo-red)"/>
        <line x1="12" y1="4" x2="12" y2="8" stroke="var(--logo-red)" stroke-width="2" stroke-linecap="round"/>
        <line x1="12" y1="16" x2="12" y2="20" stroke="var(--logo-red)" stroke-width="2" stroke-linecap="round"/>
        <line x1="4" y1="12" x2="8" y2="12" stroke="var(--logo-red)" stroke-width="2" stroke-linecap="round"/>
        <line x1="16" y1="12" x2="20" y2="12" stroke="var(--logo-red)" stroke-width="2" stroke-linecap="round"/>
    </svg>
    <span class="{{ $s['text'] }} font-bold tracking-tight">
        <span style="color: {{ $isDark ? 'var(--logo-white)' : 'var(--logo-black)' }}">DEAD</span><span style="color: var(--logo-red)">CENTER</span>
    </span>
</span>
