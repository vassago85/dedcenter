@blaze(fold: true)

@php $srOnly = $srOnly ??= $attributes->pluck('sr-only'); @endphp

@props([
    'badge' => null,
    'aside' => null,
    'trailing' => null,
    'srOnly' => null,
])

@php
    $classes = Flux::classes()
        ->add('inline-flex items-center')
        ->add('text-sm font-medium')
        ->add($srOnly ? 'sr-only' : '')
        {{-- Default Flux uses text-zinc-800 + dark:text-white; class-based dark often misses, leaving
             near-black labels on our navy UI. Use app theme foreground everywhere. --}}
        ->add('text-primary')
        ->add('[&:has([data-flux-label-trailing])]:flex')
        ;
@endphp

<ui-label {{ $attributes->class($classes) }} data-flux-label>
    {{ $slot }}

    <?php if (is_string($badge)): ?>
        <span class="ms-1.5 rounded-[4px] bg-surface-2 px-1.5 py-1 text-xs text-muted -my-1" aria-hidden="true">
            {{ $badge }}
        </span>
    <?php elseif ($badge): ?>
        <span class="ms-1.5" aria-hidden="true">
            {{ $badge }}
        </span>
    <?php endif; ?>

    <?php if ($aside): ?>
        <span class="ms-1.5 rounded-[4px] bg-surface-2 px-1.5 py-1 text-xs text-muted -my-1" aria-hidden="true">
            {{ $aside }}
        </span>
    <?php endif; ?>

    <?php if ($trailing): ?>
        <div class="ml-auto" data-flux-label-trailing>
            {{ $trailing }}
        </div>
    <?php endif; ?>
</ui-label>
