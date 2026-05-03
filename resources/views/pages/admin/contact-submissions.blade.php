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
        <div class="rounded-xl border border-border bg-surface">
            <x-empty-state
                title="No contact submissions yet"
                description="Messages from the public contact form will land here when someone gets in touch.">
                <x-slot:icon>
                    <x-icon name="mail" class="h-5 w-5" />
                </x-slot:icon>
            </x-empty-state>
        </div>
    @else
        <div class="space-y-4">
            @foreach($submissions as $sub)
                {{--
                    Use design tokens (`bg-surface`, `border-border`, `text-primary`)
                    instead of raw `bg-white` / `dark:bg-zinc-*`. The previous
                    classes hard-coded a light theme that rendered as a glaring
                    white panel with white-on-white text against the rest of
                    the app's dark surface — the empty state was completely
                    invisible.
                --}}
                <div class="rounded-xl border p-5 {{ $sub->read_at ? 'border-border bg-surface' : 'border-amber-500/40 bg-amber-500/10' }}">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-primary">{{ $sub->name }}</span>
                                @if($sub->company)
                                    <span class="text-sm text-muted">&mdash; {{ $sub->company }}</span>
                                @endif
                                @if(!$sub->read_at)
                                    <span class="rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-bold uppercase text-black">New</span>
                                @endif
                            </div>
                            <div class="mt-1 flex flex-wrap gap-4 text-sm text-muted">
                                <a href="mailto:{{ $sub->email }}" class="transition-colors hover:text-primary">{{ $sub->email }}</a>
                                @if($sub->phone)
                                    <span>{{ $sub->phone }}</span>
                                @endif
                            </div>
                            <p class="mt-3 whitespace-pre-line text-sm text-secondary">{{ $sub->message }}</p>
                            <p class="mt-2 text-xs text-muted">{{ $sub->created_at->diffForHumans() }} &mdash; via {{ $sub->source }}</p>
                        </div>
                        <div class="flex flex-shrink-0 gap-2">
                            @if(!$sub->read_at)
                                <button wire:click="markRead({{ $sub->id }})" class="rounded-lg border border-border bg-surface-2 px-3 py-1.5 text-xs font-medium text-secondary transition-colors hover:border-accent hover:text-primary">
                                    Mark Read
                                </button>
                            @endif
                            <button wire:click="delete({{ $sub->id }})" wire:confirm="Delete this submission?" class="rounded-lg border border-red-500/40 bg-red-500/10 px-3 py-1.5 text-xs font-medium text-red-300 transition-colors hover:bg-red-500/20 hover:text-red-200">
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
