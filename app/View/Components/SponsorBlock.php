<?php

namespace App\View\Components;

use App\Enums\PlacementKey;
use App\Models\SponsorAssignment;
use App\Services\SponsorPlacementResolver;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class SponsorBlock extends Component
{
    public ?SponsorAssignment $assignment;

    public function __construct(
        public string $placement,
        public ?int $matchId = null,
        public ?int $matchBookId = null,
        public string $variant = 'inline',
    ) {
        $resolver = app(SponsorPlacementResolver::class);

        try {
            $key = PlacementKey::from($this->placement);
            $this->assignment = $resolver->resolve($key, $this->matchId, $this->matchBookId);
        } catch (\ValueError) {
            $this->assignment = null;
        }
    }

    public function shouldRender(): bool
    {
        return $this->assignment !== null && $this->assignment->sponsor !== null;
    }

    public function render(): View
    {
        return view('components.sponsor-assignment', [
            'assignment' => $this->assignment,
            'variant' => $this->variant,
        ]);
    }
}
