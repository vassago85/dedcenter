{{--
    x-app-page-header — standard header for every internal page.

    DeadCenter UX Standard §3C (page header) + §4.1.
    Every internal page gets one. Primary CTA goes in the `actions` slot.
    Status chips and state context go in the `status` slot. Breadcrumbs and
    backHref are optional and should only appear where they genuinely help.

    Props
    ─────
    title      string       Required. Page title (H1).
    subtitle   ?string      Short supporting description. Keep to one line.
    eyebrow    ?string      Small uppercase kicker above the title (e.g. 'Organization · Match').
    crumbs     array        Optional breadcrumbs: [['label'=>'Matches','href'=>...], ['label'=>'Current']].
    backHref   ?string      Optional explicit back link. Prefer crumbs.
    backLabel  string       Label for the back link. Default 'Back'.

    Slots
    ─────
    $status    Chips/badges shown under the title (match status, mode, etc.).
    $actions   Right-aligned action area. Primary CTA belongs here.
--}}
@props([
    'title',
    'subtitle' => null,
    'eyebrow' => null,
    'crumbs' => [],
    'backHref' => null,
    'backLabel' => 'Back',
])

<header {{ $attributes->merge(['class' => 'mb-6 flex flex-col gap-4 border-b border-border/60 pb-6 sm:flex-row sm:items-end sm:justify-between sm:gap-6']) }}>
    <div class="min-w-0 flex-1 space-y-2">
        @if(! empty($crumbs))
            <nav class="flex flex-wrap items-center gap-1.5 text-xs text-muted" aria-label="Breadcrumb">
                @foreach($crumbs as $crumb)
                    @if(! $loop->first)
                        <span class="text-muted/60" aria-hidden="true">/</span>
                    @endif
                    @if(isset($crumb['href']) && ! $loop->last)
                        <a href="{{ $crumb['href'] }}" class="transition-colors hover:text-primary">{{ $crumb['label'] }}</a>
                    @else
                        <span class="text-secondary">{{ $crumb['label'] }}</span>
                    @endif
                @endforeach
            </nav>
        @endif

        @if($backHref && empty($crumbs))
            <a href="{{ $backHref }}" class="inline-flex items-center gap-1 text-xs font-semibold text-muted transition-colors hover:text-primary">
                <x-icon name="chevron-left" class="h-3.5 w-3.5" />
                {{ $backLabel }}
            </a>
        @endif

        <div class="space-y-1.5">
            @if($eyebrow)
                <p class="text-eyebrow uppercase tracking-[0.18em] text-accent">{{ $eyebrow }}</p>
            @endif
            <h1 class="text-page-title text-primary">{{ $title }}</h1>
            @if($subtitle)
                <p class="max-w-2xl text-body text-muted">{{ $subtitle }}</p>
            @endif
        </div>

        @isset($status)
            <div class="flex flex-wrap items-center gap-2 pt-1">
                {{ $status }}
            </div>
        @endisset
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
            {{ $actions }}
        </div>
    @endisset
</header>
