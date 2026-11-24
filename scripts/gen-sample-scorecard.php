<?php

/**
 * Ad-hoc script: generate a sample ALRHA scorecard PDF against the
 * local Laragon DB and dump it to disk so the user can inspect it.
 *
 * Usage (from project root):
 *   php artisan tinker --execute="require 'scripts/gen-sample-scorecard.php';"
 *
 * Or:
 *   php scripts/gen-sample-scorecard.php
 *
 * Writes:
 *   storage/app/sample-assigned-card.pdf   (one card for an existing shooter)
 *   storage/app/sample-spare-cards.pdf     (3 unassigned walk-in spares)
 */

use App\Enums\AlrhaClass;
use App\Models\ScoreSheet;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Services\PdfDocumentRenderer;
use App\Services\Scoring\ScoreSheetLayoutBuilder;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel when run as `php scripts/...`.
if (! class_exists(\Illuminate\Foundation\Application::class)
    || ! app() instanceof \Illuminate\Foundation\Application) {
    require __DIR__.'/../vendor/autoload.php';
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
}

/** @var ShootingMatch|null $match */
$match = ShootingMatch::where('scoring_type', 'alrha')
    ->with(['squads.shooters' => fn ($q) => $q->where('status', 'active')])
    ->first();

if (! $match) {
    fwrite(STDERR, "No ALRHA match found in the local DB.\n");
    exit(1);
}

$shooter = Shooter::whereIn('squad_id', $match->squads->pluck('id'))
    ->where('status', 'active')
    ->first();

if (! $shooter) {
    fwrite(STDERR, "ALRHA match {$match->id} has no active shooters.\n");
    exit(1);
}

$builder = app(ScoreSheetLayoutBuilder::class);
$pdf = app(PdfDocumentRenderer::class);

$outDir = __DIR__.'/../storage/app';

// ─── 1. Assigned card for an existing shooter ────────────────────────
echo "[1/2] Building assigned card for shooter #{$shooter->id} ({$shooter->name}) on match #{$match->id} ({$match->name})...\n";

$assignedSheet = DB::transaction(fn () => $builder->buildForShooter($match, $shooter, null));

$assignedPdf = $pdf->generate('exports.alrha-score-cards', [
    'match' => $match,
    'cards' => [[
        'sheet' => $assignedSheet->fresh(),
        'shooter' => $shooter->load(['squad', 'team', 'division']),
        'spare' => null,
    ]],
]);

$assignedPath = realpath($outDir).DIRECTORY_SEPARATOR.'sample-assigned-card.pdf';
file_put_contents($assignedPath, $assignedPdf);
echo "      → $assignedPath (".number_format(strlen($assignedPdf))." bytes)\n";

// ─── 2. Spare card stack (3 walk-ins) ────────────────────────────────
$classes = $match->alrhaClasses();
$spareClass = $classes[0] ?? AlrhaClass::Varmint;

echo "[2/2] Building 3 anonymous spare cards (class: {$spareClass->label()})...\n";

$spares = DB::transaction(fn () => $builder->buildAnonymousSpares($match, $spareClass, 3, null));

$i = 0;
$total = $spares->count();
$spareCards = $spares->map(fn ($sheet) => [
    'sheet' => $sheet,
    'shooter' => null,
    'spare' => ['index' => ++$i, 'total' => $total, 'class' => $spareClass],
])->all();

$sparePdf = $pdf->generate('exports.alrha-score-cards', [
    'match' => $match,
    'cards' => $spareCards,
]);

$sparePath = realpath($outDir).DIRECTORY_SEPARATOR.'sample-spare-cards.pdf';
file_put_contents($sparePath, $sparePdf);
echo "      → $sparePath (".number_format(strlen($sparePdf))." bytes)\n";

echo "\nDone. Tokens printed on the cards:\n";
echo "  Assigned : {$assignedSheet->token}\n";
foreach ($spares as $i => $s) {
    echo "  Spare ".($i + 1)." : {$s->token}\n";
}
