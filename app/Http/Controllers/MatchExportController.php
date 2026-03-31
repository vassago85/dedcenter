<?php

namespace App\Http\Controllers;

use App\Models\ElrShot;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\StageTime;
use App\Services\Scoring\ELRScoringService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MatchExportController extends Controller
{
    public function standings(ShootingMatch $match): StreamedResponse
    {
        $slug = Str::slug($match->name);

        if ($match->isElr()) {
            return $this->streamCsv("{$slug}-standings.csv", fn ($out) => $this->elrStandings($match, $out));
        }

        if ($match->isPrs()) {
            return $this->streamCsv("{$slug}-standings.csv", fn ($out) => $this->prsStandings($match, $out));
        }

        return $this->streamCsv("{$slug}-standings.csv", fn ($out) => $this->standardStandings($match, $out));
    }

    public function detailed(ShootingMatch $match): StreamedResponse
    {
        $slug = Str::slug($match->name);

        if ($match->isElr()) {
            return $this->streamCsv("{$slug}-detailed.csv", fn ($out) => $this->elrDetailed($match, $out));
        }

        if ($match->isPrs()) {
            return $this->streamCsv("{$slug}-detailed.csv", fn ($out) => $this->prsDetailed($match, $out));
        }

        return $this->streamCsv("{$slug}-detailed.csv", fn ($out) => $this->standardDetailed($match, $out));
    }

    // ── Standard ────────────────────────────────────────────────

    private function standardStandings(ShootingMatch $match, $out): void
    {
        $divisionNames = $this->divisionLookup($match);

        $shooters = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->leftJoin('scores', 'shooters.id', '=', 'scores.shooter_id')
            ->leftJoin('gongs', 'scores.gong_id', '=', 'gongs.id')
            ->leftJoin('target_sets', 'gongs.target_set_id', '=', 'target_sets.id')
            ->where('squads.match_id', $match->id)
            ->select('shooters.id as shooter_id', 'shooters.name', 'squads.name as squad')
            ->selectRaw('COUNT(CASE WHEN scores.is_hit = 1 THEN 1 END) as agg_hits')
            ->selectRaw('COUNT(CASE WHEN scores.is_hit = 0 THEN 1 END) as agg_misses')
            ->selectRaw('COALESCE(SUM(CASE WHEN scores.is_hit = 1 THEN COALESCE(target_sets.distance_multiplier, 1) * gongs.multiplier ELSE 0 END), 0) as agg_total')
            ->groupBy('shooters.id', 'shooters.name', 'squads.name')
            ->orderByDesc('agg_total')
            ->get();

        fputcsv($out, ['Rank', 'Name', 'Squad', 'Division', 'Hits', 'Misses', 'Total Score']);

        foreach ($shooters->values() as $i => $s) {
            fputcsv($out, [
                $i + 1,
                $s->name,
                $s->squad,
                $divisionNames[(int) $s->shooter_id] ?? '',
                (int) $s->agg_hits,
                (int) $s->agg_misses,
                round((float) $s->agg_total, 2),
            ]);
        }
    }

    private function standardDetailed(ShootingMatch $match, $out): void
    {
        $divisionNames = $this->divisionLookup($match);

        $targetSets = $match->targetSets()
            ->orderBy('sort_order')
            ->with(['gongs' => fn ($q) => $q->orderBy('number')])
            ->get();

        $allGongs = $targetSets->flatMap->gongs;

        $shooters = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->select('shooters.id', 'shooters.name', 'squads.name as squad_name')
            ->get();

        $allScores = Score::query()
            ->whereIn('shooter_id', $shooters->pluck('id'))
            ->whereIn('gong_id', $allGongs->pluck('id'))
            ->get()
            ->groupBy('shooter_id');

        $header = ['Rank', 'Name', 'Squad', 'Division'];
        foreach ($targetSets as $ts) {
            $label = $ts->label ?: "{$ts->distance_meters}m";
            foreach ($ts->gongs as $g) {
                $header[] = "{$label} G{$g->number}";
            }
            $header[] = "{$label} Subtotal";
        }
        $header = array_merge($header, ['Hits', 'Misses', 'Total Score']);
        fputcsv($out, $header);

        $rows = $shooters->map(function ($shooter) use ($allScores, $targetSets, $divisionNames) {
            $scores = $allScores->get($shooter->id, collect())->keyBy('gong_id');
            $totalScore = 0;
            $totalHits = 0;
            $totalMisses = 0;
            $cells = [];

            foreach ($targetSets as $ts) {
                $distMult = (float) ($ts->distance_multiplier ?? 1);
                $distSubtotal = 0;

                foreach ($ts->gongs as $g) {
                    $score = $scores->get($g->id);
                    if (!$score) {
                        $cells[] = '-';
                    } elseif ($score->is_hit) {
                        $cells[] = 'H';
                        $points = round($distMult * $g->multiplier, 2);
                        $distSubtotal += $points;
                        $totalScore += $points;
                        $totalHits++;
                    } else {
                        $cells[] = 'M';
                        $totalMisses++;
                    }
                }
                $cells[] = round($distSubtotal, 2);
            }

            return [
                'name' => $shooter->name,
                'squad' => $shooter->squad_name,
                'division' => $divisionNames[$shooter->id] ?? '',
                'cells' => $cells,
                'hits' => $totalHits,
                'misses' => $totalMisses,
                'total' => round($totalScore, 2),
            ];
        })->sortByDesc('total')->values();

        foreach ($rows as $i => $row) {
            fputcsv($out, array_merge(
                [$i + 1, $row['name'], $row['squad'], $row['division']],
                $row['cells'],
                [$row['hits'], $row['misses'], $row['total']],
            ));
        }
    }

    // ── PRS ─────────────────────────────────────────────────────

    private function prsStandings(ShootingMatch $match, $out): void
    {
        $divisionNames = $this->divisionLookup($match);
        $targetSets = $match->targetSets()->get();
        $targetSetIds = $targetSets->pluck('id');

        $totalTargets = DB::table('gongs')->whereIn('target_set_id', $targetSetIds)->count();
        $tiebreakerStage = $targetSets->firstWhere('is_tiebreaker', true);

        $shooterTimes = StageTime::whereIn('target_set_id', $targetSetIds)
            ->get()->groupBy('shooter_id')
            ->map(fn ($t) => (float) $t->sum('time_seconds'))->toArray();

        $tbHits = [];
        $tbTimes = [];
        if ($tiebreakerStage) {
            $tbGongIds = DB::table('gongs')->where('target_set_id', $tiebreakerStage->id)->pluck('id');
            $tbHits = Score::whereIn('gong_id', $tbGongIds)->where('is_hit', true)
                ->select('shooter_id', DB::raw('COUNT(*) as c'))->groupBy('shooter_id')
                ->pluck('c', 'shooter_id')->map(fn ($v) => (int) $v)->toArray();
            $tbTimes = StageTime::where('target_set_id', $tiebreakerStage->id)
                ->pluck('time_seconds', 'shooter_id')->map(fn ($v) => (float) $v)->toArray();
        }

        $shooters = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->leftJoin('scores', 'shooters.id', '=', 'scores.shooter_id')
            ->where('squads.match_id', $match->id)
            ->select('shooters.id as shooter_id', 'shooters.name', 'squads.name as squad')
            ->selectRaw('COUNT(CASE WHEN scores.is_hit = 1 THEN 1 END) as agg_hits')
            ->selectRaw('COUNT(CASE WHEN scores.is_hit = 0 THEN 1 END) as agg_misses')
            ->groupBy('shooters.id', 'shooters.name', 'squads.name')
            ->get();

        $entries = $shooters->map(function ($s) use ($shooterTimes, $tbHits, $tbTimes, $divisionNames, $totalTargets) {
            $sid = (int) $s->shooter_id;
            return [
                'sid' => $sid, 'name' => $s->name, 'squad' => $s->squad,
                'division' => $divisionNames[$sid] ?? '',
                'hits' => (int) $s->agg_hits, 'misses' => (int) $s->agg_misses,
                'not_taken' => $totalTargets - (int) $s->agg_hits - (int) $s->agg_misses,
                'total_time' => round($shooterTimes[$sid] ?? 0.0, 2),
                'tb_hits' => $tbHits[$sid] ?? 0,
                'tb_time' => round($tbTimes[$sid] ?? 0.0, 2),
            ];
        })->sort(function ($a, $b) {
            if ($a['hits'] !== $b['hits']) return $b['hits'] <=> $a['hits'];
            if ($a['tb_hits'] !== $b['tb_hits']) return $b['tb_hits'] <=> $a['tb_hits'];
            if ($a['tb_time'] !== $b['tb_time']) return $a['tb_time'] <=> $b['tb_time'];
            return $a['total_time'] <=> $b['total_time'];
        })->values();

        fputcsv($out, ['Rank', 'Name', 'Squad', 'Division', 'Hits', 'Misses', 'Not Taken', 'Total Time', 'TB Hits', 'TB Time']);

        foreach ($entries as $i => $e) {
            fputcsv($out, [$i + 1, $e['name'], $e['squad'], $e['division'], $e['hits'], $e['misses'], $e['not_taken'], $e['total_time'], $e['tb_hits'], $e['tb_time']]);
        }
    }

    private function prsDetailed(ShootingMatch $match, $out): void
    {
        $divisionNames = $this->divisionLookup($match);
        $targetSets = $match->targetSets()->orderBy('sort_order')
            ->with(['gongs' => fn ($q) => $q->orderBy('number')])->get();
        $targetSetIds = $targetSets->pluck('id');

        $totalTargets = DB::table('gongs')->whereIn('target_set_id', $targetSetIds)->count();
        $tiebreakerStage = $targetSets->firstWhere('is_tiebreaker', true);
        $allGongs = $targetSets->flatMap->gongs;

        $shooterTimesByStage = StageTime::whereIn('target_set_id', $targetSetIds)
            ->get()->groupBy('shooter_id');

        $tbHits = [];
        $tbTimes = [];
        if ($tiebreakerStage) {
            $tbGongIds = DB::table('gongs')->where('target_set_id', $tiebreakerStage->id)->pluck('id');
            $tbHits = Score::whereIn('gong_id', $tbGongIds)->where('is_hit', true)
                ->select('shooter_id', DB::raw('COUNT(*) as c'))->groupBy('shooter_id')
                ->pluck('c', 'shooter_id')->map(fn ($v) => (int) $v)->toArray();
            $tbTimes = StageTime::where('target_set_id', $tiebreakerStage->id)
                ->pluck('time_seconds', 'shooter_id')->map(fn ($v) => (float) $v)->toArray();
        }

        $shooters = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->select('shooters.id', 'shooters.name', 'squads.name as squad_name')
            ->get();

        $allScores = Score::whereIn('shooter_id', $shooters->pluck('id'))
            ->whereIn('gong_id', $allGongs->pluck('id'))
            ->get()->groupBy('shooter_id');

        $header = ['Rank', 'Name', 'Squad', 'Division'];
        foreach ($targetSets as $ts) {
            $label = $ts->label ?: "Stage {$ts->sort_order}";
            foreach ($ts->gongs as $g) {
                $header[] = "{$label} G{$g->number}";
            }
            $header[] = "{$label} Time";
        }
        $header = array_merge($header, ['Hits', 'Misses', 'Not Taken', 'Total Time', 'TB Hits', 'TB Time']);
        fputcsv($out, $header);

        $rows = $shooters->map(function ($shooter) use ($allScores, $targetSets, $shooterTimesByStage, $divisionNames, $totalTargets, $tbHits, $tbTimes) {
            $scores = $allScores->get($shooter->id, collect())->keyBy('gong_id');
            $times = $shooterTimesByStage->get($shooter->id, collect())->keyBy('target_set_id');
            $totalHits = 0;
            $totalMisses = 0;
            $totalTime = 0;
            $cells = [];

            foreach ($targetSets as $ts) {
                foreach ($ts->gongs as $g) {
                    $score = $scores->get($g->id);
                    if (!$score) {
                        $cells[] = '-';
                    } elseif ($score->is_hit) {
                        $cells[] = 'H';
                        $totalHits++;
                    } else {
                        $cells[] = 'M';
                        $totalMisses++;
                    }
                }
                $stageTime = $times->get($ts->id)?->time_seconds ?? 0;
                $totalTime += $stageTime;
                $cells[] = round((float) $stageTime, 2);
            }

            $sid = $shooter->id;
            return [
                'name' => $shooter->name, 'squad' => $shooter->squad_name,
                'division' => $divisionNames[$sid] ?? '',
                'cells' => $cells,
                'hits' => $totalHits, 'misses' => $totalMisses,
                'not_taken' => $totalTargets - $totalHits - $totalMisses,
                'total_time' => round($totalTime, 2),
                'tb_hits' => $tbHits[$sid] ?? 0,
                'tb_time' => round($tbTimes[$sid] ?? 0.0, 2),
            ];
        })->sort(function ($a, $b) {
            if ($a['hits'] !== $b['hits']) return $b['hits'] <=> $a['hits'];
            if ($a['tb_hits'] !== $b['tb_hits']) return $b['tb_hits'] <=> $a['tb_hits'];
            if ($a['tb_time'] !== $b['tb_time']) return $a['tb_time'] <=> $b['tb_time'];
            return $a['total_time'] <=> $b['total_time'];
        })->values();

        foreach ($rows as $i => $row) {
            fputcsv($out, array_merge(
                [$i + 1, $row['name'], $row['squad'], $row['division']],
                $row['cells'],
                [$row['hits'], $row['misses'], $row['not_taken'], $row['total_time'], $row['tb_hits'], $row['tb_time']],
            ));
        }
    }

    // ── ELR ─────────────────────────────────────────────────────

    private function elrStandings(ShootingMatch $match, $out): void
    {
        $data = (new ELRScoringService())->calculateStandings($match);

        fputcsv($out, ['Rank', 'Name', 'Squad', 'Total Points', 'Total Hits', '1st Round Hits', '2nd Round Hits', 'Furthest Hit (m)']);

        foreach ($data['standings'] as $s) {
            fputcsv($out, [
                $s['rank'], $s['name'], $s['squad_name'],
                $s['total_points'], $s['total_hits'],
                $s['first_round_hits'], $s['second_round_hits'],
                $s['furthest_hit_m'],
            ]);
        }
    }

    private function elrDetailed(ShootingMatch $match, $out): void
    {
        $data = (new ELRScoringService())->calculateStandings($match);
        $stages = $data['stages'];

        $header = ['Rank', 'Name', 'Squad'];
        foreach ($stages as $stage) {
            foreach ($stage['targets'] as $target) {
                for ($s = 1; $s <= $target['max_shots']; $s++) {
                    $header[] = "{$stage['label']} - {$target['name']} S{$s}";
                }
            }
            $header[] = "{$stage['label']} Points";
        }
        $header = array_merge($header, ['Total Points', 'Total Hits', '1st Round Hits', '2nd Round Hits', 'Furthest Hit (m)']);
        fputcsv($out, $header);

        foreach ($data['standings'] as $entry) {
            $row = [$entry['rank'], $entry['name'], $entry['squad_name']];

            foreach ($stages as $si => $stage) {
                $entryStage = $entry['stages'][$si] ?? null;
                foreach ($stage['targets'] as $ti => $target) {
                    $entryTarget = $entryStage['targets'][$ti] ?? null;
                    $shots = $entryTarget['shots'] ?? [];
                    for ($s = 1; $s <= $target['max_shots']; $s++) {
                        $shot = collect($shots)->firstWhere('shot_number', $s);
                        $row[] = $shot ? ucfirst($shot['result']) : '-';
                    }
                }
                $row[] = $entryStage['points'] ?? 0;
            }

            $row[] = $entry['total_points'];
            $row[] = $entry['total_hits'];
            $row[] = $entry['first_round_hits'];
            $row[] = $entry['second_round_hits'];
            $row[] = $entry['furthest_hit_m'];

            fputcsv($out, $row);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function divisionLookup(ShootingMatch $match): array
    {
        return DB::table('shooters')
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->leftJoin('match_divisions', 'shooters.match_division_id', '=', 'match_divisions.id')
            ->where('squads.match_id', $match->id)
            ->pluck('match_divisions.name', 'shooters.id')
            ->toArray();
    }

    private function streamCsv(string $filename, callable $writer): StreamedResponse
    {
        return response()->streamDownload(function () use ($writer) {
            $out = fopen('php://output', 'w');
            $writer($out);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
