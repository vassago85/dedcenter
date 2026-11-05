<?php

namespace App\Services;

use App\Models\MatchRegistration;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Match-day edits to cartridge, division, team, and squad.
 *
 * Staff can change these at any match status. Scores stay on the shooter
 * row. A registration, when one exists, is updated with the shooter so a
 * later confirm cannot copy the old division or cartridge back.
 */
class ShooterRosterService
{
    /**
     * @param  array{caliber?: string|null, division_id?: int|null, team_id?: int|null, team?: string|null, squad_id?: int|null}  $changes
     */
    public function update(ShootingMatch $match, Shooter $shooter, array $changes): Shooter
    {
        $belongs = $match->shooters()->whereKey($shooter->id)->exists();
        if (! $belongs) {
            throw new RuntimeException('Shooter not found in this match.');
        }

        $attributes = [];

        if (array_key_exists('caliber', $changes)) {
            $caliber = $this->blankToNull($changes['caliber']);
            if ($caliber !== null && mb_strlen($caliber) > 64) {
                throw new RuntimeException('Cartridge must be 64 characters or fewer.');
            }
            [$base] = self::splitName($shooter->name);
            $attributes['name'] = self::joinName($base, $caliber);
        }

        if (array_key_exists('division_id', $changes)) {
            $divisionId = $changes['division_id'] !== null ? (int) $changes['division_id'] : null;
            if ($divisionId !== null && ! $match->divisions()->whereKey($divisionId)->exists()) {
                throw new RuntimeException('That division does not belong to this match.');
            }
            $attributes['match_division_id'] = $divisionId;
        }

        if (array_key_exists('team_id', $changes) || array_key_exists('team', $changes)) {
            $attributes['team_id'] = $this->resolveTeamId($match, $changes);
        }

        $squadId = array_key_exists('squad_id', $changes) && $changes['squad_id'] !== null
            ? (int) $changes['squad_id']
            : null;

        if ($squadId !== null && $squadId !== (int) $shooter->squad_id) {
            $squad = $match->squads()->whereKey($squadId)->first();
            if (! $squad) {
                throw new RuntimeException('That squad does not belong to this match.');
            }
            if ($squad->isFull()) {
                throw new RuntimeException("{$squad->name} is full.");
            }
            $attributes['squad_id'] = $squad->id;
            $attributes['sort_order'] = ((int) $squad->shooters()->max('sort_order')) + 1;
        }

        if ($attributes !== []) {
            $shooter->update($attributes);
        }

        $this->syncRegistration($match, $shooter->fresh(), $changes);

        return $shooter->fresh(['division', 'team']);
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    public static function splitName(?string $name): array
    {
        $name = trim((string) $name);
        if ($name === '' || ! str_contains($name, ' — ')) {
            return [$name, null];
        }

        [$base, $suffix] = array_pad(explode(' — ', $name, 2), 2, '');
        $base = trim($base);
        $suffix = trim($suffix);

        return [$base !== '' ? $base : $name, $suffix !== '' ? $suffix : null];
    }

    public static function joinName(string $base, ?string $caliber): string
    {
        $base = trim($base);
        $caliber = trim((string) $caliber);

        return $caliber === '' ? $base : "{$base} — {$caliber}";
    }

    public static function caliberFor(Shooter $shooter, ?string $registrationCaliber): ?string
    {
        $registrationCaliber = trim((string) $registrationCaliber);
        if ($registrationCaliber !== '') {
            return $registrationCaliber;
        }

        return self::splitName($shooter->name)[1];
    }

    /**
     * @param  array{caliber?: string|null, division_id?: int|null, team_id?: int|null, team?: string|null}  $changes
     */
    private function syncRegistration(ShootingMatch $match, Shooter $shooter, array $changes): void
    {
        if (! $shooter->user_id) {
            return;
        }

        $touchesRegistration = array_key_exists('caliber', $changes) || array_key_exists('division_id', $changes);
        if (! $touchesRegistration) {
            return;
        }

        $registration = MatchRegistration::query()
            ->where('match_id', $match->id)
            ->where('user_id', $shooter->user_id)
            ->first();

        if (! $registration) {
            return;
        }

        $registrationAttributes = [];
        if (array_key_exists('caliber', $changes)) {
            $registrationAttributes['caliber'] = $this->blankToNull($changes['caliber']);
        }
        if (array_key_exists('division_id', $changes)) {
            $registrationAttributes['division_id'] = $shooter->match_division_id;
        }

        if ($registrationAttributes !== []) {
            $registration->update($registrationAttributes);
        }
    }

    /**
     * @param  array{team_id?: int|null, team?: string|null}  $changes
     */
    private function resolveTeamId(ShootingMatch $match, array $changes): ?int
    {
        if (array_key_exists('team_id', $changes)) {
            $teamId = $changes['team_id'] !== null && $changes['team_id'] !== ''
                ? (int) $changes['team_id']
                : null;
            if ($teamId !== null && ! $match->teams()->whereKey($teamId)->exists()) {
                throw new RuntimeException('That team does not belong to this match.');
            }

            return $teamId;
        }

        $name = trim((string) ($changes['team'] ?? ''));
        if ($name === '') {
            return null;
        }

        $team = $match->teams()->where('name', $name)->first();
        if (! $team) {
            throw new RuntimeException('That team does not belong to this match.');
        }

        return $team->id;
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, 64, '');
    }
}
