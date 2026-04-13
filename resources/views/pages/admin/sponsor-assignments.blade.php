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
    #[Title('Platform Defaults')]
    class extends Component {
    public ?int $editingId = null;

    public string $sponsor_id = '';

    public string $placement_key = '';

    public string $label_override = '';

    public int $display_order = 0;

    public string $target_organization_id = '';

    public function mount(): void
    {
        $this->resetForm();
    }

    public function openAdd(): void
    {
        $this->resetForm();
        Flux::modal('assignment-form')->show();
    }

    public function openEdit(int $id): void
    {
        $assignment = SponsorAssignment::query()
            ->platform()
            ->whereNull('scope_id')
            ->findOrFail($id);

        $this->editingId = $assignment->id;
        $this->sponsor_id = (string) $assignment->sponsor_id;
        $this->placement_key = $assignment->placement_key->value;
        $this->label_override = $assignment->label_override ?? '';
        $this->display_order = $assignment->display_order;
        $tid = $assignment->metadata['target_organization_id'] ?? null;
        $this->target_organization_id = $tid !== null && $tid !== '' ? (string) $tid : '';

        Flux::modal('assignment-form')->show();
    }

    public function save(): void
    {
        $platformValues = array_map(
            fn (PlacementKey $p) => $p->value,
            PlacementKey::platformPlacements()
        );

        $this->validate([
            'sponsor_id' => 'required|exists:sponsors,id',
            'placement_key' => ['required', Rule::in($platformValues)],
            'label_override' => 'nullable|string|max:255',
            'display_order' => 'integer|min:0|max:99999',
        ]);

        $payload = [
            'sponsor_id' => (int) $this->sponsor_id,
            'scope_type' => SponsorScope::Platform,
            'scope_id' => null,
            'placement_key' => PlacementKey::from($this->placement_key),
            'label_override' => $this->label_override !== '' ? $this->label_override : null,
            'display_order' => $this->display_order,
        ];

        if ($this->editingId) {
            $existing = SponsorAssignment::query()
                ->platform()
                ->whereNull('scope_id')
                ->whereKey($this->editingId)
                ->value('metadata') ?? [];
            $existing = is_array($existing) ? $existing : [];
            if ($this->target_organization_id !== '') {
                $existing['target_organization_id'] = (int) $this->target_organization_id;
            } else {
                unset($existing['target_organization_id']);
            }
            $payload['metadata'] = $existing === [] ? null : $existing;

            SponsorAssignment::query()
                ->platform()
                ->whereNull('scope_id')
                ->whereKey($this->editingId)
                ->firstOrFail()
                ->update($payload);
            Flux::toast('Assignment updated.', variant: 'success');
        } else {
            $meta = $this->target_organization_id !== ''
                ? ['target_organization_id' => (int) $this->target_organization_id]
                : null;
            SponsorAssignment::create($payload + ['active' => true, 'metadata' => $meta]);
            Flux::toast('Assignment created.', variant: 'success');
        }

        Flux::modal('assignment-form')->close();
        $this->resetForm();
    }

    public function cancelForm(): void
    {
        Flux::modal('assignment-form')->close();
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        SponsorAssignment::query()
            ->platform()
            ->whereNull('scope_id')
            ->whereKey($id)
            ->firstOrFail()
            ->delete();

        Flux::toast('Assignment removed.', variant: 'success');
    }

    public function toggleActive(int $id): void
    {
        $assignment = SponsorAssignment::query()
            ->platform()
            ->whereNull('scope_id')
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
        $defaults = PlacementKey::platformPlacements();
        $this->placement_key = $defaults[0]->value;
        $this->label_override = '';
        $this->display_order = 0;
        $this->target_organization_id = '';
    }

    protected function surfaceTitle(string $surface): string
    {
        return match ($surface) {
            'leaderboard' => 'Leaderboard',
            'results' => 'Results',
            'scoring' => 'Scoring',
            'exports' => 'Exports',
            'matchbook' => 'Match Book',
            'brand_info' => 'Brand Info',
            'portal' => 'Club portals',
            'landing' => 'Marketing site',
            default => ucfirst(str_replace('_', ' ', $surface)),
        };
    }

    public function with(): array
    {
        $assignments = SponsorAssignment::query()
            ->platform()
            ->whereNull('scope_id')
            ->with('sponsor')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        $surfaceOrder = ['leaderboard', 'results', 'scoring', 'exports', 'matchbook', 'brand_info', 'portal', 'landing'];
        if (! config('deadcenter.matchbook_enabled')) {
            $surfaceOrder = array_values(array_filter($surfaceOrder, fn (string $s) => $s !== 'matchbook'));
        }

        $assignmentsBySurface = [];
        foreach ($surfaceOrder as $surface) {
            $assignmentsBySurface[$surface] = $assignments
                ->filter(fn (SponsorAssignment $a) => $a->placement_key->surface() === $surface)
                ->values();
        }

        return [
            'assignmentsBySurface' => $assignmentsBySurface,
            'surfaceOrder' => $surfaceOrder,
            'platformPlacements' => PlacementKey::platformPlacements(),
            'sponsors' => Sponsor::query()->active()->orderBy('name')->get(),
            'organizations' => Organization::query()->active()->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="space-y-8">
    <x-admin-tab-bar :tabs="[
        ['href' => route('admin.advertising'), 'label' => 'Match Placements', 'active' => false],
        ['href' => route('admin.sponsors'), 'label' => 'Brands', 'active' => false],
        ['href' => route('admin.sponsor-assignments'), 'label' => 'Platform Defaults', 'active' => true],
        ['href' => route('admin.sponsor-info'), 'label' => 'Brand Info', 'active' => false],
    ]" />

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Platform Defaults</flux:heading>
            <p class="mt-1 text-sm text-muted">Default brand placements across DeadCenter (not tied to a specific event).</p>
        </div>
        <flux:button variant="primary" wire:click="openAdd">Add assignment</flux:button>
    </div>

    @foreach($surfaceOrder as $surface)
        @php $rows = $assignmentsBySurface[$surface] ?? collect(); @endphp
        <div class="space-y-3" wire:key="surface-{{ $surface }}">
            <flux:heading size="lg">{{ $this->surfaceTitle($surface) }}</flux:heading>

            @if($rows->isEmpty())
                <p class="text-sm text-muted rounded-xl border border-border bg-surface px-4 py-6 text-center">No assignments in this area yet.</p>
            @else
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
                        @foreach($rows as $assignment)
                            <flux:table.row wire:key="assignment-{{ $assignment->id }}">
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
                                            wire:confirm="Remove this brand assignment?"
                                        >
                                            Delete
                                        </flux:button>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    @endforeach

    <flux:modal name="assignment-form" class="min-w-[min(100vw-2rem,28rem)]">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Edit assignment' : 'Add assignment' }}</flux:heading>
            <p class="text-sm text-muted">Platform-level defaults. Event advertising is managed in the Advertising page.</p>

            <flux:select wire:model="sponsor_id" label="Brand" required>
                <option value="">Select a brand…</option>
                @foreach($sponsors as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </flux:select>

            <flux:select wire:model="placement_key" label="Placement" required>
                @foreach($platformPlacements as $key)
                    <option value="{{ $key->value }}">{{ $key->label() }}</option>
                @endforeach
            </flux:select>

            <flux:input wire:model="label_override" label="Label override" placeholder='e.g. "Powered by"' description="Optional. Overrides the default &quot;Powered by&quot; text." />

            <flux:input wire:model.number="display_order" label="Display order" type="number" min="0" />

            <flux:select wire:model="target_organization_id" label="Limit to one organization’s portal (optional)">
                <option value="">All organization portals (default)</option>
                @foreach($organizations as $o)
                    <option value="{{ $o->id }}">{{ $o->name }}</option>
                @endforeach
            </flux:select>
            <p class="text-xs text-muted -mt-2">For <strong>club portal</strong> placements, you can reserve inventory for a single org. Leave empty for network-wide rotation on every portal. Match-level defaults ignore this field.</p>

            <div class="flex flex-wrap gap-2 pt-2">
                <flux:button type="submit" variant="primary">{{ $editingId ? 'Save changes' : 'Create assignment' }}</flux:button>
                <flux:button type="button" variant="ghost" wire:click="cancelForm">Cancel</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
