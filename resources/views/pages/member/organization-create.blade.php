<?php

use App\Models\Organization;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Create Organization')]
    class extends Component {
    public string $name = '';
    public string $type = 'club';
    public string $description = '';
    public string $parent_id = '';

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:league,club,competition,challenge',
            'description' => 'nullable|string|max:2000',
            'parent_id' => 'nullable|exists:organizations,id',
        ]);

        $org = Organization::create([
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description ?: null,
            'parent_id' => $this->parent_id ?: null,
            'status' => 'pending',
            'created_by' => auth()->id(),
        ]);

        Flux::toast("'{$org->name}' has been submitted for approval.", variant: 'success');
        $this->redirect(route('organizations'), navigate: true);
    }

    public function with(): array
    {
        return [
            'leagues' => Organization::approved()->ofType('league')->orderBy('name')->get(['id', 'name']),
        ];
    }
}; ?>

<div class="space-y-6 max-w-2xl">
    <div class="flex items-center gap-4">
        <flux:button href="{{ route('organizations') }}" variant="ghost" size="sm">
            <svg class="mr-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
            Back
        </flux:button>
        <div>
            <flux:heading size="xl">Create Organization</flux:heading>
            <p class="mt-1 text-sm text-slate-400">Submit a new league, club, competition, or challenge for approval.</p>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-xl border border-slate-700 bg-slate-800 p-6 space-y-4">
            <flux:input wire:model="name" label="Organization Name" placeholder="e.g. Gauteng Shooting League" required />

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Type</label>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach(['league' => 'League', 'club' => 'Club', 'competition' => 'Competition', 'challenge' => 'Challenge'] as $value => $label)
                        <button type="button" wire:click="$set('type', '{{ $value }}')"
                                class="rounded-lg border px-3 py-2 text-sm font-medium text-center transition-colors {{ $type === $value ? 'border-red-600 bg-red-600/20 text-red-400' : 'border-slate-600 bg-slate-700 text-slate-300 hover:border-slate-500' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            @if($type === 'club' && $leagues->isNotEmpty())
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Parent League (optional)</label>
                    <select wire:model="parent_id" class="w-full rounded-lg border border-slate-600 bg-slate-700 px-3 py-2 text-sm text-white focus:border-red-500 focus:ring-1 focus:ring-red-500">
                        <option value="">No parent league</option>
                        @foreach($leagues as $league)
                            <option value="{{ $league->id }}">{{ $league->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <flux:textarea wire:model="description" label="Description" placeholder="What is this organization about..." rows="3" />

            <div class="rounded-lg border border-amber-800 bg-amber-900/20 p-4">
                <p class="text-sm text-amber-400">Your organization will be reviewed by a site administrator before it becomes visible.</p>
            </div>

            <div class="flex justify-end pt-2">
                <flux:button type="submit" variant="primary" class="!bg-red-600 hover:!bg-red-700">Submit for Approval</flux:button>
            </div>
        </div>
    </form>
</div>
