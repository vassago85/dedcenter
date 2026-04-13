@props(['organization', 'context' => 'app'])

@php
    $org = $organization;
    $logoUrl = $org->logoUrl();
    $hasLogo = (bool) $logoUrl;
    $href = $org->publicMarketingHref();
    $isMarketing = $context === 'marketing';
@endphp

@if($isMarketing)
    <a href="{{ $href }}" class="group rounded-2xl p-6 transition-all duration-200 hover:scale-[1.02]" style="border: 1px solid var(--lp-border); background: var(--lp-surface);" onmouseover="this.style.borderColor='rgba(225,6,0,0.3)'" onmouseout="this.style.borderColor='var(--lp-border)'">
        <div class="flex items-center gap-4 mb-3">
            @if($hasLogo)
                <img src="{{ $logoUrl }}" alt="{{ $org->name }}" class="h-10 w-10 rounded-lg object-contain" style="background: var(--lp-surface-2);">
            @else
                <div class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-bold" style="background: rgba(225,6,0,0.08); color: var(--lp-red);">
                    {{ strtoupper(substr($org->name, 0, 2)) }}
                </div>
            @endif
            <div>
                <h3 class="text-base font-semibold group-hover:!text-white transition-colors" style="color: var(--lp-text);">{{ $org->name }}</h3>
                <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--lp-text-muted);">{{ $org->type }}</span>
            </div>
        </div>
        @if($org->description)
            <p class="text-sm leading-relaxed line-clamp-2" style="color: var(--lp-text-soft);">{{ $org->description }}</p>
        @endif
    </a>
@else
    <a href="{{ $href }}" class="group flex items-center gap-4 rounded-xl border border-border bg-surface p-4 transition-all hover:border-accent/40 hover:bg-surface-2/30">
        @if($hasLogo)
            <img src="{{ $logoUrl }}" alt="{{ $org->name }}" class="h-10 w-10 flex-shrink-0 rounded-lg bg-surface-2 object-contain" />
        @else
            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-surface-2 text-sm font-bold text-muted">
                {{ strtoupper(substr($org->name, 0, 2)) }}
            </div>
        @endif
        <div class="min-w-0 flex-1">
            <h3 class="text-sm font-semibold text-primary group-hover:text-accent transition-colors">{{ $org->name }}</h3>
            <span class="text-[11px] font-medium uppercase tracking-wider text-muted">{{ $org->type }}</span>
        </div>
    </a>
@endif
