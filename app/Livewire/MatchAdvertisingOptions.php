<?php

namespace App\Livewire;

use App\Enums\AdvertisingMode;
use App\Enums\MdPackageStatus;
use App\Enums\PlacementKey;
use App\Models\Setting;
use App\Models\ShootingMatch;
use App\Models\Sponsor;
use App\Services\AdvertisingService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MatchAdvertisingOptions extends Component
{
    public ShootingMatch $match;

    public string $selectedBrandId = '';

    public function mount(ShootingMatch $match): void
    {
        $this->match = $match;
        $this->selectedBrandId = (string) ($match->full_package_brand_id ?? '');
    }

    public function takePackage(): void
    {
        if (! $this->selectedBrandId) {
            Flux::toast('Please select a brand first.', variant: 'danger');
            return;
        }

        $brand = Sponsor::active()->find((int) $this->selectedBrandId);
        if (! $brand) {
            Flux::toast('Brand not found or inactive.', variant: 'danger');
            return;
        }

        try {
            app(AdvertisingService::class)->takeFullPackage($this->match, $brand);
            $this->match->refresh();
            Flux::toast('Full package activated — all placements assigned.', variant: 'success');
        } catch (\LogicException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');
        }
    }

    public function declinePackage(): void
    {
        app(AdvertisingService::class)->declineMdPackage($this->match);
        $this->match->refresh();
        Flux::toast('Package declined — placements are now available to public advertisers.', variant: 'success');
    }

    public function changeBrand(): void
    {
        if (! $this->selectedBrandId) {
            Flux::toast('Please select a brand.', variant: 'danger');
            return;
        }

        $brand = Sponsor::active()->find((int) $this->selectedBrandId);
        if (! $brand) {
            Flux::toast('Brand not found or inactive.', variant: 'danger');
            return;
        }

        try {
            app(AdvertisingService::class)->changeBrand($this->match, $brand);
            $this->match->refresh();
            Flux::toast('Brand updated across all placements.', variant: 'success');
        } catch (\LogicException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');
        }
    }

    public function releasePackage(): void
    {
        app(AdvertisingService::class)->clearAllPlacements($this->match);
        $this->match->update([
            'advertising_mode' => AdvertisingMode::PublicOpen,
            'md_package_status' => MdPackageStatus::Declined,
        ]);
        $this->match->refresh();
        $this->selectedBrandId = '';
        Flux::toast('All placements released.', variant: 'success');
    }

    public function render(): View
    {
        return view('livewire.match-advertising-options', [
            'advertisingEnabled' => (bool) Setting::get('advertising_enabled', false),
            'brands' => Sponsor::active()->orderBy('name')->get(),
            'currentBrand' => $this->match->full_package_brand_id
                ? Sponsor::find($this->match->full_package_brand_id)
                : null,
            'advertisingPlacements' => PlacementKey::advertisingPlacements(),
        ]);
    }
}
