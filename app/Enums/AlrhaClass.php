<?php

namespace App\Enums;

/**
 * ALRHA class of competition. A single ALRHA event runs both classes
 * concurrently on shared relays — a shooter picks exactly one at entry
 * (they cannot compete in both on the same day, see rules §3 and §7).
 *
 * The class lives on the shooter (source of truth once squadded) and on
 * each ELR stage (so one match holds both class stage trees side-by-side).
 * `matches.alrha_class` is legacy: retained only for back-compat with
 * matches that were seeded before dual-class support existed. New
 * matches should leave it null and rely on shooter/stage tags.
 *
 *  - Hunters: 2-person teams; 1000 / 900 / 700 / 600 / 400 m; Cold Bore
 *    Challenge on the 1000 m Springbuck cut-out.
 *  - Varmint: individual; 700 / 600 / 500 / 400 / 300 m; Cold Bore
 *    Challenge on the 700 m Jackal head cut-out.
 */
enum AlrhaClass: string
{
    case Hunters = 'hunters';
    case Varmint = 'varmint';

    public function label(): string
    {
        return match ($this) {
            self::Hunters => 'LR Hunters',
            self::Varmint => 'LR Varmint',
        };
    }

    /**
     * Class-specific scoring distances in metres, ordered furthest → nearest.
     * The first two are the "Far" block (fired at the start of a relay),
     * the remaining three are the "Near" block.
     */
    public function distances(): array
    {
        return match ($this) {
            self::Hunters => [1000, 900, 700, 600, 400],
            self::Varmint => [700, 600, 500, 400, 300],
        };
    }

    /**
     * Distances shot in the initial "far" block for a relay: the top-two
     * distances plus the CBC shot on the class cut-out target.
     */
    public function farBlockDistances(): array
    {
        return array_slice($this->distances(), 0, 2);
    }

    /**
     * Distances shot in the second "near" block: the bottom-three
     * distances after the far block is done.
     */
    public function nearBlockDistances(): array
    {
        return array_slice($this->distances(), 2);
    }

    /**
     * Distance where the Cold Bore Challenge cut-out sits for this class.
     */
    public function coldBoreDistance(): int
    {
        return match ($this) {
            self::Hunters => 1000,
            self::Varmint => 700,
        };
    }

    /**
     * Descriptive cut-out target name used on the printed score card and
     * in the ELR target row (§4 targets).
     */
    public function coldBoreTargetName(): string
    {
        return match ($this) {
            self::Hunters => 'CBC — Springbuck cut-out (1000 m)',
            self::Varmint => 'CBC — Jackal cut-out (700 m)',
        };
    }

    /**
     * Whether this class is scored by team totals as well as individual.
     * Hunters rank both team and individual; Varmint is individual only.
     */
    public function hasTeamScoring(): bool
    {
        return $this === self::Hunters;
    }

    /**
     * Peer-scoring group size (§General Rules — Scoring):
     *  - Hunters: teams score each other in pairs → 2 teams per group.
     *  - Varmint: shooters score each other in groups of three.
     */
    public function peerGroupSize(): int
    {
        return match ($this) {
            self::Hunters => 2, // 2 teams (of 2 shooters) → 4 people total
            self::Varmint => 3, // 3 individual shooters
        };
    }

    /**
     * Category slugs published for the year-end prize tables. Hunters has
     * no Ladies category (§Rules — Ladies & Juniors section for Hunters
     * has no Ladies field entry).
     */
    public function categorySlugs(): array
    {
        return match ($this) {
            self::Hunters => ['open', 'junior'],
            self::Varmint => ['open', 'ladies', 'junior'],
        };
    }
}
