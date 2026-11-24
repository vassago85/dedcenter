<?php

namespace App\Http\Controllers;

use App\Enums\AlrhaClass;
use App\Models\Organization;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\ScoreSheet;
use App\Services\PdfDocumentRenderer;
use App\Services\Scoring\ScoreSheetLayoutBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Print the ALRHA mark-sense score cards for a match.
 *
 * One A4 card per shooter. The controller builds (or rebuilds) the
 * sheet record for each eligible shooter so the stored layout and the
 * printed page always match — a reprint always gets a fresh token.
 *
 * Two endpoints live here:
 *   show()  — a small "configure the print" screen that filters by
 *             squad and class
 *   print() — the PDF stream the user downloads / opens
 *
 * Both are match-director-scoped: `can('score', $match)` matches what
 * the scoring tab already demands.
 */
class ScoreSheetPrintController extends Controller
{
    public function __construct(private readonly ScoreSheetLayoutBuilder $builder, private readonly PdfDocumentRenderer $pdf) {}

    public function show(Request $request, ShootingMatch $match)
    {
        $organization = $this->resolveOrganization($request);
        $this->authorize($request, $organization, $match);

        if (! $match->isAlrha()) {
            abort(404, 'Mark-sense cards are only available for ALRHA matches.');
        }

        $squads = $match->squads()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $classes = $match->alrhaClasses();
        $allShooters = $this->builder->printableShooters($match);
        $allRoster = Shooter::whereIn('squad_id', $match->squads()->select('id'))
            ->where('status', 'active')
            ->count();

        $skipped = $allRoster - $allShooters->count();

        // The eligible roster feeds the "Reprint a single shooter"
        // picker. Preloaded as an array so the Blade can render a native
        // <select> without an extra Livewire round-trip.
        $shooterOptions = $allShooters->load('squad')->map(function (Shooter $s) {
            $parts = [$s->name];
            if ($s->bib_number) $parts[] = '#'.$s->bib_number;
            if ($s->squad) $parts[] = $s->squad->name;
            if ($s->alrha_class) $parts[] = $s->alrha_class->label();

            return [
                'id' => $s->id,
                'label' => implode(' · ', $parts),
            ];
        })->values()->all();

        $isDual = count($classes) > 1;

        // Spare-stack snapshot: how many unclaimed spares already exist
        // per class, so the UI doesn't accidentally encourage printing
        // another 20 when yesterday's 15 are still in the drawer.
        $spareCounts = ScoreSheet::where('match_id', $match->id)
            ->unclaimed()
            ->selectRaw('alrha_class, COUNT(*) as c')
            ->groupBy('alrha_class')
            ->pluck('c', 'alrha_class')
            ->all();

        return view('pages.matches.score-cards-print', [
            'match' => $match,
            'organization' => $organization,
            'squads' => $squads,
            'classes' => $classes,
            'shooterOptions' => $shooterOptions,
            'printableCount' => $allShooters->count(),
            'skippedCount' => $skipped,
            'isAdmin' => $organization === null,
            'isDualClass' => $isDual,
            'spareCounts' => $spareCounts,
        ]);
    }

    /**
     * Print a stack of anonymous spare cards for walk-ins.
     *
     * One sheet per card is created with `shooter_id = NULL` and the
     * chosen class baked into the frozen layout. On the day, the MD
     * scans the card, picks a shooter in the phone, and the sheet is
     * bound atomically before the shots are written.
     */
    public function printSpares(Request $request, ShootingMatch $match)
    {
        $organization = $this->resolveOrganization($request);
        $this->authorize($request, $organization, $match);

        if (! $match->isAlrha()) {
            abort(404, 'Mark-sense cards are only available for ALRHA matches.');
        }

        $validated = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:30'],
            // Required only on dual-class matches. Enforced below once we
            // know which the match is, to give a clearer error than
            // "the class field is required" on a single-class match.
            'class' => ['nullable', 'string', 'in:hunters,varmint'],
        ]);

        $matchClass = $match->alrhaClass();
        $isDual = count($match->alrhaClasses()) > 1;

        if ($isDual && empty($validated['class'])) {
            abort(422, 'Pick a class for the spare cards — Hunters and Varmint have different distances.');
        }

        $class = isset($validated['class'])
            ? AlrhaClass::from($validated['class'])
            : $matchClass;

        if ($class === null) {
            abort(422, 'This match has no ALRHA class configured. Open Match setup first.');
        }

        $printerId = $request->user()?->id;

        $spares = DB::transaction(function () use ($match, $class, $validated, $printerId) {
            return $this->builder->buildAnonymousSpares($match, $class, (int) $validated['count'], $printerId);
        });

        // The export template expects {sheet, shooter} rows. Spares have
        // no shooter — we hand a `null` through and the Blade renders a
        // "WRITE NAME HERE" header instead of a pre-filled identity.
        $spareIndex = 0;
        $totalSpares = $spares->count();
        $cards = $spares->map(function ($sheet) use (&$spareIndex, $totalSpares, $class) {
            return [
                'sheet' => $sheet,
                'shooter' => null,
                'spare' => [
                    'index' => ++$spareIndex,
                    'total' => $totalSpares,
                    'class' => $class,
                ],
            ];
        })->all();

        $filename = sprintf(
            'alrha-spare-cards-%s-%s-%s.pdf',
            Str::slug($match->name ?: 'match'),
            $class->value,
            now()->format('YmdHi'),
        );

        return $this->pdf->stream(
            'exports.alrha-score-cards',
            ['match' => $match, 'cards' => $cards],
            $filename,
        );
    }

    public function print(Request $request, ShootingMatch $match)
    {
        $organization = $this->resolveOrganization($request);
        $this->authorize($request, $organization, $match);

        if (! $match->isAlrha()) {
            abort(404, 'Mark-sense cards are only available for ALRHA matches.');
        }

        $validated = $request->validate([
            'squad_id' => ['nullable', 'integer'],
            'class' => ['nullable', 'string', 'in:hunters,varmint'],
            'shooter_id' => ['nullable', 'integer'],
        ]);

        $squadId = $validated['squad_id'] ?? null;
        if ($squadId !== null) {
            // Belongs-to-this-match check; stops a scoped print from
            // pulling a squad that belongs to someone else's event.
            $owned = Squad::where('id', $squadId)->where('match_id', $match->id)->exists();
            abort_unless($owned, 404, 'Squad not found on this match.');
        }

        $shooterId = $validated['shooter_id'] ?? null;
        if ($shooterId !== null) {
            // Same belongs-to check so a single-shooter reprint can't
            // pull a foreign shooter in from another match.
            $owned = Shooter::where('id', $shooterId)
                ->whereIn('squad_id', $match->squads()->select('id'))
                ->exists();
            abort_unless($owned, 404, 'Shooter not found on this match.');
        }

        $classFilter = isset($validated['class']) ? AlrhaClass::from($validated['class']) : null;

        $shooters = $this->builder->printableShooters($match, $squadId, $classFilter, $shooterId);

        if ($shooters->isEmpty()) {
            abort(404, 'Nothing to print — no eligible active shooters in that selection.');
        }

        $shooters->load(['squad', 'team', 'division']);

        $printerId = $request->user()?->id;

        $cards = DB::transaction(function () use ($match, $shooters, $printerId) {
            $out = [];
            foreach ($shooters as $shooter) {
                $sheet = $this->builder->buildForShooter($match, $shooter, $printerId);
                $out[] = ['sheet' => $sheet->fresh(), 'shooter' => $shooter, 'spare' => null];
            }

            return $out;
        });

        $filename = sprintf(
            'alrha-score-cards-%s-%s.pdf',
            Str::slug($match->name ?: 'match'),
            now()->format('YmdHi'),
        );

        return $this->pdf->stream(
            'exports.alrha-score-cards',
            ['match' => $match, 'cards' => $cards],
            $filename,
        );
    }

    private function authorize(Request $request, ?Organization $organization, ShootingMatch $match): void
    {
        $user = $request->user();
        abort_unless($user?->can('score', $match), 403, 'You are not authorized to print score cards for this match.');

        if ($organization !== null) {
            abort_unless($match->organization_id === $organization->id, 404);
        }
    }

    /**
     * The org variant of the route carries an {organization} segment;
     * the admin variant does not. Pull it from the route parameters
     * explicitly so the controller signature stays uncluttered and
     * Laravel's container never has to invent an empty Organization.
     */
    private function resolveOrganization(Request $request): ?Organization
    {
        $org = $request->route('organization');

        return $org instanceof Organization ? $org : null;
    }
}
