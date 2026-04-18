<?php

namespace App\Services;

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\TargetSet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates a new Royal Flush match for an organization with the canonical
 * 400/500/600/700m × 5-gong preset applied.
 *
 * Gong multipliers (G1 biggest → G5 smallest): 1.00, 1.25, 1.50, 1.75, 2.00
 * Distance multiplier: distance_meters / 100   (i.e. 4, 5, 6, 7)
 * Score per hit = distance_multiplier × gong.multiplier
 */
class RoyalFlushMatchBuilder
{
    /** @var array<int, int> */
    public const DISTANCES = [400, 500, 600, 700];

    /** @var array<int, string>  gong number (1..5) → multiplier string */
    public const GONG_MULTIPLIERS = [
        1 => '1.00',
        2 => '1.25',
        3 => '1.50',
        4 => '1.75',
        5 => '2.00',
    ];

    /**
     * Build a brand-new Royal Flush match for `$org` on `$date` (default: today).
     * The match lands in SquaddingOpen, with side_bet and royal_flush both enabled.
     */
    public function createForDate(Organization $org, User $actor, ?Carbon $date = null): ShootingMatch
    {
        $date ??= Carbon::today();

        return DB::transaction(function () use ($org, $actor, $date) {
            $name = sprintf('Royal Flush — %s', $date->format('j F Y'));

            $match = ShootingMatch::create([
                'name' => $name,
                'date' => $date->toDateString(),
                'location' => $org->default_location ?? null,
                'status' => MatchStatus::SquaddingOpen,
                'scoring_type' => 'standard',
                'side_bet_enabled' => true,
                'royal_flush_enabled' => true,
                'scores_published' => false,
                'concurrent_relays' => 2,
                'max_squad_size' => 10,
                'created_by' => $actor->id,
                'organization_id' => $org->id,
            ]);

            $this->applyPresetTo($match);

            return $match;
        });
    }

    /**
     * Idempotently apply the canonical 400/500/600/700m × 5-gong preset
     * (with RF multipliers) to an existing match. Safe to re-run.
     */
    public function applyPresetTo(ShootingMatch $match): void
    {
        foreach (self::DISTANCES as $i => $distance) {
            $ts = TargetSet::firstOrCreate(
                ['match_id' => $match->id, 'distance_meters' => $distance],
                [
                    'label' => "{$distance}m",
                    'distance_multiplier' => $distance / 100,
                    'sort_order' => $i + 1,
                ]
            );

            $ts->fill([
                'label' => "{$distance}m",
                'distance_multiplier' => $distance / 100,
                'sort_order' => $i + 1,
            ])->save();

            $existing = Gong::where('target_set_id', $ts->id)->orderBy('number')->get();
            $byNumber = $existing->keyBy('number');

            for ($n = 1; $n <= 5; $n++) {
                $mult = self::GONG_MULTIPLIERS[$n];
                if ($byNumber->has($n)) {
                    $byNumber[$n]->fill(['label' => "G{$n}", 'multiplier' => $mult])->save();
                    continue;
                }
                Gong::create([
                    'target_set_id' => $ts->id,
                    'number' => $n,
                    'label' => "G{$n}",
                    'multiplier' => $mult,
                ]);
            }
        }
    }
}
