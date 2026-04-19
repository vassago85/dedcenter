{{--
    x-skeleton — loading placeholder primitives.

    DeadCenter UX Standard §12 (states): prefer skeletons over spinners.
    Three flavours:

      <x-skeleton variant="line"  width="w-32" />         – inline text line
      <x-skeleton variant="block" height="h-24" />        – block placeholder
      <x-skeleton variant="row"   :columns="4" />         – a single table row
      <x-skeleton variant="card"  :rows="3" />            – a panel-sized stack

    Props
    ─────
    variant   string   'line' | 'block' | 'row' | 'card'. Default 'line'.
    width     string   Tailwind width class. Default 'w-full'.
    height    string   Tailwind height class. Default depends on variant.
    rows      int      Used by 'card' variant: number of lines to stub.
    columns   int      Used by 'row' variant: number of cells to stub.
--}}
@props([
    'variant' => 'line',
    'width' => 'w-full',
    'height' => null,
    'rows' => 3,
    'columns' => 4,
])

@switch($variant)
    @case('block')
        <div {{ $attributes->merge(['class' => "dc-skeleton {$width} " . ($height ?? 'h-24')]) }}></div>
        @break

    @case('row')
        <tr class="animate-pulse [&>td]:px-4 [&>td]:py-3.5 [&>td:first-child]:pl-6 [&>td:last-child]:pr-6">
            @for($i = 0; $i < $columns; $i++)
                <td><div class="dc-skeleton h-4 {{ $i === 0 ? 'w-40' : 'w-24' }}"></div></td>
            @endfor
        </tr>
        @break

    @case('card')
        <div {{ $attributes->merge(['class' => 'space-y-3 rounded-xl border border-border bg-surface p-5']) }}>
            <div class="dc-skeleton h-4 w-1/3"></div>
            @for($i = 0; $i < $rows; $i++)
                <div class="dc-skeleton h-3 {{ $i % 2 === 0 ? 'w-full' : 'w-5/6' }}"></div>
            @endfor
        </div>
        @break

    @default
        <div {{ $attributes->merge(['class' => "dc-skeleton h-4 {$width} " . ($height ?? '')]) }}></div>
@endswitch
