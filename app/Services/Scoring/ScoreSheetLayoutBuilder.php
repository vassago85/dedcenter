<?php

namespace App\Services\Scoring;

use App\Enums\AlrhaClass;
use App\Models\ElrTarget;
use App\Models\ScoreSheet;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use Illuminate\Support\Collection;

/**
 * Builds the ALRHA mark-sense score card layout for a single shooter
 * and persists it to a {@see ScoreSheet} row.
 *
 * The layout is the thing the phone reads back. It's intentionally
 * stored as flat JSON (no joins, no classes) so a card scanned a week
 * after printing still decodes correctly even if someone has since
 * edited the match's stages.
 *
 * Positions are millimetres from the top-left corner of the printable
 * A4 page. The decoder uses the four corner fiducials to straighten
 * the photo, then samples each bubble at its stored (x, y) centre.
 *
 * Layout shape (format_version = 1):
 *   {
 *     format: 1,
 *     page: { width, height, margin },
 *     block: { x, y, width, height },
 *     fiducials: [ {x, y}, ... (four corners) ],
 *     qr: { x, y, size, payload },
 *     bubble: { diameter, gap },
 *     rows: [
 *       {
 *         target_id, target_label, distance_m, alrha_block,
 *         is_cold_bore, max_shots,
 *         shots: [
 *           { shot_number, hit:  {x, y}, miss: {x, y} },
 *           ...
 *         ]
 *       },
 *       ...
 *     ]
 *   }
 */
class ScoreSheetLayoutBuilder
{
    /**
     * Full A4 page (portrait) in millimetres.
     * The scan block sits in a fixed rectangle inside this page so a
     * phone can locate it from the four corner fiducials alone.
     */
    public const PAGE_WIDTH_MM = 210.0;
    public const PAGE_HEIGHT_MM = 297.0;

    public const BLOCK_X_MM = 15.0;
    public const BLOCK_Y_MM = 155.0;
    public const BLOCK_WIDTH_MM = 180.0;
    public const BLOCK_HEIGHT_MM = 110.0;

    public const FIDUCIAL_SIZE_MM = 7.0;
    public const QR_SIZE_MM = 22.0;
    public const QR_MARGIN_MM = 4.0;

    public const BUBBLE_DIAMETER_MM = 6.5;
    public const BUBBLE_GAP_MM = 2.5; // between Hit and Miss in a pair
    public const ROW_HEIGHT_MM = 11.0;
    public const SHOT_PAIR_WIDTH_MM = 22.0; // one shot = Hit + Miss together
    public const ROW_LABEL_WIDTH_MM = 30.0;

    /**
     * Build or replace this shooter's active card for the match.
     *
     * We overwrite the shooter's previous card instead of accumulating
     * rows. A reprint always means "the last one is void", and if we
     * kept old rows around the token lookup would have to guess which
     * sheet the camera saw. One active card per shooter removes that
     * ambiguity entirely.
     */
    public function buildForShooter(ShootingMatch $match, Shooter $shooter, ?int $printedBy = null): ScoreSheet
    {
        $rows = $this->rowsForShooter($match, $shooter);

        $layout = $this->composeLayout($rows);
        $token = ScoreSheet::generateToken();
        $layout['qr']['payload'] = ScoreSheet::QR_PREFIX.$token;

        // Reprint rotates the token and layout on the shooter's single
        // active sheet. A claimed spare (now owned by the shooter) is
        // reprinted the same way — we never mint a second row.

        $existing = ScoreSheet::where('match_id', $match->id)
            ->where('shooter_id', $shooter->id)
            ->first();

        if ($existing) {
            $existing->update([
                'token' => $token,
                'layout' => $layout,
                'format_version' => ScoreSheet::FORMAT_VERSION,
                'printed_by' => $printedBy,
                'printed_at' => now(),
            ]);

            return $existing->fresh();
        }

        return ScoreSheet::create([
            'match_id' => $match->id,
            'shooter_id' => $shooter->id,
            'token' => $token,
            'layout' => $layout,
            'format_version' => ScoreSheet::FORMAT_VERSION,
            'printed_by' => $printedBy,
            'printed_at' => now(),
        ]);
    }

    /**
     * Print `count` anonymous spare cards for a match, all with the
     * same class-specific layout.
     *
     * A spare has no `shooter_id` yet — the MD scans it on the day,
     * picks a shooter in the phone, and the sheet is bound atomically
     * at confirm time. Because row *distances* differ between Hunters
     * and Varmint, every spare is class-locked at print; the dual-class
     * caller has to print one stack per class.
     *
     * @return \Illuminate\Support\Collection<int, ScoreSheet>
     */
    public function buildAnonymousSpares(ShootingMatch $match, AlrhaClass $class, int $count, ?int $printedBy = null): Collection
    {
        $count = max(1, min($count, 30));

        $rows = $this->rowsForClass($match, $class);

        $layout = $this->composeLayout($rows);

        $sheets = collect();

        for ($i = 0; $i < $count; $i++) {
            $token = ScoreSheet::generateToken();
            $rowLayout = $layout;
            $rowLayout['qr']['payload'] = ScoreSheet::QR_PREFIX.$token;

            $sheets->push(ScoreSheet::create([
                'match_id' => $match->id,
                'shooter_id' => null,
                'alrha_class' => $class->value,
                'token' => $token,
                'layout' => $rowLayout,
                'format_version' => ScoreSheet::FORMAT_VERSION,
                'printed_by' => $printedBy,
                'printed_at' => now(),
            ]));
        }

        return $sheets;
    }

    /**
     * Shooters on this match that can receive a printable ALRHA card.
     *
     * Dual-class matches partition stages by `alrha_class`, so a shooter
     * with no class can't be assigned any targets and must be filtered
     * out (the UI explains why).
     *
     * `shooterId` lets the MD print exactly one card for a single
     * shooter — the common path for a walk-in who was just added, or
     * a shooter who scribbled on their card and needs a reprint. When
     * set, other filters are ignored so you can't accidentally print
     * nothing by combining incompatible filters.
     *
     * @return Collection<int, Shooter>
     */
    public function printableShooters(ShootingMatch $match, ?int $squadId = null, ?AlrhaClass $classFilter = null, ?int $shooterId = null): Collection
    {
        $query = Shooter::query()
            ->whereIn('squad_id', $match->squads()->select('id'))
            ->where('status', 'active')
            ->orderBy('squad_id')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($shooterId !== null) {
            $query->where('id', $shooterId);
        } elseif ($squadId !== null) {
            $query->where('squad_id', $squadId);
        }

        $shooters = $query->get();

        // Exclude shooters with no class on dual-class ALRHA matches —
        // there are no targets tagged for them, so the card would be
        // empty. The caller surfaces a "skipped: no class set" note.
        $classes = $match->alrhaClasses();
        $isDual = count($classes) > 1;

        return $shooters->filter(function (Shooter $s) use ($isDual, $classFilter, $match) {
            $shooterClass = $s->alrha_class ?? $match->alrhaClass();

            if ($isDual && $shooterClass === null) {
                return false;
            }

            if ($classFilter !== null && $shooterClass !== $classFilter) {
                return false;
            }

            return true;
        })->values();
    }

    /**
     * The target rows this shooter's card will carry, already in
     * firing order (Far → CBC → Near) so every card the match director
     * flips through reads the same way.
     *
     * @return array<int, array{target_id:int,target_label:string,distance_m:int,alrha_block:?string,is_cold_bore:bool,max_shots:int}>
     */
    public function rowsForShooter(ShootingMatch $match, Shooter $shooter): array
    {
        return $this->rowsForClass($match, $shooter->alrha_class ?? $match->alrhaClass());
    }

    /**
     * Same row set as {@see rowsForShooter()} but keyed only on class —
     * the only thing that determines which targets are on the card.
     * Used for anonymous spares, where there is no shooter yet.
     *
     * @return array<int, array{target_id:int,target_label:string,distance_m:int,alrha_block:?string,is_cold_bore:bool,max_shots:int}>
     */
    public function rowsForClass(ShootingMatch $match, ?AlrhaClass $class): array
    {
        $targets = ElrTarget::query()
            ->whereIn('elr_stage_id', $match->elrStages()
                ->when($class, fn ($q) => $q->where(function ($w) use ($class) {
                    // Legacy single-class matches have stages with no
                    // alrha_class tag — still accept those.
                    $w->where('alrha_class', $class->value)
                        ->orWhereNull('alrha_class');
                }))
                ->select('id'))
            ->with('stage')
            ->orderBy('elr_stage_id')
            ->orderBy('sort_order')
            ->get();

        $ordered = $targets->sortBy(function (ElrTarget $t) {
            // Firing order on the day is Cold Bore first, then the far
            // block warm-up, then the near block. The card reads
            // top-to-bottom in that same order so the shooter is never
            // hunting for the next row.
            return match ($t->alrha_block) {
                'cbc' => 0,
                'far' => 1,
                'near' => 2,
                default => 3,
            };
        })->values();

        return $ordered->map(function (ElrTarget $t) {
            return [
                'target_id' => (int) $t->id,
                'target_label' => (string) ($t->name ?? $t->distance_m.' m'),
                'distance_m' => (int) $t->distance_m,
                'alrha_block' => $t->alrha_block,
                'is_cold_bore' => (bool) $t->is_cold_bore,
                'max_shots' => max(1, (int) $t->max_shots),
            ];
        })->all();
    }

    /**
     * Turn the ordered list of target rows into the on-page bubble grid.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function composeLayout(array $rows): array
    {
        $bubbleStartX = self::BLOCK_X_MM + self::ROW_LABEL_WIDTH_MM;
        $bubbleStartY = self::BLOCK_Y_MM + 10.0; // first row sits below the header strip

        $laidOutRows = [];
        $y = $bubbleStartY;
        foreach ($rows as $row) {
            $shots = [];
            for ($shot = 1; $shot <= $row['max_shots']; $shot++) {
                $pairX = $bubbleStartX + ($shot - 1) * self::SHOT_PAIR_WIDTH_MM;
                $hitCx = $pairX + self::BUBBLE_DIAMETER_MM / 2;
                $missCx = $pairX + self::BUBBLE_DIAMETER_MM + self::BUBBLE_GAP_MM + self::BUBBLE_DIAMETER_MM / 2;
                $cy = $y + self::BUBBLE_DIAMETER_MM / 2;

                $shots[] = [
                    'shot_number' => $shot,
                    'hit' => ['x' => round($hitCx, 2), 'y' => round($cy, 2)],
                    'miss' => ['x' => round($missCx, 2), 'y' => round($cy, 2)],
                ];
            }

            $laidOutRows[] = array_merge($row, ['shots' => $shots]);
            $y += self::ROW_HEIGHT_MM;
        }

        return [
            'format' => ScoreSheet::FORMAT_VERSION,
            'page' => [
                'width' => self::PAGE_WIDTH_MM,
                'height' => self::PAGE_HEIGHT_MM,
                'margin' => 10.0,
            ],
            'block' => [
                'x' => self::BLOCK_X_MM,
                'y' => self::BLOCK_Y_MM,
                'width' => self::BLOCK_WIDTH_MM,
                'height' => self::BLOCK_HEIGHT_MM,
            ],
            'fiducials' => [
                ['x' => self::BLOCK_X_MM, 'y' => self::BLOCK_Y_MM],
                ['x' => self::BLOCK_X_MM + self::BLOCK_WIDTH_MM - self::FIDUCIAL_SIZE_MM, 'y' => self::BLOCK_Y_MM],
                ['x' => self::BLOCK_X_MM, 'y' => self::BLOCK_Y_MM + self::BLOCK_HEIGHT_MM - self::FIDUCIAL_SIZE_MM],
                ['x' => self::BLOCK_X_MM + self::BLOCK_WIDTH_MM - self::FIDUCIAL_SIZE_MM, 'y' => self::BLOCK_Y_MM + self::BLOCK_HEIGHT_MM - self::FIDUCIAL_SIZE_MM],
            ],
            'fiducial_size' => self::FIDUCIAL_SIZE_MM,
            'qr' => [
                // Top-right of the block, inside the four fiducials.
                'x' => self::BLOCK_X_MM + self::BLOCK_WIDTH_MM - self::QR_SIZE_MM - self::QR_MARGIN_MM,
                'y' => self::BLOCK_Y_MM + self::QR_MARGIN_MM,
                'size' => self::QR_SIZE_MM,
                'payload' => '', // filled in at persist time once the token exists
            ],
            'bubble' => [
                'diameter' => self::BUBBLE_DIAMETER_MM,
                'gap' => self::BUBBLE_GAP_MM,
            ],
            'rows' => $laidOutRows,
        ];
    }

    /**
     * Count every bubble on the laid-out layout. Used in tests and in
     * the UI to show the match director how dense the sheet is before
     * they hit print.
     */
    public static function totalBubbles(array $layout): int
    {
        $count = 0;
        foreach ($layout['rows'] ?? [] as $row) {
            $count += count($row['shots'] ?? []) * 2;
        }

        return $count;
    }
}
