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

    public function label(): string
    {
        return match ($this) {
            self::TargetByTarget => 'Target by target',
            self::FullString => 'Full string',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::TargetByTarget => 'Shooter fires all shots on one target, then moves to the next target.',
            self::FullString => 'Shooter engages all assigned targets as one string; the whole string is scored on one screen.',
        };
    }
}
