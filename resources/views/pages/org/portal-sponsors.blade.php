<?php

use App\Enums\PlacementKey;
use App\Enums\SponsorScope;
use App\Models\Organization;
use App\Models\Sponsor;
use App\Models\SponsorAssignment;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Portal sponsors')]
    class extends Component {
    public Organization $organization;

    public ?int $editingId = null;

    public string $sponsor_id = '';

    public string $placement_key = '';

    public string $label_override = '';

    public int $display_order = 0;

    public function mount(Organization $organization): void
    {
        if (! $organization->hasPortalAdRights()) {
            abort(403);
        }

        $this->organization = $organization;
        $this->resetForm();
    }

    public function openAdd(): void
    {
        $this->resetForm();
        Flux::modal('org-portal-assignment-form')->show();
    }

    public function openEdit(int $id): void
    {
        $assignment = SponsorAssignment::query()
            ->forOrganization($this->organization->id)
            ->findOrFail($id);

        $this->editingId = $assignment->id;
        $this->sponsor_id = (string) $assignment->sponsor_id;
        $this->placement_key = $assignment->placement_key->value;
        $this->label_override = $assignment->label_override ?? '';
        $this->display_order = $assignment->display_order;

        Flux::modal('org-portal-assignment-form')->show();
    }

    public function save(): void
    {
        $allowed = array_map(
            fn (PlacementKey $p) => $p->value,
            PlacementKey::organizationPortalPlacements()
        );

        $this->validate([
            'sponsor_id' => 'required|exists:sponsors,id',
            'placement_key' => ['required', Rule::in($allowed)],
            'label_override' => 'nullable|string|max:255',
            'display_order' => 'integer|min:0|max:99999',
        ]);

        $payload = [
            'sponsor_id' => (int) $this->sponsor_id,
            'scope_type' => SponsorScope::Organization,
            'scope_id' => $this->organization->id,
            'placement_key' => PlacementKey::from($this->placement_key),
            'label_override' => $this->label_override !== '' ? $this->label_override : null,
            'display_order' => $this->display_order,
        ];

        if ($this->editingId) {
            SponsorAssignment::query()
                ->forOrganization($this->organization->id)
                ->whereKey($this->editingId)
                ->firstOrFail()
                ->update($payload);
            Flux::toast('Assignment updated.', variant: 'success');
        } else {
            SponsorAssignment::create($payload + ['active' => true]);
            Flux::toast('Assignment created.', variant: 'success');
        }

        Flux::modal('org-portal-assignment-form')->close();
        $this->resetForm();
    }

    public function cancelForm(): void
    {
        Flux::modal('org-portal-assignment-form')->close();
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        SponsorAssignment::query()
            ->forOrganization($this->organization->id)
            ->whereKey($id)
            ->firstOrFail()
            ->delete();

        Flux::toast('Assignment removed.', variant: 'success');
    }

    public function toggleActive(int $id): void
    {
        $assignment = SponsorAssignment::query()
            ->forOrganization($this->organization->id)
            ->findOrFail($id);

        $assignment->update(['active' => ! $assignment->active]);

        Flux::toast(
            $assignment->active ? 'Assignment activated.' : 'Assignment deactivated.',
            variant: 'success'
        );
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->sponsor_id = '';
        $defaults = PlacementKey::organizationPortalPlacements();
        $this->placement_key = $defaults[0]->value;
        $this->label_override = '';
        $this->display_order = 0;
    }

    public function with(): array
    {
        $assignments = SponsorAssignment::query()
            ->forOrganization($this->organization->id)
            ->with('sponsor')
            ->orderBy('placement_key')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        return [
            'assignments' => $assignments,
            'portalPlacements' => PlacementKey::organizationPortalPlacements(),
            'sponsors' => Sponsor::query()->active()->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="space-y-6 max-w-4xl">
    <x-app-page-header
        :title="'Portal sponsors — ' . $organization->name"
        subtitle="Assign brands to placements on your public portal. Network defaults still appear when you leave a slot empty."
        :crumbs="[
            ['label' => 'Organization'],
            ['label' => $organization->name],
            ['label' => 'Portal sponsors'],
        ]"
    />

    <div class="flex justify-end">
        <flux:button variant="primary" wire:click="openAdd">Add assignment</flux:button>
    </div>

    @if($assignments->isEmpty())
        <div class="rounded-xl border border-border bg-surface px-6 py-12 text-center text-sm text-muted">
            No portal sponsor assignments yet. Add one for each placement you want to brand.
        </div>
    @else
        <div class="rounded-xl border border-border bg-surface overflow-hidden">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Placement</flux:table.column>
                    <flux:table.column>Brand</flux:table.column>
                    <flux:table.column>Label</flux:table.column>
                    <flux:table.column>Order</flux:table.column>
                    <flux:table.column align="center">Active</flux:table.column>
                    <flux:table.column align="end">Actions</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($assignments as $assignment)
                        <flux:table.row wire:key="pa-{{ $assignment->id }}">
                            <flux:table.cell variant="strong">{{ $assignment->placement_key->label() }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-3 min-w-0">
                                    @if($assignment->sponsor?->logo_path)
                                        <img src="{{ asset('storage/'.$assignment->sponsor->logo_path) }}" alt="" class="h-8 w-auto max-w-[100px] object-contain shrink-0" />
                                    @endif
                                    <span class="truncate">{{ $assignment->sponsor?->name ?? '—' }}</span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $assignment->label_override ?: '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $assignment->display_order }}</flux:table.cell>
                            <flux:table.cell align="center">
                                @if($assignment->active)
                                    <flux:badge size="sm" color="green">Yes</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">No</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button size="sm" variant="ghost" wire:click="openEdit({{ $assignment->id }})">Edit</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="toggleActive({{ $assignment->id }})">
                                        {{ $assignment->active ? 'Deactivate' : 'Activate' }}
                                    </flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        wire:click="delete({{ $assignment->id }})"
                                        wire:confirm="Remove this assignment?"
                                    >
                                        Delete
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    <flux:modal name="org-portal-assignment-form" class="min-w-[min(100vw-2rem,28rem)]">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Edit assignment' : 'Add assignment' }}</flux:heading>
            <p class="text-sm text-muted">These placements appear on your public portal only.</p>

            <flux:select wire:model="sponsor_id" label="Brand" required>
                <option value="">Select a brand…</option>
                @foreach($sponsors as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </flux:select>

            <flux:select wire:model="placement_key" label="Placement" required>
                @foreach($portalPlacements as $key)
                    <option value="{{ $key->value }}">{{ $key->label() }}</option>
                @endforeach
            </flux:select>

            <flux:input wire:model="label_override" label="Label override" placeholder='e.g. "Presented by"' />

            <flux:input wire:model.number="display_order" label="Display order" type="number" min="0" />

            <div class="flex flex-wrap gap-2 pt-2">
                <flux:button type="submit" variant="primary">{{ $editingId ? 'Save changes' : 'Create assignment' }}</flux:button>
                <flux:button type="button" variant="ghost" wire:click="cancelForm">Cancel</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
