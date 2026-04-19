<?php

use App\Models\Organization;
use App\Models\ShootingMatch;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')]
    #[Title('Organization Settings')]
    class extends Component {
    use WithFileUploads;

    public Organization $organization;
    public $logo = null;

    public string $name = '';
    public string $description = '';
    public string $best_of = '';
    public bool $uses_relative_scoring = true;
    public string $entry_fee_default = '';
    public string $primary_color = '#dc2626';
    public string $secondary_color = '#1e293b';
    public string $hero_text = '';
    public string $hero_description = '';
    public bool $portal_enabled = false;

    public bool $season_standings_enabled = true;

    public string $bank_name = '';

    public string $bank_account_holder = '';

    public string $bank_account_number = '';

    public string $bank_branch_code = '';

    public function mount(Organization $organization): void
    {
        $this->organization = $organization;
        $this->name = $organization->name;
        $this->description = $organization->description ?? '';
        $this->best_of = $organization->best_of ? (string) $organization->best_of : '';
        $this->uses_relative_scoring = (bool) ($organization->uses_relative_scoring ?? true);
        $this->entry_fee_default = $organization->entry_fee_default ? (string) $organization->entry_fee_default : '';
        $this->primary_color = $organization->primary_color ?? '#dc2626';
        $this->secondary_color = $organization->secondary_color ?? '#1e293b';
        $this->hero_text = $organization->hero_text ?? '';
        $this->hero_description = $organization->hero_description ?? '';
        $this->portal_enabled = (bool) $organization->portal_enabled;
        $this->season_standings_enabled = (bool) ($organization->season_standings_enabled ?? true);

        if (auth()->user()->isOrgOwner($organization)) {
            $this->bank_name = $organization->bank_name ?? '';
            $this->bank_account_holder = $organization->bank_account_holder ?? '';
            $this->bank_account_number = $organization->bank_account_number ?? '';
            $this->bank_branch_code = $organization->bank_branch_code ?? '';
        }
    }

    public function removeLogo(): void
    {
        if ($this->organization->logo_path) {
            Storage::disk('public')->delete($this->organization->logo_path);
            $this->organization->update(['logo_path' => null]);
            Flux::toast('Logo removed.', variant: 'success');
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'best_of' => 'nullable|integer|min:1|max:100',
            'entry_fee_default' => 'nullable|numeric|min:0',
            'primary_color' => 'required|string|max:7',
            'secondary_color' => 'required|string|max:7',
            'hero_text' => 'nullable|string|max:255',
            'hero_description' => 'nullable|string|max:500',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_holder' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_branch_code' => 'nullable|string|max:20',
            'logo' => 'nullable|image|max:4096',
        ]);

        $canEditBank = auth()->user()->isOrgOwner($this->organization);

        $this->organization->refresh();

        $payload = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'best_of' => $this->best_of !== '' ? (int) $this->best_of : null,
            'uses_relative_scoring' => (bool) $this->uses_relative_scoring,
            'entry_fee_default' => $this->entry_fee_default !== '' ? (float) $this->entry_fee_default : null,
            'primary_color' => $validated['primary_color'],
            'secondary_color' => $validated['secondary_color'],
            'hero_text' => $validated['hero_text'] ?: null,
            'hero_description' => $validated['hero_description'] ?: null,
            'portal_enabled' => (bool) $this->portal_enabled,
            'season_standings_enabled' => $this->season_standings_enabled,
        ];

        if ($canEditBank) {
            $payload['bank_name'] = $validated['bank_name'] ?: null;
            $payload['bank_account_holder'] = $validated['bank_account_holder'] ?: null;
            $payload['bank_account_number'] = $validated['bank_account_number'] ?: null;
            $payload['bank_branch_code'] = $validated['bank_branch_code'] ?: null;
        }

        if ($this->logo) {
            if ($this->organization->logo_path) {
                Storage::disk('public')->delete($this->organization->logo_path);
            }
            $payload['logo_path'] = $this->logo->store('org-logos/' . $this->organization->id, 'public');
            $this->logo = null;
        }

        $this->organization->update($payload);

        if (! $this->season_standings_enabled) {
            ShootingMatch::query()
                ->where('organization_id', $this->organization->id)
                ->update(['season_id' => null]);
        }

        Flux::toast('Settings saved.', variant: 'success');
    }
}; ?>

<div class="space-y-6 max-w-2xl">
    <div>
        <flux:heading size="xl">Settings</flux:heading>
        <p class="mt-1 text-sm text-muted">{{ $organization->name }} — Configure organization settings.</p>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <h2 class="text-lg font-semibold text-primary">Organization Details</h2>

            <flux:input wire:model="name" label="Name" required />
            <flux:textarea wire:model="description" label="Description" placeholder="What is this organization about..." rows="3" />

            <flux:separator />

            <h2 class="text-lg font-semibold text-primary">Logo</h2>
            <div class="flex items-start gap-4">
                @if($organization->logo_path)
                    <div class="flex-shrink-0">
                        <img src="{{ $organization->logoUrl() }}" alt="Logo" class="h-16 w-16 rounded-lg border border-border object-cover" />
                    </div>
                @endif
                <div class="flex-1 space-y-2">
                    <input type="file" wire:model="logo" accept="image/*"
                           class="block w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-4 file:py-2 file:text-sm file:font-medium file:text-secondary hover:file:bg-surface-2/80" />
                    @error('logo') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                    @if($organization->logo_path)
                        <button type="button" wire:click="removeLogo" wire:confirm="Remove the current logo?" class="text-xs text-red-400 hover:underline">Remove logo</button>
                    @endif
                    <p class="text-xs text-muted">Max 4 MB. Shown on event cards and the events page.</p>
                </div>
            </div>

            <flux:separator />

            <h2 class="text-lg font-semibold text-primary">Leaderboard</h2>
            <div class="max-w-xs">
                <flux:input wire:model="best_of" label="Best-of (X scores)" type="number" min="1" placeholder="Leave empty to count all" />
                <p class="mt-1 text-xs text-muted">Leaderboard will rank shooters by their top X match scores. Leave empty to sum all scores.</p>
            </div>

            <div class="flex items-start gap-3 rounded-lg border border-border bg-surface-2/30 p-4">
                <input type="checkbox" wire:model="uses_relative_scoring" id="uses_relative_scoring"
                       class="mt-1 rounded border-border bg-surface-2 text-accent focus:ring-red-500">
                <div>
                    <label for="uses_relative_scoring" class="text-sm font-medium text-secondary">Relative (out-of-100) scoring</label>
                    <p class="mt-1 text-xs text-muted">On: each match is normalised — the match winner gets the match's point value (100 for regulars, 200 for finals) and everyone else is scaled against them to the closest whole number. Off: raw weighted totals are rounded and summed as-is.</p>
                </div>
            </div>

            <div class="flex items-start gap-3 rounded-lg border border-border bg-surface-2/30 p-4">
                <input type="checkbox" wire:model="season_standings_enabled" id="season_standings_enabled"
                       class="mt-1 rounded border-border bg-surface-2 text-accent focus:ring-red-500">
                <div>
                    <label for="season_standings_enabled" class="text-sm font-medium text-secondary">Season standings</label>
                    <p class="mt-1 text-xs text-muted">When enabled, matches can be linked to a season for aggregate standings in the admin tools. Turn off if you only run standalone events. Disabling clears season links for this organization’s matches.</p>
                </div>
            </div>

            <flux:separator />

            <h2 class="text-lg font-semibold text-primary">Defaults</h2>
            <div class="max-w-xs">
                <flux:input wire:model="entry_fee_default" label="Default Entry Fee (ZAR)" type="number" step="0.01" min="0" placeholder="Leave empty for free" />
                <p class="mt-1 text-xs text-muted">New matches will default to this fee.</p>
            </div>

            <flux:separator />

            <h2 class="text-lg font-semibold text-primary">Public portal</h2>
            <p class="text-sm text-muted">Your organization’s public portal is included at no charge. Turn it on when you are ready to share matches and results on a branded page.</p>

            <div class="flex items-center gap-3">
                <input type="checkbox" wire:model="portal_enabled" id="portal_enabled"
                       class="rounded border-border bg-surface-2 text-accent focus:ring-red-500">
                <label for="portal_enabled" class="text-sm text-secondary">Enable public portal</label>
            </div>
            @if($organization->canAccessPortal())
                <p class="text-xs text-muted">
                    Portal URL: <a href="{{ route('portal.home', $organization) }}" target="_blank" class="text-accent hover:text-accent font-mono">{{ route('portal.home', $organization) }}</a>
                </p>
            @endif

            @if(! $organization->hasPortalAdRights())
                <div class="rounded-lg border border-border bg-surface-2/40 px-4 py-3 text-sm text-secondary">
                    <strong class="text-primary">Portal sponsor slots</strong> are controlled by DeadCenter on your portal unless you purchase <span class="text-primary">advertising rights</span>. Contact us to discuss branded placements on your public portal.
                </div>
            @else
                <p class="text-sm text-muted">
                    You control approved sponsor placements on your portal.
                    <a href="{{ route('org.portal-sponsors', $organization) }}" class="text-accent hover:underline font-medium">Manage portal sponsors</a>
                </p>
            @endif

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-secondary mb-1">Primary Color</label>
                    <div class="flex items-center gap-2">
                        <input type="color" wire:model.live="primary_color" class="h-10 w-14 cursor-pointer rounded border border-border bg-surface-2">
                        <flux:input wire:model.live="primary_color" class="flex-1" />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-secondary mb-1">Secondary Color</label>
                    <div class="flex items-center gap-2">
                        <input type="color" wire:model.live="secondary_color" class="h-10 w-14 cursor-pointer rounded border border-border bg-surface-2">
                        <flux:input wire:model.live="secondary_color" class="flex-1" />
                    </div>
                </div>
            </div>

            <flux:input wire:model="hero_text" label="Hero Heading" placeholder="e.g. Royal Flush Competition 2026" />
            <flux:textarea wire:model="hero_description" label="Hero Description" placeholder="A brief description shown on the portal landing page..." rows="2" />

            {{-- Preview swatch --}}
            <div class="rounded-lg p-4 border border-border" style="background-color: {{ $secondary_color }};">
                <p class="text-sm font-bold" style="color: {{ $primary_color }};">{{ $hero_text ?: $organization->name }}</p>
                <p class="text-xs text-muted mt-1">Color preview</p>
            </div>
        </div>

        @if(auth()->user()->isOrgOwner($organization))
            <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
                <h2 class="text-lg font-semibold text-primary">Banking Details</h2>
                <p class="text-sm text-muted">Only organization owners can view or edit these. Shown where payment instructions are displayed for your organization (e.g. portal registration).</p>

                <flux:input wire:model="bank_name" label="Bank name" placeholder="e.g. FNB" />
                <flux:input wire:model="bank_account_holder" label="Account holder" placeholder="Name on the account" />
                <flux:input wire:model="bank_account_number" label="Account number" placeholder="e.g. 62000000000" />
                <flux:input wire:model="bank_branch_code" label="Branch code" placeholder="e.g. 250655" />

                <div class="flex justify-end pt-2">
                    <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover">Save Settings</flux:button>
                </div>
            </div>
        @else
            <div class="rounded-xl border border-dashed border-border bg-surface/50 p-6">
                <p class="text-sm text-muted">Banking details are managed by the organization owner. Match directors and range officers can still run matches and squadding.</p>
                <div class="flex justify-end pt-4">
                    <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover">Save Settings</flux:button>
                </div>
            </div>
        @endif
    </form>

    {{-- Info --}}
    <div class="rounded-xl border border-border bg-surface p-6 space-y-2">
        <h2 class="text-sm font-semibold text-muted">Organization Info</h2>
        <div class="grid grid-cols-2 gap-2 text-sm">
            <span class="text-muted">Type</span>
            <span class="text-primary capitalize">{{ $organization->type }}</span>
            <span class="text-muted">Slug</span>
            <span class="text-primary font-mono text-xs">{{ $organization->slug }}</span>
            <span class="text-muted">Status</span>
            <span class="text-primary capitalize">{{ $organization->status }}</span>
            <span class="text-muted">Created</span>
            <span class="text-primary">{{ $organization->created_at->format('d M Y') }}</span>
            @if($organization->parent)
                <span class="text-muted">Parent</span>
                <span class="text-primary">{{ $organization->parent->name }}</span>
            @endif
        </div>
    </div>
</div>
