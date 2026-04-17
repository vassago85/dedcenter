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
                MatchStatus::SquaddingClosed,
            ])->orderBy('date'),
        };

        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('location', 'like', $term));
        }

        if ($this->eventType !== '') {
            if ($this->eventType === 'royal_flush') {
                $query->where('royal_flush_enabled', true);
            } else {
                $query->where('scoring_type', $this->eventType);
            }
        }

        if ($this->province !== '') {
            $query->where('province', $this->province);
        }

        if ($this->organizationId !== '') {
            $query->where('organization_id', (int) $this->organizationId);
        }

        $matches = $query->paginate(12);
        $organizations = Organization::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        $baseCounts = ShootingMatch::where('status', '!=', MatchStatus::Draft);
        $upcomingCount = (clone $baseCounts)->whereIn('status', [
            MatchStatus::PreRegistration, MatchStatus::RegistrationOpen,
            MatchStatus::RegistrationClosed, MatchStatus::SquaddingOpen,
            MatchStatus::SquaddingClosed,
        ])->count();
        $liveCount = (clone $baseCounts)->where('status', MatchStatus::Active)->count();
        $completedCount = (clone $baseCounts)->where('status', MatchStatus::Completed)->count();
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
