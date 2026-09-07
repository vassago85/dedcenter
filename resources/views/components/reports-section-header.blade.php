@props([
    'title',
    'description' => null,
    'icon' => 'file-text',
])

{{-- Shared header for Reports panel sections. Keeps every panel's typography aligned. --}}

<div class="mb-4 flex items-start gap-3">
    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-border bg-surface-2/40 text-muted">
        <x-icon :name="$icon" class="h-4 w-4" />
    </div>
    <div class="min-w-0 flex-1">
        <h3 class="text-base font-semibold text-primary">{{ $title }}</h3>
        @if($description)
            <p class="mt-0.5 text-xs text-muted">{{ $description }}</p>
        @endif
    </div>
</div>
