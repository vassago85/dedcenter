<?php

use App\Models\ContactSubmission;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')]
    #[Title('Contact Submissions')]
    class extends Component {
    use WithPagination;

    public function markRead(int $id): void
    {
        ContactSubmission::find($id)?->markAsRead();
    }

    public function delete(int $id): void
    {
        ContactSubmission::find($id)?->delete();
    }

    public function with(): array
    {
        return [
            'submissions' => ContactSubmission::latest()->paginate(25),
            'unreadCount' => ContactSubmission::unread()->count(),
        ];
    }
}; ?>

<div class="space-y-6">
    <x-admin-tab-bar :tabs="[
        ['href' => route('admin.settings'), 'label' => 'General', 'active' => false],
        ['href' => route('admin.homepage'), 'label' => 'Homepage', 'active' => false],
        ['href' => route('admin.contact-submissions'), 'label' => 'Contact Inbox', 'active' => true],
    ]" />

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Contact Submissions</flux:heading>
            @if($unreadCount > 0)
                <p class="mt-1 text-sm text-amber-500">{{ $unreadCount }} unread</p>
            @endif
        </div>
    </div>

    @if($submissions->isEmpty())
        <div class="rounded-xl border border-zinc-200 bg-white p-12 text-center dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-zinc-500">No contact submissions yet.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($submissions as $sub)
                <div class="rounded-xl border p-5 {{ $sub->read_at ? 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800' : 'border-amber-500/30 bg-amber-50 dark:border-amber-600/30 dark:bg-amber-900/10' }}">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-zinc-900 dark:text-white">{{ $sub->name }}</span>
                                @if($sub->company)
                                    <span class="text-sm text-zinc-500">&mdash; {{ $sub->company }}</span>
                                @endif
                                @if(!$sub->read_at)
                                    <span class="rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-bold uppercase text-white">New</span>
                                @endif
                            </div>
                            <div class="mt-1 flex flex-wrap gap-4 text-sm text-zinc-500">
                                <a href="mailto:{{ $sub->email }}" class="hover:text-zinc-900 dark:hover:text-white">{{ $sub->email }}</a>
                                @if($sub->phone)
                                    <span>{{ $sub->phone }}</span>
                                @endif
                            </div>
                            <p class="mt-3 whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $sub->message }}</p>
                            <p class="mt-2 text-xs text-zinc-400">{{ $sub->created_at->diffForHumans() }} &mdash; via {{ $sub->source }}</p>
                        </div>
                        <div class="flex flex-shrink-0 gap-2">
                            @if(!$sub->read_at)
                                <button wire:click="markRead({{ $sub->id }})" class="rounded-lg border border-zinc-300 px-3 py-1.5 text-xs font-medium text-zinc-600 hover:bg-zinc-100 dark:border-zinc-600 dark:text-zinc-400 dark:hover:bg-zinc-700">
                                    Mark Read
                                </button>
                            @endif
                            <button wire:click="delete({{ $sub->id }})" wire:confirm="Delete this submission?" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $submissions->links() }}
        </div>
    @endif
</div>
