@props(['tabs'])

<div class="flex gap-1 border-b border-border mb-6 overflow-x-auto">
    @foreach($tabs as $tab)
        <a href="{{ $tab['href'] }}"
           class="px-4 py-2 text-sm font-medium whitespace-nowrap border-b-2 transition-colors {{ $tab['active'] ? 'border-accent text-primary' : 'border-transparent text-muted hover:text-secondary' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
