<?php

namespace App\Livewire;

use App\Enums\MatchStatus;
use App\Enums\Province;
use App\Models\Organization;
use App\Models\ShootingMatch;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class EventsListing extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'upcoming';

    #[Url]
    public string $search = '';

    #[Url]
    public string $eventType = '';

    #[Url]
    public string $province = '';

    #[Url]
    public string $organizationId = '';

    public function updatedTab(): void { $this->resetPage(); }
    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedEventType(): void { $this->resetPage(); }
    public function updatedProvince(): void { $this->resetPage(); }
    public function updatedOrganizationId(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->reset(['search', 'eventType', 'province', 'organizationId']);
        $this->resetPage();
    }

    public function render()
    {
        if ($this->tab === 'my_events' && ! auth()->check()) {
            $this->tab = 'upcoming';
        }

        // Shared filter closure so the tab counts reflect the SAME
        // search / type / province / organization filters as the list
        // below them. Previously the counts were computed globally, so
        // filtering by one org showed e.g. "Past Results 14" while the
        // filtered list held 2, and a match left Active in another org
        // showed "Live Now 1" over an empty list.
        $applyFilters = function ($q) {
            if ($this->search !== '') {
                $term = '%' . $this->search . '%';
                $q->where(fn ($qq) => $qq->where('name', 'like', $term)->orWhere('location', 'like', $term));
            }
            if ($this->eventType !== '') {
                if ($this->eventType === 'royal_flush') {
                    $q->where('royal_flush_enabled', true);
                } else {
                    $q->where('scoring_type', $this->eventType);
                }
            }
            if ($this->province !== '') {
                $q->where('province', $this->province);
            }
            if ($this->organizationId !== '') {
                $q->where('organization_id', (int) $this->organizationId);
            }

            return $q;
        };

        $query = ShootingMatch::query()
            ->with('organization')
            ->withCount(['registrations', 'shooters']);

        match ($this->tab) {
            'upcoming' => $query->whereIn('status', [
                MatchStatus::PreRegistration,
                MatchStatus::RegistrationOpen,
                MatchStatus::RegistrationClosed,
                MatchStatus::SquaddingOpen,
                MatchStatus::SquaddingClosed,
                MatchStatus::Ready,
            ])->orderByRaw("CASE WHEN featured_status = 'active' THEN 0 ELSE 1 END")->orderBy('date'),

            'live' => $query->where('status', MatchStatus::Active)
                ->orderByRaw("CASE WHEN featured_status = 'active' THEN 0 ELSE 1 END")->orderBy('date'),

            'my_events' => $query->whereHas('registrations', fn ($r) => $r->where('user_id', auth()->id()))
                ->orderByDesc('date'),

            'past' => $query->where('status', MatchStatus::Completed)
                ->orderByRaw("CASE WHEN featured_status = 'active' THEN 0 ELSE 1 END")->orderByDesc('date'),

            default => $query->whereIn('status', [
                MatchStatus::PreRegistration, MatchStatus::RegistrationOpen,
                MatchStatus::RegistrationClosed, MatchStatus::SquaddingOpen,
                MatchStatus::SquaddingClosed, MatchStatus::Ready,
            ])->orderBy('date'),
        };

        $applyFilters($query);

        $matches = $query->paginate(12);
        $organizations = Organization::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        $baseCounts = fn () => $applyFilters(ShootingMatch::where('status', '!=', MatchStatus::Draft));
        $upcomingCount = $baseCounts()->whereIn('status', [
            MatchStatus::PreRegistration, MatchStatus::RegistrationOpen,
            MatchStatus::RegistrationClosed, MatchStatus::SquaddingOpen,
            MatchStatus::SquaddingClosed, MatchStatus::Ready,
        ])->count();
        $liveCount = $baseCounts()->where('status', MatchStatus::Active)->count();
        $completedCount = $baseCounts()->where('status', MatchStatus::Completed)->count();
        $myEventsCount = auth()->check()
            ? ShootingMatch::whereHas('registrations', fn ($q) => $q->where('user_id', auth()->id()))->count()
            : 0;

        return view('livewire.events-listing', [
            'matches'        => $matches,
            'organizations'  => $organizations,
            'provinces'      => Province::cases(),
            'upcomingCount'  => $upcomingCount,
            'liveCount'      => $liveCount,
            'completedCount' => $completedCount,
            'myEventsCount'  => $myEventsCount,
        ]);
    }
}
