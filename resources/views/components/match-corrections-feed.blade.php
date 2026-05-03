@props([
    'match',
    // Cap on rows shown inline. The feed is meant for "what just
    // happened" awareness, not a full audit ledger — tune if a future
    // design wants pagination, but for now the most recent N entries
    // is the right answer for both Scoring and Reports surfaces.
    'limit' => 25,
    // 'compact' (Scoring tab — live feed style, dense) or 'full'
    // (Reports tab — permanent record, more breathing room).
    'variant' => 'full',
    // Optional deep link (e.g. to the squad-correction page) so the
    // MD can act on a specific shooter from the feed.
    'correctUrl' => null,
])

@php
    use App\Models\ScoreAuditLog;
    use App\Models\Score;
    use App\Models\PrsShotScore;
    use App\Models\PrsStageResult;
    use App\Models\StageTime;

    /*
    |--------------------------------------------------------------------------
    | Match Corrections Feed
    |--------------------------------------------------------------------------
    | Surfaces every score / shot / stage-time mutation that's been logged
    | to `score_audit_logs` for this match, with the author and the
    | optional `reason` note. Lives in two places by design:
    |
    |   - Scoring tab (`variant=compact`) — live feed during the match,
    |     so the MD knows in real time when an SO files a correction.
    |   - Reports tab (`variant=full`)    — permanent audit record after
    |     the match completes; same data, more padding so it reads as
    |     a ledger rather than a stream.
    |
    | The data has been collecting all along (the web `squad-correction`
    | page writes through `SquadScoreCorrectionService` which always
    | passes a `reason`; the scoring app now also threads its optional
    | `correction_reason` through to `ScoreAuditService`). Until this
    | component existed, none of it was visible — the audit log was an
    | API-only fixture with no consumer in `resources/`.
    |
    | We resolve human-readable subject labels (e.g. "Pat — 500m
    | gong #3", "Pat — Stage 2 shot 1") on the PHP side rather than
    | dumping JSON into the UI, because the MD wants to know "who got
    | corrected" not "row id 47 in table prs_shot_scores".
    */

    $entries = ScoreAuditLog::where('match_id', $match->id)
        ->whereIn('action', ['updated', 'deleted', 'correction'])
        ->with('user:id,name,email')
        ->orderByDesc('created_at')
        ->limit($limit)
        ->get();

    // Bulk-load the auditable subject rows so we can label each entry
    // without an N+1. `auditable_type` is the FQCN of the model and
    // `auditable_id` is the row's primary key — group by type so each
    // type gets one query rather than per-row.
    $byType = $entries->groupBy('auditable_type');

    $resolveScore = function ($ids) {
        if (empty($ids)) return collect();
        return Score::whereIn('id', $ids)
            ->with(['shooter:id,name', 'gong:id,number,label,target_set_id', 'gong.targetSet:id,label,distance_meters'])
            ->get()->keyBy('id');
    };
    $resolvePrsShot = function ($ids) {
        if (empty($ids)) return collect();
        return PrsShotScore::whereIn('id', $ids)
            ->with(['shooter:id,name', 'stage:id,label,distance_meters'])
            ->get()->keyBy('id');
    };
    $resolvePrsStage = function ($ids) {
        if (empty($ids)) return collect();
        return PrsStageResult::whereIn('id', $ids)
            ->with(['shooter:id,name', 'stage:id,label,distance_meters'])
            ->get()->keyBy('id');
    };
    $resolveStageTime = function ($ids) {
        if (empty($ids)) return collect();
        return StageTime::whereIn('id', $ids)
            ->with(['shooter:id,name', 'targetSet:id,label,distance_meters'])
            ->get()->keyBy('id');
    };

    $loaded = [
        Score::class => $resolveScore($byType->get(Score::class, collect())->pluck('auditable_id')->all()),
        PrsShotScore::class => $resolvePrsShot($byType->get(PrsShotScore::class, collect())->pluck('auditable_id')->all()),
        PrsStageResult::class => $resolvePrsStage($byType->get(PrsStageResult::class, collect())->pluck('auditable_id')->all()),
        StageTime::class => $resolveStageTime($byType->get(StageTime::class, collect())->pluck('auditable_id')->all()),
    ];

    $describe = function ($entry) use ($loaded) {
        $type = $entry->auditable_type;
        $row = $loaded[$type][$entry->auditable_id] ?? null;

        $shooter = $row->shooter->name ?? 'Unknown shooter';

        if ($type === Score::class && $row) {
            $stage = $row->gong?->targetSet?->label ?? "Stage";
            $distance = $row->gong?->targetSet?->distance_meters;
            $gongNumber = $row->gong?->number ?? '?';
            $stageLabel = $distance ? "{$stage} ({$distance}m)" : $stage;
            $oldHit = (bool) ($entry->old_values['is_hit'] ?? false);
            $newHit = (bool) ($entry->new_values['is_hit'] ?? false);
            $change = $entry->action === 'deleted'
                ? 'cleared'
                : (($oldHit !== $newHit) ? ($newHit ? 'miss → HIT' : 'hit → MISS') : 'updated');
            return [
                'subject' => "{$shooter} · {$stageLabel} · gong #{$gongNumber}",
                'change' => $change,
            ];
        }

        if ($type === PrsShotScore::class && $row) {
            $stage = $row->stage?->label ?? 'Stage';
            $shot = $row->shot_number;
            $oldR = $entry->old_values['result'] ?? '?';
            $newR = $entry->new_values['result'] ?? '?';
            return [
                'subject' => "{$shooter} · {$stage} · shot {$shot}",
                'change' => $entry->action === 'deleted' ? 'cleared' : "{$oldR} → {$newR}",
            ];
        }

        if ($type === PrsStageResult::class && $row) {
            $stage = $row->stage?->label ?? 'Stage';
            $oldHits = $entry->old_values['hits'] ?? null;
            $newHits = $entry->new_values['hits'] ?? null;
            $change = ($oldHits !== null && $newHits !== null && $oldHits !== $newHits)
                ? "hits {$oldHits} → {$newHits}"
                : 'stage re-submitted';
            return [
                'subject' => "{$shooter} · {$stage} · stage result",
                'change' => $change,
            ];
        }

        if ($type === StageTime::class && $row) {
            $stage = $row->targetSet?->label ?? 'Stage';
            $oldT = isset($entry->old_values['time_seconds']) ? number_format((float) $entry->old_values['time_seconds'], 1) . 's' : '?';
            $newT = isset($entry->new_values['time_seconds']) ? number_format((float) $entry->new_values['time_seconds'], 1) . 's' : '?';
            return [
                'subject' => "{$shooter} · {$stage} · stage time",
                'change' => "{$oldT} → {$newT}",
            ];
        }

        return [
            'subject' => 'Score row (deleted source)',
            'change' => $entry->action,
        ];
    };

    $actionPalette = [
        'updated'    => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
        'deleted'    => 'border-red-500/30 bg-red-500/10 text-red-300',
        'correction' => 'border-violet-500/30 bg-violet-500/10 text-violet-300',
    ];

    $padding = $variant === 'compact' ? 'p-2.5' : 'p-3.5';
    $gap = $variant === 'compact' ? 'space-y-1.5' : 'space-y-2';
@endphp

<section
    {{ $attributes->merge(['class' => 'rounded-2xl border border-border bg-surface p-4 sm:p-5']) }}
    aria-label="Recent corrections"
>
    <div class="mb-3 flex items-center justify-between gap-3">
        <div>
            <h3 class="text-sm font-bold text-primary">Recent Corrections</h3>
            <p class="text-xs text-muted">
                Score changes &amp; deletions for this match. Notes are required when filed via the squad-correction page or the scoring app's correct-shooter flow.
            </p>
        </div>
        @if($correctUrl)
            <a
                href="{{ $correctUrl }}"
                class="shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-2 px-3 py-1.5 text-xs font-semibold text-secondary transition-colors hover:border-accent/50 hover:text-primary"
            >
                <x-icon name="edit" class="h-3.5 w-3.5" />
                File a correction
            </a>
        @endif
    </div>

    @if($entries->isEmpty())
        <div class="rounded-xl border border-dashed border-border bg-surface-2 px-4 py-6 text-center text-sm text-muted">
            No corrections logged yet. When an SO or MD changes a score after it has been recorded, it will appear here with the reason and timestamp.
        </div>
    @else
        <ul class="{{ $gap }}">
            @foreach($entries as $entry)
                @php
                    $info = $describe($entry);
                    $actionCls = $actionPalette[$entry->action] ?? 'border-zinc-500/30 bg-zinc-500/10 text-zinc-300';
                    $author = $entry->user?->name ?? 'Unknown user';
                    $when = $entry->created_at?->diffForHumans() ?? '—';
                    $whenAbs = $entry->created_at?->format('D, j M Y · H:i:s') ?? '';
                @endphp
                <li class="rounded-xl border border-border bg-surface-2 {{ $padding }}">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $actionCls }}">
                                    {{ strtoupper($entry->action) }}
                                </span>
                                <span class="text-xs font-semibold text-primary">{{ $info['change'] }}</span>
                            </div>
                            <p class="mt-1 truncate text-sm text-secondary">{{ $info['subject'] }}</p>
                        </div>
                        <div class="shrink-0 text-right text-[11px] text-muted">
                            <div title="{{ $whenAbs }}" class="tabular-nums">{{ $when }}</div>
                            <div class="truncate">by {{ $author }}</div>
                        </div>
                    </div>

                    @if($entry->reason)
                        <div class="mt-2 rounded-lg border-l-4 border-violet-500/60 bg-zinc-900/40 px-3 py-2 text-[12.5px] italic text-zinc-300">
                            "{{ $entry->reason }}"
                        </div>
                    @else
                        <div class="mt-2 text-[11px] text-muted/70">
                            <span class="opacity-60">No note recorded — change was made directly without a correction reason.</span>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
