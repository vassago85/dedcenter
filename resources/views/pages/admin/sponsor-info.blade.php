<?php

use App\Models\Setting;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Brand Info Page')]
    class extends Component {
    public string $overview = '';

    public string $visibility = '';

    public string $matchbook_section = '';

    public string $reach = '';

    public string $tiers = '';

    public string $custom_packages = '';

    public string $contact = '';

    public string $individual_price = '500';

    public string $package_price = '1500';

    public string $access_token = '';

    public function mount(): void
    {
        $this->overview = (string) Setting::get('sponsor_info_overview', '');
        $this->visibility = (string) Setting::get('sponsor_info_visibility', '');
        $this->matchbook_section = (string) Setting::get('sponsor_info_matchbook_section', '');
        $this->reach = (string) Setting::get('sponsor_info_reach', '');
        $this->tiers = (string) Setting::get('sponsor_info_tiers', '');
        $this->custom_packages = (string) Setting::get('sponsor_info_custom_packages', '');
        $this->contact = (string) Setting::get('sponsor_info_contact', '');
        $this->individual_price = (string) Setting::get('advertising_individual_price', '500');
        $this->package_price = (string) Setting::get('advertising_package_price', '1500');
        $this->access_token = (string) (Setting::get('sponsor_info_access_token') ?? '');
    }

    public function save(): void
    {
        $this->validate([
            'overview' => 'nullable|string',
            'visibility' => 'nullable|string',
            'matchbook_section' => 'nullable|string',
            'reach' => 'nullable|string',
            'tiers' => 'nullable|string',
            'custom_packages' => 'nullable|string',
            'contact' => 'nullable|string',
            'individual_price' => 'required|numeric|min:0',
            'package_price' => 'required|numeric|min:0',
        ]);

        Setting::set('sponsor_info_overview', $this->overview);
        Setting::set('sponsor_info_visibility', $this->visibility);
        Setting::set('sponsor_info_matchbook_section', $this->matchbook_section);
        Setting::set('sponsor_info_reach', $this->reach);
        Setting::set('sponsor_info_tiers', $this->tiers);
        Setting::set('sponsor_info_custom_packages', $this->custom_packages);
        Setting::set('sponsor_info_contact', $this->contact);
        Setting::set('advertising_individual_price', $this->individual_price);
        Setting::set('advertising_package_price', $this->package_price);

        Flux::toast('Brand info content saved.', variant: 'success');
    }

    public function regenerateToken(): void
    {
        $this->access_token = Str::random(32);
        Setting::set('sponsor_info_access_token', $this->access_token);

        Flux::toast('Access token regenerated. Update any shared links.', variant: 'success');
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-8">
    <div>
        <flux:heading size="xl">Brand Information Page</flux:heading>
        <p class="mt-2 text-sm text-muted">Edit the private brand-facing content. The page is only accessible via the secret URL below.</p>
    </div>

    <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
        <flux:heading size="lg">Private Link</flux:heading>
        <p class="text-sm text-muted">Share this URL with potential advertisers. Regenerating invalidates previous links.</p>

        <flux:input wire:model="access_token" label="Access token" readonly />

        <div class="flex flex-wrap items-center gap-3">
            <flux:button type="button" wire:click="regenerateToken" variant="ghost">Regenerate token</flux:button>

            @if($access_token !== '')
                <flux:button href="{{ route('sponsor-info.show', $access_token) }}" target="_blank" variant="primary">
                    Preview in new tab
                </flux:button>
            @else
                <flux:button type="button" disabled variant="ghost">Preview (generate a token first)</flux:button>
            @endif
        </div>
    </div>

    <form wire:submit="save" class="space-y-8">
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <flux:heading size="lg">Advertising Pricing</flux:heading>
            <p class="text-sm text-muted">These prices appear on the public advertising page and are used as defaults for new matches.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="individual_price" label="Individual placement (R)" type="number" min="0" step="1" />
                <flux:input wire:model="package_price" label="Full package — all 3 placements (R)" type="number" min="0" step="1" />
            </div>
        </div>

        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <flux:heading size="lg">Overview</flux:heading>
            <p class="text-sm text-muted">What DeadCenter is — shown at the top of the advertising page.</p>
            <flux:textarea wire:model="overview" rows="8" />
        </div>

        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <flux:heading size="lg">Visibility Locations</flux:heading>
            <p class="text-sm text-muted">Where brands appear across the platform.</p>
            <flux:textarea wire:model="visibility" rows="6" />
        </div>

        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <flux:heading size="lg">How It Works</flux:heading>
            <p class="text-sm text-muted">Explains the MD-first advertising workflow to potential brands.</p>
            <flux:textarea wire:model="matchbook_section" rows="6" />
        </div>

        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <flux:heading size="lg">Reach & Footprint</flux:heading>
            <p class="text-sm text-muted">Locations, regions, and events.</p>
            <flux:textarea wire:model="reach" rows="6" />
        </div>

        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <flux:heading size="lg">Pricing Details</flux:heading>
            <p class="text-sm text-muted">Additional tier or pricing description text.</p>
            <flux:textarea wire:model="tiers" rows="6" />
        </div>

        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <flux:heading size="lg">Custom Arrangements</flux:heading>
            <p class="text-sm text-muted">Multi-event, season, or bespoke deals.</p>
            <flux:textarea wire:model="custom_packages" rows="6" />
        </div>

        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <flux:heading size="lg">Contact Information</flux:heading>
            <p class="text-sm text-muted">How brands can get in touch.</p>
            <flux:textarea wire:model="contact" rows="6" />
        </div>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">Save content</flux:button>
        </div>
    </form>
</div>
