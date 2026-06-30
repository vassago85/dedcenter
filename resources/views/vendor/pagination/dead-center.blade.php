{{--
    DeadCenter pagination view.

    Why this exists: inside a Livewire component, $paginator->links() defaults
    to the livewire::tailwind view, which is styled with bg-white / text-gray-*
    plus dark: variants. Those classes live under vendor/livewire and are NOT
    in this project's Tailwind @source list (see resources/css/app.css), so they
    never get compiled — the pagination rendered as unstyled native white boxes.
    This view uses the app's own design tokens (bg-surface, border-border,
    text-secondary, bg-accent, …) which ARE compiled because it lives under
    resources/views, and keeps Livewire's wire:click handlers so the controls
    actually navigate instead of doing a full page reload.

    Pass it explicitly: {{ $paginator->links('vendor.pagination.dead-center') }}
--}}
@php
    if (! isset($scrollTo)) {
        $scrollTo = 'body';
    }

    $scrollIntoViewJsSnippet = ($scrollTo !== false)
        ? <<<JS
           (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
        JS
        : '';

    $baseBtn = 'relative inline-flex items-center justify-center min-h-[36px] min-w-[36px] px-3 py-1.5 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-accent';
    $idleBtn = 'bg-surface-2 text-secondary hover:bg-surface-2/70 hover:text-primary border border-border';
    $activeBtn = 'bg-accent text-white border border-accent cursor-default';
    $disabledBtn = 'bg-surface-2/40 text-muted border border-border/60 cursor-not-allowed';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-between gap-4">
            {{-- Mobile: prev / next only --}}
            <div class="flex flex-1 items-center justify-between sm:hidden">
                @if ($paginator->onFirstPage())
                    <span class="{{ $baseBtn }} {{ $disabledBtn }} rounded-lg">{!! __('pagination.previous') !!}</span>
                @else
                    <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" class="{{ $baseBtn }} {{ $idleBtn }} rounded-lg">
                        {!! __('pagination.previous') !!}
                    </button>
                @endif

                <span class="text-xs text-muted">
                    {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
                </span>

                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" class="{{ $baseBtn }} {{ $idleBtn }} rounded-lg">
                        {!! __('pagination.next') !!}
                    </button>
                @else
                    <span class="{{ $baseBtn }} {{ $disabledBtn }} rounded-lg">{!! __('pagination.next') !!}</span>
                @endif
            </div>

            {{-- Desktop: full controls --}}
            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <p class="text-sm text-muted">
                    {!! __('Showing') !!}
                    <span class="font-semibold text-secondary">{{ $paginator->firstItem() }}</span>
                    {!! __('to') !!}
                    <span class="font-semibold text-secondary">{{ $paginator->lastItem() }}</span>
                    {!! __('of') !!}
                    <span class="font-semibold text-secondary">{{ $paginator->total() }}</span>
                    {!! __('results') !!}
                </p>

                <div class="flex items-center gap-1">
                    {{-- Previous --}}
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="{{ $baseBtn }} {{ $disabledBtn }} rounded-lg" aria-hidden="true">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                        </span>
                    @else
                        <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" class="{{ $baseBtn }} {{ $idleBtn }} rounded-lg" aria-label="{{ __('pagination.previous') }}">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                        </button>
                    @endif

                    {{-- Page numbers --}}
                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span aria-disabled="true" class="{{ $baseBtn }} {{ $disabledBtn }} rounded-lg">{{ $element }}</span>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page" wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}" class="{{ $baseBtn }} {{ $activeBtn }} rounded-lg">{{ $page }}</span>
                                @else
                                    <button type="button" wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" class="{{ $baseBtn }} {{ $idleBtn }} rounded-lg" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                        {{ $page }}
                                    </button>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next --}}
                    @if ($paginator->hasMorePages())
                        <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" class="{{ $baseBtn }} {{ $idleBtn }} rounded-lg" aria-label="{{ __('pagination.next') }}">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                        </button>
                    @else
                        <span aria-disabled="true" aria-label="{{ __('pagination.next') }}" class="{{ $baseBtn }} {{ $disabledBtn }} rounded-lg" aria-hidden="true">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                        </span>
                    @endif
                </div>
            </div>
        </nav>
    @endif
</div>
