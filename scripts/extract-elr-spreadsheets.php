<?php
/**
 * One-off helper that reads the Peregrine ELR Challenge and Forster 2 Mile
 * spreadsheets the user attached and dumps a compact PHP array of teams /
 * shooters / calibers / classes / scores per match. Output is consumed by
 * DemoEliteSeasonsSeeder so the seed values mirror the real match-day
 * data without us hand-typing 80 rows.
 *
 * Run:
 *   php scripts/extract-elr-spreadsheets.php \
 *       "<peregrine.xlsm>" "<forster.xlsx>" \
 *       > database/seeders/demo/elite-seasons-data.json
 *
 * Not registered with DatabaseSeeder — this is build-time tooling.
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
            // <si><t>plain</t></si> OR <si><r><t>frag</t></r>...</si>
            if (isset($si->t)) {
                $sharedStrings[] = (string) $si->t;
            } else {
                $parts = [];
                foreach ($si->r as $r) { $parts[] = (string) $r->t; }
                $sharedStrings[] = implode('', $parts);
            }
        }
    }

    // workbook.xml -> sheets[name => rId/sheet number]
    $wb = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
    $sheetMeta = [];
    foreach ($wb->sheets->sheet as $s) {
        $attrs = $s->attributes();
        $sheetMeta[(string) $attrs['name']] = (string) $attrs['sheetId'];
    }

    // Sheets are stored at xl/worksheets/sheetN.xml where N is in the SHEETS
    // ORDER from workbook.xml (not sheetId). Map sheet order index -> file index.
    $orderedSheets = [];
    $i = 1;
    foreach ($wb->sheets->sheet as $s) {
        $orderedSheets[(string) $s->attributes()['name']] = $i;
        $i++;
    }

    $sheets = [];
    foreach ($orderedSheets as $name => $idx) {
        $xml = simplexml_load_string($zip->getFromName("xl/worksheets/sheet{$idx}.xml"));
        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $r = (int) $row['r'];
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r']; // e.g. "AZ4"
                $col = preg_replace('/\d+/', '', $ref);
                $type = (string) ($c['t'] ?? '');
                $v = isset($c->v) ? (string) $c->v : null;

                if ($v === null && isset($c->is->t)) {
                    // inline string
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

function colLetterToIndex(string $col): int
{
    $col = strtoupper($col);
    $n = 0;
    for ($i = 0, $len = strlen($col); $i < $len; $i++) {
        $n = $n * 26 + (ord($col[$i]) - ord('A') + 1);
    }
    return $n; // 1-indexed
}

// ── Peregrine ── header rows: 1=match title, 2=Target 1/2/3, 3=ID|Shooter|Team|Caliber|Class|distances
// Data rows start at 4.
//
// Columns (Peregrine):
//   A=ID  C=Shooter  D=Team  E=Caliber  F=Class
//   G..O  = Warrior        (Target 1 G..I, Target 2 J..L, Target 3 M..O — 3 shots each)
//   P..X  = Brothers Arms
//   Y..AG = Integrix
//   AH..AP = Delta
//   AQ..AY = Zeiss
//   AZ    = Score, BA..BB ignored
function extractShooters(array $sheet, array $stations): array
{
    $shooters = [];
    foreach ($sheet as $rowNum => $cells) {
        // Rows 1-9 are header / formula reference. Real shooter rows start at row 10.
        if ($rowNum < 10) continue;
        $name = trim($cells['C'] ?? '');
        if ($name === '' || $name === '(blank)' || $name === 'Shooter') continue;

        $team = trim($cells['D'] ?? '');
        $caliber = trim($cells['E'] ?? '');
        $class = trim($cells['F'] ?? '');
        $score = $cells['AZ'] ?? null;

        // Pull the 5 stations × 3 targets × 3 shots = 45 hit/miss values.
        $shotsByStation = [];
        foreach ($stations as $stationName => $startCol) {
            $startIdx = colLetterToIndex($startCol);
            $targets = [];
            for ($t = 0; $t < 3; $t++) {
                $shots = [];
                for ($s = 0; $s < 3; $s++) {
                    $colIdx = $startIdx + ($t * 3) + $s;
                    $col = '';
                    $n = $colIdx;
                    while ($n > 0) {
                        $rem = ($n - 1) % 26;
                        $col = chr(ord('A') + $rem) . $col;
                        $n = intdiv($n - 1, 26);
                    }
                    $v = $cells[$col] ?? null;
                    $shots[] = $v === '1' || $v === 1 ? 1 : 0;
                }
                $targets[] = $shots;
            }
            $shotsByStation[$stationName] = $targets;
        }

        // We only need the roster for seeding — drop the per-shot payload.
        unset($shotsByStation);
        $shooters[] = [
            'name'    => $name,
            'team'    => $team,
            'caliber' => $caliber,
            'class'   => $class, // "Minor" or "Major"
        ];
    }
    return $shooters;
}

$tmp = $argv[1] ?? null;
if (! $tmp) {
    fwrite(STDERR, "Usage: php _extract-elr.php <path-to-peregrine.xlsx> [<path-to-forster.xlsx>]\n");
    exit(1);
}

$peregrineSheets = readXlsx($argv[1]);
$peregrineShooters = extractShooters(
    $peregrineSheets['Data'] ?? [],
    [
        'Warrior'       => 'G',
        'Brothers Arms' => 'P',
        'Integrix'      => 'Y',
        'Delta'         => 'AH',
        'Zeiss'         => 'AQ',
    ]
);

$out = [
    'peregrine' => [
        'match_name' => 'Peregrine ELR Challenge — 7 March 2026',
        'match_date' => '2026-03-07',
        'shooters'   => $peregrineShooters,
    ],
];

if (isset($argv[2]) && file_exists($argv[2])) {
    $forsterSheets = readXlsx($argv[2]);
    // Scores sheet layout:
    //   Row 2: distances per station (D2=2024, I2=2478, N2=2836, S2=3272) — Heat 1
    //   Row 3: per-shot multipliers (2.0/1.75/1.5/1.25/1.0)
    //   Row 6: column headers (A=#, B=Name, C=Caliber, D..H=A1-A5, I..M=B1-B5, ...)
    //   Row 7+: shooter rows
    $forsterShooters = [];
    foreach ($forsterSheets['Scores'] ?? [] as $rowNum => $cells) {
        if ($rowNum < 7) continue;
        $name = trim($cells['B'] ?? '');
        if ($name === '' || $name === 'Name') continue;
        $forsterShooters[] = [
            'bib'     => $cells['A'] ?? null,
            'name'    => $name,
            'caliber' => trim($cells['C'] ?? ''),
        ];
    }

    $out['forster'] = [
        'match_name' => 'Forster 2 Mile Challenge — 28 November 2025 (Heat 1)',
        'match_date' => '2025-11-28',
        'station_distances' => [
            'A' => 2024,
            'B' => 2478,
            'C' => 2836,
            'D' => 3272,
        ],
        'per_shot_multipliers' => [2.0, 1.75, 1.5, 1.25, 1.0],
        'shooters' => $forsterShooters,
    ];
}

$outPath = $argv[3] ?? null;
if ($outPath) {
    file_put_contents($outPath, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fwrite(STDERR, "Wrote " . strlen(file_get_contents($outPath)) . " bytes to $outPath\n");
} else {
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
