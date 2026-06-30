<?php

namespace App\Livewire\Layouts\Nav;

use App\Enums\ShooterClaimStatus;
use App\Models\ContactSubmission;
use App\Models\Organization;
use App\Models\ShooterAccountClaim;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Platform admin sidebar — owns the three "X pending" badges that previously
 * lived as inline @php in the Blade x-component.
 *
 * The counts are computed FRESH in render() rather than stored as wire
 * properties. That's deliberate: wire:navigate caches a full HTML snapshot of
 * each visited page (sidebar included) AND the component's property payload,
 * so a count stored in a property would be restored stale on back-navigation
 * — exactly the "badge stuck at 1 even though there are 0 pending claims" bug.
 * By computing in render(), every re-render (poll, moderation event, the
 * livewire:navigated $refresh hook, or normal hydration) reflects the live
 * database, so the badge can never lie.
 *
 * The #[On] listeners simply force a re-render the instant an admin resolves
 * something on a sibling page; wire:poll is a cross-tab safety net.
 */
class PlatformAdmin extends Component
{
    /**
     * Receiving any of these events triggers a Livewire round-trip, which
     * re-runs render() and therefore recomputes the badge counts. The body is
     * intentionally empty — the re-render is the whole point.
     */
    #[On('moderation-updated')]
    #[On('contact-submissions-updated')]
    #[On('organization-status-updated')]
    #[On('refresh-admin-nav-counts')]
    public function refreshCounts(): void
    {
    }

    public function render()
    {
        return view('livewire.layouts.nav.platform-admin', [
            'pendingOrgs' => Organization::pending()->count(),
            'pendingClaims' => ShooterAccountClaim::where('status', ShooterClaimStatus::Pending)->count(),
            'unreadContacts' => ContactSubmission::unread()->count(),
        ]);
    }
}
