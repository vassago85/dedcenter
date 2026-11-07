<?php

namespace App\Services\Scoring;

use App\Enums\ElrShotResult;
use App\Models\ElrShot;
use App\Models\ElrStage;
use App\Models\ElrTarget;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Services\ScoreAuditService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Full-match ELR scoresheet: one row per shooter, three relative targets
 * per station (the gongs that shooter's class engages), three shots each.
 * A blank cell is unscored — the row is removed, not stored as a miss.
 */
class ElrScoresheetService
{
    /**
     * @return array{
     *     stages: list<array{id: int, label: string, shot_count: int, rel_count: int}>,
     *     squads: list<array{id: int, name: string}>,
     *     shooters: list<array<string, mixed>>,
     *     cells: array<string, string>
     * }
     */
    public function sheet(ShootingMatch $match): array
    {
        $stages = $this->stages($match);
        $allowedByDivision = $this->divisionWhitelist($match);
        $shotCountByStage = [];
        foreach ($stages as $stage) {
            $shotCountByStage[$stage->id] = max(1, (int) ($stage->targets->max('max_shots') ?: $match->elr_shots_per_target ?: 3));
        }

        $engagedByDivision = [];
        $relCountByStage = [];
        foreach ($stages as $stage) {
            $max = 1;
            foreach ($allowedByDivision['divisions'] as $divisionId) {
                $engaged = $this->engagedTargets($stage, $allowedByDivision['allowed'][$divisionId] ?? null);
                $engagedByDivision[$divisionId][$stage->id] = $engaged;
                $max = max($max, $engaged->count());
            }
            if ($allowedByDivision['divisions'] === []) {
                $max = max($max, $stage->targets->count());
            }
            $relCountByStage[$stage->id] = $max;
        }

        $shooters = $this->shooters($match);
        $cells = $this->cellsFor($shooters->pluck('id'), $stages);

        $rows = [];
        $squads = [];
        foreach ($shooters as $shooter) {
            $divisionId = $shooter->match_division_id ? (int) $shooter->match_division_id : null;
            $squadId = (int) $shooter->squad_id;
            $squads[$squadId] = ['id' => $squadId, 'name' => (string) $shooter->squad_name];

            $groups = [];
            foreach ($stages as $stage) {
                $engaged = $divisionId !== null
                    ? ($engagedByDivision[$divisionId][$stage->id] ?? $this->engagedTargets($stage, $allowedByDivision['allowed'][$divisionId] ?? null))
                    : $this->engagedTargets($stage, null);
                $shotCount = $shotCountByStage[$stage->id];
                $targets = [];
                for ($rel = 0; $rel < $relCountByStage[$stage->id]; $rel++) {
                    $target = $engaged->get($rel);
                    if (! $target) {
                        $targets[] = null;

                        continue;
                    }
                    $shots = [];
                    $limit = min($shotCount, (int) ($target->max_shots ?: $shotCount));
                    for ($n = 1; $n <= $shotCount; $n++) {
                        $shots[] = $n <= $limit
                            ? ['n' => $n, 'key' => $this->key($shooter->id, $target->id, $n)]
                            : null;
                    }
                    $targets[] = [
                        'target_id' => $target->id,
                        'distance' => (int) $target->distance_m,
                        'shots' => $shots,
                    ];
                }
                $groups[] = ['stage_id' => $stage->id, 'targets' => $targets];
            }

            $rows[] = [
                'id' => (int) $shooter->id,
                'name' => (string) $shooter->name,
                'squad_id' => $squadId,
                'squad_name' => (string) $shooter->squad_name,
                'division' => (string) ($shooter->division_name ?? ''),
                'team' => (string) ($shooter->team_name ?? ''),
                'groups' => $groups,
            ];
        }

        return [
            'stages' => $stages->map(fn (ElrStage $stage) => [
                'id' => $stage->id,
                'label' => $stage->label,
                'shot_count' => $shotCountByStage[$stage->id],
                'rel_count' => $relCountByStage[$stage->id],
            ])->values()->all(),
            'squads' => array_values($squads),
            'shooters' => $rows,
            'cells' => $cells,
        ];
    }

    /**
     * @param  array<string, string>  $dirty  key "{shooter}:{target}:{shot}" => "1"|"0"|""
     * @return array{written: int, cleared: int, unchanged: int}
     */
    public function apply(ShootingMatch $match, array $dirty, string $reason, int $actorUserId): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('A correction note is required.');
        }

        $stages = $this->stages($match);
        $allowedByDivision = $this->divisionWhitelist($match);
        $shooters = $this->shooters($match)->keyBy('id');

        $targets = $stages->flatMap(fn (ElrStage $stage) => $stage->targets)->keyBy('id');
        $match->loadMissing('elrScoringProfile');
        $targets->each(fn (ElrTarget $target) => $target->stage?->setRelation('match', $match));

        $engagedIds = [];
        foreach ($shooters as $shooter) {
            $divisionId = $shooter->match_division_id ? (int) $shooter->match_division_id : null;
            $allowed = $divisionId !== null ? ($allowedByDivision['allowed'][$divisionId] ?? null) : null;
            foreach ($stages as $stage) {
                foreach ($this->engagedTargets($stage, $allowed) as $target) {
                    $engagedIds[(int) $shooter->id][$target->id] = true;
                }
            }
        }

        $stats = ['written' => 0, 'cleared' => 0, 'unchanged' => 0];
        $isTeam = $match->elrEngagementMode()->isTeamSequence();
        $service = app(ELRScoringService::class);
        $shooterModels = Shooter::query()->whereIn('id', $shooters->keys())->get()->keyBy('id');

        DB::transaction(function () use ($dirty, $shooterModels, $targets, $engagedIds, $match, $reason, $actorUserId, $isTeam, $service, &$stats) {
            $touched = [];

            foreach ($dirty as $key => $value) {
                $parsed = $this->parseKey((string) $key);
                if ($parsed === null) {
                    continue;
                }
                [$shooterId, $targetId, $shotNumber] = $parsed;
                $shooter = $shooterModels->get($shooterId);
                $target = $targets->get($targetId);
                if (! $shooter || ! $target || ! isset($engagedIds[$shooterId][$targetId])) {
                    continue;
                }
                if ($shotNumber < 1 || $shotNumber > (int) ($target->max_shots ?: 3)) {
                    continue;
                }

                $desired = $this->normalize($value);
                if ($desired === null) {
                    continue;
                }

                $existing = ElrShot::query()
                    ->where('shooter_id', $shooterId)
                    ->where('elr_target_id', $targetId)
                    ->where('shot_number', $shotNumber)
                    ->first();

                $current = match ($existing?->result) {
                    ElrShotResult::Hit => '1',
                    ElrShotResult::Miss => '0',
                    default => '',
                };

                if ($desired === '' && ! $existing) {
                    $stats['unchanged']++;

                    continue;
                }

                if ($desired === $current && $existing?->result !== ElrShotResult::NotTaken) {
                    $stats['unchanged']++;

                    continue;
                }

                if ($desired === '') {
                    ScoreAuditService::log($match->id, $existing, 'deleted', $existing->toArray(), null, $reason);
                    $existing->delete();
                    $stats['cleared']++;
                    $touched[$shooterId][$targetId] = $target;

                    continue;
                }

                $result = $desired === '1' ? ElrShotResult::Hit : ElrShotResult::Miss;
                $old = $existing?->toArray();
                $shot = $service->recordShot(
                    $shooter,
                    $target,
                    $shotNumber,
                    $result,
                    $actorUserId,
                    'scoresheet',
                );

                ScoreAuditService::log(
                    $match->id,
                    $shot,
                    $old ? 'updated' : 'created',
                    $old,
                    $shot->toArray(),
                    $reason,
                );
                $stats['written']++;
                $touched[$shooterId][$targetId] = $target;
            }

            if ($isTeam) {
                foreach ($touched as $shooterId => $shooterTargets) {
                    foreach ($shooterTargets as $target) {
                        $service->recomputeTargetImpacts((int) $shooterId, $target);
                    }
                }
            }
        });

        return $stats;
    }

    /**
     * @return Collection<int, ElrStage>
     */
    private function stages(ShootingMatch $match): Collection
    {
        return $match->elrStages()
            ->with(['targets' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return array{divisions: list<int>, allowed: array<int, array<int, true>|null>}
     */
    private function divisionWhitelist(ShootingMatch $match): array
    {
        $divisions = $match->divisions()->orderBy('id')->get(['id']);
        $allowed = [];
        $rows = DB::table('elr_division_targets')
            ->whereIn('match_division_id', $divisions->pluck('id'))
            ->get(['match_division_id', 'elr_target_id']);

        $restricted = [];
        foreach ($rows as $row) {
            $restricted[(int) $row->match_division_id] = true;
            $allowed[(int) $row->match_division_id][(int) $row->elr_target_id] = true;
        }

        foreach ($divisions as $division) {
            if (! isset($restricted[$division->id])) {
                $allowed[(int) $division->id] = null;
            }
        }

        return [
            'divisions' => $divisions->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'allowed' => $allowed,
        ];
    }

    /**
     * @param  array<int, true>|null  $allowed  null = every gong on the station
     * @return Collection<int, ElrTarget>
     */
    private function engagedTargets(ElrStage $stage, ?array $allowed): Collection
    {
        return $stage->targets
            ->filter(fn (ElrTarget $target) => $allowed === null || isset($allowed[$target->id]))
            ->sortBy('sort_order')
            ->values();
    }

    /**
     * @return Collection<int, object>
     */
    private function shooters(ShootingMatch $match): Collection
    {
        return Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->leftJoin('teams', 'shooters.team_id', '=', 'teams.id')
            ->leftJoin('match_divisions', 'shooters.match_division_id', '=', 'match_divisions.id')
            ->where('squads.match_id', $match->id)
            ->orderBy('squads.sort_order')
            ->orderBy('shooters.sort_order')
            ->orderBy('shooters.name')
            ->get([
                'shooters.id',
                'shooters.name',
                'shooters.squad_id',
                'shooters.match_division_id',
                'squads.name as squad_name',
                'teams.name as team_name',
                'match_divisions.name as division_name',
            ]);
    }

    /**
     * @param  Collection<int, mixed>  $shooterIds
     * @param  Collection<int, ElrStage>  $stages
     * @return array<string, string>
     */
    private function cellsFor(Collection $shooterIds, Collection $stages): array
    {
        if ($shooterIds->isEmpty()) {
            return [];
        }

        $targetIds = $stages->flatMap(fn (ElrStage $stage) => $stage->targets->pluck('id'));
        if ($targetIds->isEmpty()) {
            return [];
        }

        $cells = [];
        $shots = ElrShot::query()
            ->whereIn('shooter_id', $shooterIds)
            ->whereIn('elr_target_id', $targetIds)
            ->get(['shooter_id', 'elr_target_id', 'shot_number', 'result']);

        foreach ($shots as $shot) {
            $mark = match ($shot->result) {
                ElrShotResult::Hit => '1',
                ElrShotResult::Miss => '0',
                default => '',
            };
            if ($mark === '') {
                continue;
            }
            $cells[$this->key($shot->shooter_id, $shot->elr_target_id, $shot->shot_number)] = $mark;
        }

        return $cells;
    }

    private function key(int $shooterId, int $targetId, int $shotNumber): string
    {
        return $shooterId.':'.$targetId.':'.$shotNumber;
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private function parseKey(string $key): ?array
    {
        $parts = explode(':', $key);
        if (count($parts) !== 3 || ! ctype_digit($parts[0]) || ! ctype_digit($parts[1]) || ! ctype_digit($parts[2])) {
            return null;
        }

        return [(int) $parts[0], (int) $parts[1], (int) $parts[2]];
    }

    private function normalize(mixed $value): ?string
    {
        $mark = is_string($value) ? $value : (is_int($value) ? (string) $value : '');

        return in_array($mark, ['', '0', '1'], true) ? $mark : null;
    }
}
