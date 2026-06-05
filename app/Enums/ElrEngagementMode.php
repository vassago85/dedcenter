<?php

namespace App\Enums;

enum ElrEngagementMode: string
{
    /**
     * Shooter engages one target at a time, firing all shots on a target
     * before advancing to the next target.
     */
    case TargetByTarget = 'target_by_target';

    /**
     * Shooter engages all assigned targets as one continuous string.
     * The full string of shots is scored on a single screen before moving
     * to the next shooter.
     */
    case FullString = 'full_string';

    /**
     * Team-based gong sequence (Peregrine team event). Two shooters per
     * team; calibre class (Minor/Major) gates which gongs each shooter
     * engages. For every shared gong, shooter 1 fires 3 shots, then
     * shooter 2 fires 3 shots, before advancing to the next gong. A
     * per-team countdown timer bounds the team's turn, and points use
     * impact-based multipliers (a miss never consumes a multiplier slot).
     */
    case TeamSequence = 'team_sequence';

    public function label(): string
    {
        return match ($this) {
            self::TargetByTarget => 'Target by target',
            self::FullString => 'Full string',
            self::TeamSequence => 'Team gong sequence',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::TargetByTarget => 'Shooter fires all shots on one target, then moves to the next target.',
            self::FullString => 'Shooter engages all assigned targets as one string; the whole string is scored on one screen.',
            self::TeamSequence => 'Two-shooter teams. Calibre class gates each shooter\'s gongs; per shared gong, shooter 1 fires 3 then shooter 2 fires 3. Per-team timer and impact-based scoring.',
        };
    }

    public function isTeamSequence(): bool
    {
        return $this === self::TeamSequence;
    }
}
