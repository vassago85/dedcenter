<?php

use App\Models\Sponsor;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')]
    #[Title('Brands')]
    class extends Component {
    use WithFileUploads;

    public string $search = '';

    public ?int $editingId = null;

    public string $name = '';

    public string $slug = '';

    public ?string $editingLogoPath = null;

    public $logo = null;

    public string $website_url = '';

    public string $contact_name = '';

    public string $contact_email = '';

    public string $short_description = '';

    public bool $active = true;

    public bool $assignable_by_match_director = true;

    public string $internal_notes = '';

    public string $starts_at = '';

    public string $ends_at = '';

    protected function makeUniqueSlug(string $name, ?int $exceptId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;
        while (Sponsor::query()
            ->where('slug', $slug)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function openCreate(): void
    {
        $this->resetForm();
        Flux::modal('sponsor-form')->show();
    }

    public function openEdit(int $id): void
    {
        $sponsor = Sponsor::findOrFail($id);
        $this->editingId = $sponsor->id;
        $this->name = $sponsor->name;
        $this->slug = $sponsor->slug;
        $this->website_url = $sponsor->website_url ?? '';
        $this->contact_name = $sponsor->contact_name ?? '';
        $this->contact_email = $sponsor->contact_email ?? '';
        $this->short_description = $sponsor->short_description ?? '';
        $this->active = $sponsor->active;
        $this->assignable_by_match_director = $sponsor->assignable_by_match_director;
        $this->internal_notes = $sponsor->internal_notes ?? '';
        $this->starts_at = $sponsor->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->ends_at = $sponsor->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->logo = null;
        $this->editingLogoPath = $sponsor->logo_path;
        Flux::modal('sponsor-form')->show();
    }

    public function save(): void
    {
        $this->name = trim($this->name);
        $this->website_url = trim($this->website_url);
        $this->contact_name = trim($this->contact_name);
        $this->contact_email = trim($this->contact_email);

        $this->validate([
            'name' => 'required|string|max:255',
            'website_url' => 'nullable|url|max:255',
            'contact_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'short_description' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'active' => 'boolean',
            'assignable_by_match_director' => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'logo' => 'nullable|image|max:4096',
        ]);

        $payload = [
            'name' => $this->name,
            'website_url' => $this->website_url !== '' ? $this->website_url : null,
            'contact_name' => $this->contact_name !== '' ? $this->contact_name : null,
            'contact_email' => $this->contact_email !== '' ? $this->contact_email : null,
            'short_description' => $this->short_description !== '' ? $this->short_description : null,
            'active' => $this->active,
            'assignable_by_match_director' => $this->assignable_by_match_director,
            'internal_notes' => $this->internal_notes !== '' ? $this->internal_notes : null,
            'starts_at' => $this->starts_at !== '' ? $this->starts_at : null,
            'ends_at' => $this->ends_at !== '' ? $this->ends_at : null,
        ];

        if ($this->editingId) {
            $sponsor = Sponsor::findOrFail($this->editingId);
            $payload['slug'] = $this->makeUniqueSlug($this->name, $sponsor->id);

            if ($this->logo) {
                if ($sponsor->logo_path) {
                    Storage::disk('public')->delete($sponsor->logo_path);
                }
                $payload['logo_path'] = $this->logo->store('sponsors', 'public');
            }

            $sponsor->update($payload);
            Flux::toast('Brand updated.', variant: 'success');
        } else {
            $sponsor = Sponsor::create($payload);

            if ($this->logo) {
                $sponsor->update([
                    'logo_path' => $this->logo->store('sponsors', 'public'),
                ]);
            }

            Flux::toast('Brand created.', variant: 'success');
        }

        $this->logo = null;
        $this->resetForm();
        Flux::modal('sponsor-form')->close();
    }

    public function cancelForm(): void
    {
        $this->resetForm();
        Flux::modal('sponsor-form')->close();
    }

    public function toggleActive(int $id): void
    {
        $sponsor = Sponsor::findOrFail($id);
        $sponsor->update(['active' => ! $sponsor->active]);
        Flux::toast($sponsor->active ? 'Brand activated.' : 'Brand deactivated.', variant: 'success');
    }

    public function delete(int $id): void
    {
        $sponsor = Sponsor::findOrFail($id);
        if ($sponsor->logo_path) {
            Storage::disk('public')->delete($sponsor->logo_path);
        }
        $sponsor->delete();
        Flux::toast('Brand deleted.', variant: 'success');
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->slug = '';
        $this->editingLogoPath = null;
        $this->logo = null;
        $this->website_url = '';
        $this->contact_name = '';
        $this->contact_email = '';
        $this->short_description = '';
        $this->active = true;
        $this->assignable_by_match_director = true;
        $this->internal_notes = '';
        $this->starts_at = '';
        $this->ends_at = '';
        $this->resetValidation();
    }

    public function with(): array
    {
        $sponsors = Sponsor::query()
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($this->search)).'%';
                $q->where('name', 'like', $term);
            })
            ->orderBy('name')
            ->get();

        return ['sponsors' => $sponsors];
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">Brands</flux:heading>
            <p class="mt-1 text-sm text-muted">Create and manage brands, logos, and visibility windows.</p>
        </div>
        <flux:button variant="primary" wire:click="openCreate">Add brand</flux:button>
    </div>

    <div class="max-w-md">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search by name…"
            icon="magnifying-glass"
        />
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Logo</flux:table.column>
            <flux:table.column>Active</flux:table.column>
            <flux:table.column>Assignable</flux:table.column>
            <flux:table.column>Dates</flux:table.column>
            <flux:table.column align="end">Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse($sponsors as $sponsor)
                <flux:table.row wire:key="sponsor-row-{{ $sponsor->id }}">
                    <flux:table.cell variant="strong">{{ $sponsor->name }}</flux:table.cell>
                    <flux:table.cell>
                        @if($sponsor->hasLogo())
                            <img
                                src="{{ Storage::url($sponsor->logo_path) }}"
                                alt=""
                                class="h-8 max-w-[5rem] object-contain rounded border border-zinc-800/20 dark:border-white/10"
                            />
                        @else
                            <span class="text-xs text-muted">—</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($sponsor->active)
                            <flux:badge size="sm" color="green">Active</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">Inactive</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($sponsor->assignable_by_match_director)
                            <flux:badge size="sm" color="blue">MD</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">Admin only</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="whitespace-normal text-xs text-muted">
                            @if($sponsor->starts_at || $sponsor->ends_at)
                                @if($sponsor->starts_at)
                                    <div>From {{ $sponsor->starts_at->format('d M Y H:i') }}</div>
                                @endif
                                @if($sponsor->ends_at)
                                    <div>Until {{ $sponsor->ends_at->format('d M Y H:i') }}</div>
                                @endif
                            @else
                                <span>Always</span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <flux:button size="sm" variant="ghost" class="!text-secondary hover:!text-primary"
                                         wire:click="openEdit({{ $sponsor->id }})">Edit</flux:button>
                            <flux:button size="sm" variant="ghost" class="!text-secondary hover:!text-primary"
                                         wire:click="toggleActive({{ $sponsor->id }})">
                                {{ $sponsor->active ? 'Deactivate' : 'Activate' }}
                            </flux:button>
                            <flux:button
                                size="sm"
                                variant="ghost"
                                class="!text-secondary hover:!text-primary"
                                wire:click="delete({{ $sponsor->id }})"
                                wire:confirm="Delete brand &quot;{{ $sponsor->name }}&quot;? This cannot be undone."
                            >
                                Delete
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">
                        <span class="text-sm text-muted">No brands found.</span>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="sponsor-form" class="min-w-[min(100vw-2rem,42rem)]">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Edit brand' : 'New brand' }}</flux:heading>

            <flux:input wire:model="name" label="Name" placeholder="Brand name" required />

            @if($editingId)
                <flux:input :value="$slug" label="Slug" disabled description="Auto-generated from name on save." />
            @endif

            <flux:input type="file" wire:model="logo" label="Logo" description="PNG, JPG, or WebP up to 4 MB." />

            @if($logo)
                <p class="text-xs text-muted">New file: {{ $logo->getClientOriginalName() }}</p>
            @elseif($editingLogoPath)
                <div class="flex items-center gap-3">
                    <span class="text-xs text-muted">Current:</span>
                    <img src="{{ Storage::url($editingLogoPath) }}" alt="" class="h-10 max-w-[8rem] object-contain rounded border border-zinc-800/20 dark:border-white/10" />
                </div>
            @endif

            <flux:input wire:model="website_url" label="Website URL" type="url" placeholder="https://…" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="contact_name" label="Contact name" />
                <flux:input wire:model="contact_email" label="Contact email" type="email" />
            </div>

            <flux:textarea wire:model="short_description" label="Short description" rows="3" />

            <div class="flex flex-col gap-3 sm:flex-row sm:gap-8">
                <flux:checkbox wire:model="active" label="Active" />
                <flux:checkbox wire:model="assignable_by_match_director" label="Assignable by match director" />
            </div>

            <flux:textarea wire:model="internal_notes" label="Internal notes" rows="3" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="starts_at" label="Starts at" type="datetime-local" />
                <flux:input wire:model="ends_at" label="Ends at" type="datetime-local" />
            </div>

            <div class="flex flex-wrap gap-2 pt-2">
                <flux:button type="submit" variant="primary">{{ $editingId ? 'Save changes' : 'Create brand' }}</flux:button>
                <flux:button type="button" variant="ghost" class="!text-secondary hover:!text-primary" wire:click="cancelForm">Cancel</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
