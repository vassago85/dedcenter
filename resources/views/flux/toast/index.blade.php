@blaze(fold: true, safe: ['position'])

@props([
    'position' => 'bottom end',
])

{{-- DeadCenter override of Flux's toast template.

     Flux renders <ui-toast> into a shadow DOM. Tailwind's `dark:` variant
     resolves to `.dark ...` ancestor-chain selectors, which do NOT cross
     shadow-root boundaries, so the `dark:` classes in the vendor template
     silently fall back to their light-mode defaults inside the toast —
     which on our always-dark shell renders as white text on an almost-
     white pill (user reported "same colour as background").

     Our site is permanently dark (html.dark is hardcoded), so we bake the
     dark palette into the toast directly and drop the light-mode classes. --}}

<ui-toast x-data x-on:toast-show.document="! $el.closest('ui-toast-group') && $el.showToast($event.detail)" popover="manual" position="{{ $position }}" wire:ignore>
    <template>
        <div {{ $attributes->only(['class'])->class('max-w-sm in-[ui-toast-group]:max-w-auto in-[ui-toast-group]:w-xs sm:in-[ui-toast-group]:w-sm') }} data-variant="" data-flux-toast-dialog>
            <div class="p-2 flex rounded-xl shadow-lg bg-zinc-800 border border-zinc-700 border-b-zinc-900/80">
                <div class="flex-1 flex items-start gap-4 overflow-hidden">
                    <div class="flex-1 py-1.5 ps-2.5 flex gap-2">
                        {{-- Success icon --}}
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="hidden [[data-flux-toast-dialog][data-variant=success]_&]:block shrink-0 mt-0.5 size-4 text-lime-400">
                            <path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm3.844-8.791a.75.75 0 0 0-1.188-.918l-3.7 4.79-1.649-1.833a.75.75 0 1 0-1.114 1.004l2.25 2.5a.75.75 0 0 0 1.15-.043l4.25-5.5Z" clip-rule="evenodd" />
                        </svg>

                        {{-- Warning icon --}}
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="hidden [[data-flux-toast-dialog][data-variant=warning]_&]:block shrink-0 mt-0.5 size-4 text-amber-400">
                            <path fill-rule="evenodd" d="M6.701 2.25c.577-1 2.02-1 2.598 0l5.196 9a1.5 1.5 0 0 1-1.299 2.25H2.804a1.5 1.5 0 0 1-1.3-2.25l5.197-9ZM8 4a.75.75 0 0 1 .75.75v3a.75.75 0 1 1-1.5 0v-3A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                        </svg>

                        {{-- Danger icon --}}
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="hidden [[data-flux-toast-dialog][data-variant=danger]_&]:block shrink-0 mt-0.5 size-4 text-rose-400">
                            <path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14ZM8 4a.75.75 0 0 1 .75.75v3a.75.75 0 0 1-1.5 0v-3A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                        </svg>

                        <div>
                            {{-- Heading --}}
                            <div class="font-medium text-sm text-white [&:not(:empty)+div]:font-normal [&:not(:empty)+div]:text-zinc-300 [&:not(:empty)]:pb-2"><slot name="heading"></slot></div>

                            {{-- Text --}}
                            <div class="font-medium text-sm text-white"><slot name="text"></slot></div>
                        </div>
                    </div>

                    {{-- Close button --}}
                    <ui-close class="flex items-center">
                        <button type="button" class="inline-flex items-center font-medium justify-center gap-2 truncate disabled:opacity-75 disabled:cursor-default h-8 text-sm rounded-md w-8 bg-transparent hover:bg-white/15 text-zinc-400 hover:text-white" as="button">
                            <div>
                                <svg class="[:where(&)]:size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                                    <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"></path>
                                </svg>
                            </div>
                        </button>
                    </ui-close>
                </div>
            </div>
        </div>
    </template>
</ui-toast>
