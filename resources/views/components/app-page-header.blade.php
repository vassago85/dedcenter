@props([
    'title',
    'subtitle' => null,
    'crumbs' => [],
])

<div class="mb-5 space-y-2">
    @if(! empty($crumbs))
        <nav class="flex flex-wrap items-center gap-2 text-xs text-muted">
            @foreach($crumbs as $crumb)
                @if(! $loop->first)
                    <span>/</span>
                @endif
                @if(isset($crumb['href']))
                    <a href="{{ $crumb['href'] }}" class="hover:text-secondary">{{ $crumb['label'] }}</a>
                @else
                    <span class="text-secondary">{{ $crumb['label'] }}</span>
                @endif
            @endforeach
        </nav>
    @endif
    <div>
        <h1 class="text-2xl font-bold text-primary">{{ $title }}</h1>
        @if($subtitle)
            <p class="mt-1 text-sm text-muted">{{ $subtitle }}</p>
        @endif
    </div>
</div>
