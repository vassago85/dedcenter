<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'name',
        'max_size',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'max_size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(ShootingMatch::class, 'match_id');
    }

    public function shooters(): HasMany
    {
        return $this->hasMany(Shooter::class);
    }

    public function effectiveMaxSize(): int
    {
        return $this->max_size ?? $this->match?->team_size ?? 3;
    }

    public function isFull(): bool
    {
        return $this->shooters()->count() >= $this->effectiveMaxSize();
    }

    public function spotsRemaining(): int
    {
        return max(0, $this->effectiveMaxSize() - $this->shooters()->count());
    }

    public function totalScore(): float
    {
        return (float) $this->shooters->sum(fn (Shooter $s) => $s->total_score);
    }

    /**
     * Sum of this team's members' ELR points.
     *
     * `Shooter::$total_score` is a standard-gong accessor — it returns 0 for
     * ELR matches. Pass an `ElrShot` collection (indexed by shooter_id) to
     * total an ELR team. We accept an in-memory map rather than running
     * fresh queries here so the per-match team leaderboard can build all
     * teams' totals in one query above the loop.
     *
     * Expected $shotsByShooter shape: `Collection<int, Collection<ElrShot>>`.
     */
    public function elrTotalScore(\Illuminate\Support\Collection $shotsByShooter): float
    {
        $sum = 0.0;
        foreach ($this->shooters as $member) {
            $shots = $shotsByShooter->get($member->id, collect());
            foreach ($shots as $shot) {
                if (($shot->result?->value ?? $shot->result) === 'hit') {
                    $sum += (float) $shot->points_awarded;
                }
            }
        }

        return round($sum, 2);
    }

    /**
     * Derive a division-pairing category label from the team's members.
     *
     * For the Peregrine ELR Challenge JD runs pair teams: "Minor/Minor",
     * "Minor/Major" (a.k.a. "Mixed"), or "Major/Major". For teams larger
     * than 2 we degrade gracefully to a compact composition count
     * (e.g. "2 Minor + 1 Major"). Members with no division assigned are
     * tagged "?" so a half-configured roster is obvious in the UI.
     *
     * Returns null when no member of the team has a division — useful for
     * non-divisioned matches like Forster 2 Mile so the column hides cleanly.
     */
    public function divisionCategoryLabel(): ?string
    {
        $names = $this->shooters
            ->map(fn (Shooter $s) => $s->division?->name)
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            return null;
        }

        // Pair teams (the JD case) → the canonical short label.
        if ($this->shooters->count() === 2) {
            $sorted = $names->sort()->values();
            // Pad to 2 entries — a half-assigned pair still surfaces "?/Major" etc.
            $a = $sorted->get(0) ?? '?';
            $b = $sorted->get(1) ?? '?';

            return $a === $b ? "{$a}/{$a}" : "{$a}/{$b}";
        }

        // Larger or smaller teams → composition count, deterministic order
        // (alphabetical by division name) so the same team always renders
        // the same label across the UI.
        $counts = $names->countBy()->sortKeys();
        return $counts
            ->map(fn ($count, $name) => "{$count} {$name}")
            ->values()
            ->implode(' + ');
    }
}
