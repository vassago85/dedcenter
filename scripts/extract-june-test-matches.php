<?php
/**
 * Build-time helper: reads the June 2026 Peregrine ELR + Forster 2 Mile
 * spreadsheets and emits a compact JSON payload consumed by
 * DemoJuneTestMatchesSeeder. Captures the *real* squadding, shooting order,
 * per-station distance ladders, divisions and shot multipliers so the two
 * weekend test matches mirror match-day exactly.
 *
 * No PhpSpreadsheet dependency — parses the OOXML zip directly.
 *
 * Run:
 *   php scripts/extract-june-test-matches.php \
 *       "<peregrine.xlsm>" "<forster.xlsx>" \
 *       database/seeders/demo/june-2026-test-matches.json
 *
 * Not registered with DatabaseSeeder — throwaway tooling.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

function readXlsx(string $path): array
{
    if (! file_exists($path)) {
        throw new RuntimeException("Missing file: $path");
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException("Cannot open xlsx: $path");
    }

    $sharedStrings = [];
    if (($ss = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
        $xml = simplexml_load_string($ss);
        foreach ($xml->si as $si) {
            if (isset($si->t)) {
                $sharedStrings[] = (string) $si->t;
            } else {
                $parts = [];
                foreach ($si->r as $r) { $parts[] = (string) $r->t; }
                $sharedStrings[] = implode('', $parts);
            }
        }
    }

    $wb = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
    $orderedSheets = [];
    $i = 1;
    foreach ($wb->sheets->sheet as $s) {
        $orderedSheets[(string) $s->attributes()['name']] = $i;
        $i++;
    }

    $sheets = [];
    foreach ($orderedSheets as $name => $idx) {
        $raw = $zip->getFromName("xl/worksheets/sheet{$idx}.xml");
        if ($raw === false) { $sheets[$name] = []; continue; }
        $xml = simplexml_load_string($raw);
        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $r = (int) $row['r'];
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $col = preg_replace('/\d+/', '', $ref);
                $type = (string) ($c['t'] ?? '');
                $v = isset($c->v) ? (string) $c->v : null;
                if ($v === null && isset($c->is->t)) {
                    $cells[$col] = (string) $c->is->t;
                    continue;
                }
                if ($type === 's' && $v !== null) {
                    $cells[$col] = $sharedStrings[(int) $v] ?? null;
                } else {
                    $cells[$col] = $v;
                }
            }
            $rows[$r] = $cells;
        }
        $sheets[$name] = $rows;
    }

    $zip->close();
    return $sheets;
}

/** Clean a cell value to a trimmed string ('' when blank/(blank)). */
function cv(array $cells, string $col): string
{
    $v = trim((string) ($cells[$col] ?? ''));
    return ($v === '(blank)') ? '' : $v;
}

// ───────────────────────── Peregrine ─────────────────────────
//
// Data sheet layout (June 2026):
//   r1 : station banner   F=Warrior O=Brothers Arms X=Integrix AG=Delta AP=Zeiss
//   r3 : col headers (A=Squad B=Shooter C=Team D=Caliber E=Class)
//        + MINOR distances per target (F,I,L | O,R,U | X,AA,AD | AG,AJ,AM | AP,AS,AV)
//   r4 : MAJOR distances per target (same columns, shifted one rung further out)
//   r5 : per-shot multipliers (1.5 / 1.25 / 1.0)
//   r8+: shooter rows (A=squad number, B=name, C=team, D=caliber, E=class)
//
// Each station is a 4-rung ladder: [minorT1, minorT2, minorT3, majorT3].
// Minor engages rungs 1-3, Major engages rungs 2-4.

$peregrine = readXlsx($argv[1]);
$data = $peregrine['Data'] ?? [];

$stationCols = [
    'Warrior'       => 'F',
    'Brothers Arms' => 'O',
    'Integrix'      => 'X',
    'Delta Optics'  => 'AG',
    'Zeiss Optics'  => 'AP',
];

function colToIdx(string $col): int
{
    $col = strtoupper($col); $n = 0;
    for ($i = 0, $l = strlen($col); $i < $l; $i++) {
        $n = $n * 26 + (ord($col[$i]) - ord('A') + 1);
    }
    return $n;
}
function idxToCol(int $n): string
{
    $col = '';
    while ($n > 0) { $rem = ($n - 1) % 26; $col = chr(ord('A') + $rem) . $col; $n = intdiv($n - 1, 26); }
    return $col;
}

$minorRow = $data[3] ?? [];
$majorRow = $data[4] ?? [];

$stations = [];
foreach ($stationCols as $name => $startCol) {
    $base = colToIdx($startCol);
    // Targets are spaced 3 columns apart (3 shots per target).
    $minorT1 = (float) ($minorRow[idxToCol($base)] ?? 0);
    $minorT2 = (float) ($minorRow[idxToCol($base + 3)] ?? 0);
    $minorT3 = (float) ($minorRow[idxToCol($base + 6)] ?? 0);
    $majorT3 = (float) ($majorRow[idxToCol($base + 6)] ?? 0);

    $stations[] = [
        'label'   => $name,
        'sponsor' => $name,
        // ladder rung 1..4 — Minor=1-3, Major=2-4
        'targets' => [
            (int) round($minorT1),
            (int) round($minorT2),
            (int) round($minorT3),
            (int) round($majorT3),
        ],
    ];
}

// Per-shot multipliers from r5 (Warrior T1 cols F,G,H).
$mRow = $data[5] ?? [];
$multipliers = [
    (float) ($mRow['F'] ?? 1.5),
    (float) ($mRow['G'] ?? 1.25),
    (float) ($mRow['H'] ?? 1.0),
];

$peregrineShooters = [];
foreach ($data as $rowNum => $cells) {
    if ($rowNum < 8) continue;
    $name = cv($cells, 'B');
    if ($name === '' || $name === 'Shooter') continue;
    $peregrineShooters[] = [
        'squad'   => (int) (cv($cells, 'A') ?: 0),
        'name'    => $name,
        'team'    => cv($cells, 'C'),
        'caliber' => cv($cells, 'D'),
        'class'   => cv($cells, 'E') ?: 'Minor',
    ];
}

// ───────────────────────── Forster ─────────────────────────
//
// Scores sheet layout (June 2026):
//   r2 : TARGET DISTANCE  D=2024 I=2478 N=2836 S=3272
//   r3 : TARGET MULTIPLIER per shot (D..H = 2/1.75/1.5/1.25/1) ×4 stations
//   r5 : SHOT 1..5
//   r6 : headers (A=# B=Name C=Cartridge ...)
//   r7+: shooter rows

$forster = readXlsx($argv[2]);
$scores = $forster['Scores'] ?? [];

$distRow = $scores[2] ?? [];
$forsterStations = [
    ['label' => 'Station A', 'distance' => (int) round((float) ($distRow['D'] ?? 2024))],
    ['label' => 'Station B', 'distance' => (int) round((float) ($distRow['I'] ?? 2478))],
    ['label' => 'Station C', 'distance' => (int) round((float) ($distRow['N'] ?? 2836))],
    ['label' => 'Station D', 'distance' => (int) round((float) ($distRow['S'] ?? 3272))],
];

$multRow = $scores[3] ?? [];
$forsterMultipliers = [
    (float) ($multRow['D'] ?? 2.0),
    (float) ($multRow['E'] ?? 1.75),
    (float) ($multRow['F'] ?? 1.5),
    (float) ($multRow['G'] ?? 1.25),
    (float) ($multRow['H'] ?? 1.0),
];

$forsterShooters = [];
foreach ($scores as $rowNum => $cells) {
    if ($rowNum < 7) continue;
    $name = cv($cells, 'B');
    if ($name === '' || $name === 'Name') continue;
    $forsterShooters[] = [
        'bib'     => (int) (cv($cells, 'A') ?: ($rowNum - 6)),
        'name'    => $name,
        'caliber' => cv($cells, 'C'),
    ];
}

$out = [
    'peregrine' => [
        'match_name'  => 'Peregrine ELR Challenge — Test Match (Jun 2026)',
        'match_date'  => '2026-06-06',
        'stations'    => $stations,
        'multipliers' => $multipliers,
        'shooters'    => $peregrineShooters,
    ],
    'forster' => [
        'match_name'  => 'Forster 2 Mile Challenge — Test Match (Jun 2026)',
        'match_date'  => '2026-06-06',
        'stations'    => $forsterStations,
        'multipliers' => $forsterMultipliers,
        'shooters'    => $forsterShooters,
    ],
];

$outPath = $argv[3] ?? null;
if ($outPath) {
    file_put_contents($outPath, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fwrite(STDERR, sprintf(
        "Wrote %d bytes — Peregrine: %d shooters, Forster: %d shooters\n",
        strlen(file_get_contents($outPath)),
        count($peregrineShooters),
        count($forsterShooters)
    ));
} else {
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
