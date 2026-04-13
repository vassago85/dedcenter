<?php

namespace App\Services;

use App\Enums\PlacementKey;
use App\Enums\SponsorScope;
use App\Models\Organization;
use App\Models\SponsorAssignment;
use Illuminate\Database\Eloquent\Builder;

class PortalAdResolver
{
    /**
     * Resolve a single sponsor assignment for an organization portal placement.
     *
     * When the org lacks portal ad rights, only platform-controlled assignments apply
     * (global platform rows or platform rows targeted via metadata.target_organization_id).
     * Organization-scoped assignments are ignored.
     */
    public function resolve(Organization $organization, string $placementKey): ?SponsorAssignment
    {
        $key = PlacementKey::tryFrom($placementKey);
        if (! $key || ! $key->isPortalPlacement()) {
            return null;
        }

        if ($organization->hasPortalAdRights()) {
            $orgAssignment = $this->firstValidOrganizationAssignment($organization->id, $key);
            if ($orgAssignment) {
                return $orgAssignment;
            }
        }

        return $this->firstValidPlatformPortalAssignment($organization->id, $key);
    }

    /**
     * Site-wide landing placements (not tied to an organization).
     */
    public function resolveSiteWide(string $placementKey): ?SponsorAssignment
    {
        $key = PlacementKey::tryFrom($placementKey);
        if (! $key || ! $key->isLandingPlacement()) {
            return null;
        }

        return $this->validSponsorAssignmentBase()
            ->where('placement_key', $key)
            ->where('scope_type', SponsorScope::Platform)
            ->whereNull('scope_id')
            ->orderBy('display_order')
            ->orderBy('id')
            ->first();
    }

    protected function firstValidOrganizationAssignment(int $organizationId, PlacementKey $key): ?SponsorAssignment
    {
        return $this->validSponsorAssignmentBase()
            ->where('placement_key', $key)
            ->where('scope_type', SponsorScope::Organization)
            ->where('scope_id', $organizationId)
            ->orderBy('display_order')
            ->orderBy('id')
            ->first();
    }

    /**
     * Prefer platform assignment targeted to this org; else global platform (no target id).
     */
    protected function firstValidPlatformPortalAssignment(int $organizationId, PlacementKey $key): ?SponsorAssignment
    {
        $targeted = $this->validSponsorAssignmentBase()
            ->where('placement_key', $key)
            ->where('scope_type', SponsorScope::Platform)
            ->whereNull('scope_id')
            ->where('metadata->target_organization_id', $organizationId)
            ->orderBy('display_order')
            ->orderBy('id')
            ->first();

        if ($targeted) {
            return $targeted;
        }

        return $this->validSponsorAssignmentBase()
            ->where('placement_key', $key)
            ->where('scope_type', SponsorScope::Platform)
            ->whereNull('scope_id')
            ->whereNull('metadata->target_organization_id')
            ->orderBy('display_order')
            ->orderBy('id')
            ->first();
    }

    protected function validSponsorAssignmentBase(): Builder
    {
        $now = now();

        return SponsorAssignment::query()
            ->with('sponsor')
            ->whereHas('sponsor', function ($q) use ($now) {
                $q->where('active', true)
                    ->where(fn ($q2) => $q2->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                    ->where(fn ($q2) => $q2->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
            })
            ->where('active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }
}
