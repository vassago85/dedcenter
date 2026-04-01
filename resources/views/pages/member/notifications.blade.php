<?php

use Livewire\Volt\Component;

new class extends Component {
    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function markRead(string $id): void
    {
        auth()->user()->notifications()->where('id', $id)->first()?->markAsRead();
    }

    public function with(): array
    {
        return [
            'notifications' => auth()->user()->notifications()->latest()->take(50)->get(),
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
        ];
    }
}; ?>

<x-layouts.app title="Notifications">
    <div class="mx-auto max-w-lg py-8 px-4">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Notifications</h1>
            @if($unreadCount > 0)
                <button wire:click="markAllRead" class="text-xs font-medium text-red-500 hover:text-red-400 transition-colors">
                    Mark all read
                </button>
            @endif
        </div>

        @if($notifications->isEmpty())
            <div class="rounded-xl border border-border bg-surface p-8 text-center">
                <svg class="mx-auto h-10 w-10 text-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                </svg>
                <p class="mt-3 text-sm text-muted">No notifications yet</p>
            </div>
        @else
            <div class="space-y-2">
                @foreach($notifications as $notification)
                    <div
                        wire:click="markRead('{{ $notification->id }}')"
                        class="cursor-pointer rounded-xl border p-4 transition-colors hover:bg-surface-2 {{ $notification->read_at ? 'border-border bg-surface opacity-60' : 'border-red-800/30 bg-red-900/10' }}"
                    >
                        <div class="flex items-start gap-3">
                            @unless($notification->read_at)
                                <span class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full bg-red-500"></span>
                            @endunless
                            <div class="flex-1 {{ $notification->read_at ? 'ml-5' : '' }}">
                                <p class="text-sm font-semibold">{{ $notification->data['title'] ?? 'Notification' }}</p>
                                <p class="mt-0.5 text-xs text-muted">{{ $notification->data['body'] ?? '' }}</p>
                                <p class="mt-1 text-[10px] text-muted">{{ $notification->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>
