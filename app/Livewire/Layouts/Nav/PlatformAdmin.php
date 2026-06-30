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
 * lived as inline @php in the Blade x-component. The x-component version was
 * silently stale because Livewire's persistent-layout `wire:navigate` keeps
 * the layout in the DOM and never re-runs its inline PHP, so once the user
 * approved a claim the sidebar badge stayed at "1" until a hard refresh.
 *
 * As a Livewire component we can:
 *   - listen for `moderation-updated`, `contact-submissions-updated`, and
 *     `organization-status-updated` events dispatched by sibling pages and
 *     recompute the counts the instant an admin acts;
 *   - poll every 60s as a safety net so cross-tab activity also reconciles
 *     without requiring a manual refresh.
 */
class PlatformAdmin extends Component
{
    public int $pendingOrgs = 0;

    public int $pendingClaims = 0;

    public int $unreadContacts = 0;

    public function mount(): void
    {
        $this->refreshCounts();
    }

    #[On('moderation-updated')]
    public function onModerationUpdated(): void
    {
        $this->refreshCounts();
    }

    #[On('contact-submissions-updated')]
    public function onContactSubmissionsUpdated(): void
    {
        $this->refreshCounts();
    }

    #[On('organization-status-updated')]
    public function onOrganizationStatusUpdated(): void
    {
        $this->refreshCounts();
    }

    /**
     * Public so wire:poll can hit it without exposing internal state, and so
     * `livewire:navigated` JS can call $wire.refresh() if we ever wire that
     * up. Keeps the three count queries in one place to avoid drift.
     */
    public function refreshCounts(): void
    {
        $this->pendingOrgs = Organization::pending()->count();
        $this->pendingClaims = ShooterAccountClaim::where('status', ShooterClaimStatus::Pending)->count();
        $this->unreadContacts = ContactSubmission::unread()->count();
    }

    public function render()
    {
        return view('livewire.layouts.nav.platform-admin');
    }
}
