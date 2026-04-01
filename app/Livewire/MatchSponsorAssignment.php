<?php

namespace App\Livewire;

use App\Enums\PlacementKey;
use App\Enums\SponsorScope;
use App\Models\ShootingMatch;
use App\Models\Sponsor;
use App\Models\SponsorAssignment;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MatchSponsorAssignment extends Component
{
    public ShootingMatch $match;

    public array $placementSelections = [];

    public function mount(ShootingMatch $match): void
    {
        $this->match = $match;
        $this->loadSelections();
    }

    protected function loadSelections(): void
    {
        foreach (PlacementKey::matchDirectorPlacements() as $key) {
            $this->placementSelections[$key->value] = '';
        }

        $assignments = SponsorAssignment::query()
            ->forMatch($this->match->id)
            ->get()
            ->groupBy(fn ($a) => $a->placement_key->value);

        foreach (PlacementKey::matchDirectorPlacements() as $key) {
            $first = ($assignments->get($key->value) ?? collect())->first();
            $this->placementSelections[$key->value] = $first ? (string) $first->sponsor_id : '';
        }
    }

    public function savePlacement(string $placementValue): void
    {
        $placement = PlacementKey::from($placementValue);
        $selected = trim($this->placementSelections[$placementValue] ?? '');

        SponsorAssignment::query()
            ->where('scope_type', SponsorScope::Match)
            ->where('scope_id', $this->match->id)
            ->where('placement_key', $placement)
            ->delete();

        if ($selected !== '') {
            $sponsor = Sponsor::where('assignable_by_match_director', true)->active()->find((int) $selected);
            if (! $sponsor) {
                Flux::toast('Sponsor cannot be assigned.', variant: 'danger');
                $this->loadSelections();
                return;
            }

            SponsorAssignment::create([
                'sponsor_id' => $sponsor->id,
                'scope_type' => SponsorScope::Match,
                'scope_id' => $this->match->id,
                'placement_key' => $placement,
                'active' => true,
                'display_order' => 0,
            ]);
        }

        Flux::toast('Sponsor assignment updated.', variant: 'success');
    }

    public function render(): View
    {
        return view('livewire.match-sponsor-assignment', [
            'sponsors' => Sponsor::assignableByMatchDirector()->active()->orderBy('name')->get(),
            'placementLabels' => [
                PlacementKey::MatchLeaderboard->value => 'Leaderboard Sponsor',
                PlacementKey::MatchResults->value => 'Results Sponsor',
                PlacementKey::MatchScoring->value => 'Scoring Branding',
                PlacementKey::MatchExports->value => 'Export Sponsor',
                PlacementKey::MatchMatchbook->value => 'Match Book Sponsor',
            ],
            'matchPlacements' => PlacementKey::matchDirectorPlacements(),
        ]);
    }
}
