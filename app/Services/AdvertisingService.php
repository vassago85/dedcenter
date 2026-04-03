<?php

namespace App\Services;

use App\Enums\AdvertisingMode;
use App\Enums\MdPackageStatus;
use App\Enums\PlacementKey;
use App\Enums\SponsorScope;
use App\Models\ShootingMatch;
use App\Models\Sponsor;
use App\Models\SponsorAssignment;
use Illuminate\Support\Facades\DB;

class AdvertisingService
{
    /**
     * MD takes the full event visibility package and assigns a brand.
     */
    public function takeFullPackage(ShootingMatch $match, Sponsor $brand): void
    {
        throw_if(
            $match->hasIndividualPlacements(),
            \LogicException::class,
            'Cannot take full package while individual placements exist.'
        );

        DB::transaction(function () use ($match, $brand) {
            $match->update([
                'advertising_mode' => AdvertisingMode::SelfManaged,
                'md_package_status' => MdPackageStatus::Taken,
                'full_package_brand_id' => $brand->id,
            ]);

            $this->upsertAllPlacements($match, $brand);
        });
    }

    /**
     * Admin sells the full package to a public advertiser.
     */
    public function sellFullPackage(ShootingMatch $match, Sponsor $brand): void
    {
        throw_if(
            $match->hasIndividualPlacements(),
            \LogicException::class,
            'Cannot sell full package while individual placements exist.'
        );

        DB::transaction(function () use ($match, $brand) {
            $match->update([
                'full_package_brand_id' => $brand->id,
            ]);

            $this->upsertAllPlacements($match, $brand);
        });
    }

    /**
     * MD declines the package, opening placements to public.
     */
    public function declineMdPackage(ShootingMatch $match): void
    {
        $match->update([
            'md_package_status' => MdPackageStatus::Declined,
            'advertising_mode' => AdvertisingMode::PublicOpen,
        ]);
    }

    /**
     * Sell an individual placement to a brand (public/admin only).
     */
    public function sellIndividualPlacement(ShootingMatch $match, PlacementKey $key, Sponsor $brand): void
    {
        throw_if(
            $match->isFullPackageSold(),
            \LogicException::class,
            'Cannot sell individual placements when full package is sold.'
        );

        throw_if(
            ! in_array($key, PlacementKey::advertisingPlacements()),
            \LogicException::class,
            'Invalid placement key for advertising.'
        );

        $existing = SponsorAssignment::forMatch($match->id)
            ->forPlacement($key)
            ->whereNotNull('sponsor_id')
            ->active()
            ->first();

        throw_if(
            $existing,
            \LogicException::class,
            "Placement {$key->value} is already sold."
        );

        SponsorAssignment::create([
            'sponsor_id' => $brand->id,
            'scope_type' => SponsorScope::Match,
            'scope_id' => $match->id,
            'placement_key' => $key,
            'active' => true,
            'display_order' => 0,
        ]);
    }

    /**
     * Clear a single placement assignment for a match.
     */
    public function clearPlacement(ShootingMatch $match, PlacementKey $key): void
    {
        SponsorAssignment::forMatch($match->id)
            ->forPlacement($key)
            ->delete();
    }

    /**
     * Clear all advertising placements and reset the match advertising state.
     */
    public function clearAllPlacements(ShootingMatch $match): void
    {
        DB::transaction(function () use ($match) {
            SponsorAssignment::forMatch($match->id)
                ->whereIn('placement_key', PlacementKey::advertisingPlacements())
                ->delete();

            $match->update([
                'full_package_brand_id' => null,
            ]);
        });
    }

    /**
     * Change the brand for a full-package match (MD or admin).
     */
    public function changeBrand(ShootingMatch $match, Sponsor $brand): void
    {
        throw_unless(
            $match->isFullPackageSold(),
            \LogicException::class,
            'No full package to change brand on.'
        );

        DB::transaction(function () use ($match, $brand) {
            $match->update(['full_package_brand_id' => $brand->id]);
            $this->upsertAllPlacements($match, $brand);
        });
    }

    /**
     * Create or update all 3 advertising placement assignments for one brand.
     */
    private function upsertAllPlacements(ShootingMatch $match, Sponsor $brand): void
    {
        foreach (PlacementKey::advertisingPlacements() as $key) {
            SponsorAssignment::updateOrCreate(
                [
                    'scope_type' => SponsorScope::Match,
                    'scope_id' => $match->id,
                    'placement_key' => $key,
                ],
                [
                    'sponsor_id' => $brand->id,
                    'active' => true,
                    'display_order' => 0,
                ]
            );
        }
    }
}
