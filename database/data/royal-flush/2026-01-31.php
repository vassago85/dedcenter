<?php

/**
 * Royal Flush Gong Shoot — 31 January 2026
 *
 * Source: results spreadsheet screenshot supplied by the MD.
 * See database/data/royal-flush/2026-02-21.php for the base format notes.
 *
 * Per-match multiplier overrides (different from Feb/Mar matches):
 *   Gongs:      1.00, 1.25, 1.50, 1.75, 2.00   (G1 biggest → G5 smallest)
 *   Distances:  400m, 500m, 600m, 700m
 *   Max score:  22 × 7.5 = 165.0
 *
 * The source spreadsheet's Hit Rate / Relative Score columns use a
 * non-obvious formula that doesn't reconcile with a 20-shot grid
 * (Pos 1 shows "100.00%" at score 132 ≠ 165). Rather than guess at
 * the column, we omit hit_pct / hits and let the importer's auto-hits
 * mode pick the highest hit count consistent with the documented
 * score — shooter intuition of "miss the hardest gongs first".
 *
 * TRANSCRIPTION CAVEATS (spot-check before live import):
 *   · Some names / cartridges are OCR best-effort from a dense
 *     screenshot (flagged inline with a TODO comment).
 *   · Pos 65 & Pos 78 both read "Brandon Ulrich" — possible duplicate
 *     or two different shooters sharing a name.
 *   · Pos 94–96 read "Abdul Aziz Amod" three times — possible
 *     duplicate relay entries or separate shooters; verify.
 */

return [
    'meta' => [
        'name' => 'Royal Flush Gong Shoot — 31 Jan 2026',
        'date' => '2026-01-31',
        'organization_slug' => 'royal-flush',
        'location' => null,
        'gong_multipliers' => [1.00, 1.25, 1.50, 1.75, 2.00],
        'distances' => [400, 500, 600, 700],
    ],

    'shooters' => [
        ['pos' => 1,  'name' => 'Warren Britnell',           'cartridge' => '7mm PRC',         'score' => 132],
        ['pos' => 2,  'name' => 'Johan Lottering',           'cartridge' => '22 BR',           'score' => 131],
        ['pos' => 3,  'name' => 'Gerrit van Rooyen',         'cartridge' => '308 AI',          'score' => 125],
        ['pos' => 4,  'name' => 'Morton Mynhardt',           'cartridge' => '6.5 PRC',         'score' => 124],
        ['pos' => 5,  'name' => 'Shaun Flink',               'cartridge' => '284 Shehane',     'score' => 124],
        ['pos' => 6,  'name' => 'Danie Koch',                'cartridge' => '7 RSAUM',         'score' => 123],
        ['pos' => 7,  'name' => 'Louis Coetzen',             'cartridge' => '6.5 PRC',         'score' => 123],
        ['pos' => 8,  'name' => 'Johan Nel',                 'cartridge' => '25x47',           'score' => 121],
        ['pos' => 9,  'name' => 'Jaco Minnaar',              'cartridge' => '6.5 Creedmoor',   'score' => 119],
        ['pos' => 10, 'name' => 'Schalk van der Merwe',      'cartridge' => '6.5 Creedmoor',   'score' => 118],
        ['pos' => 11, 'name' => 'Shaun Snyman',              'cartridge' => '300 WSM',         'score' => 115],
        ['pos' => 12, 'name' => 'DW de Klerk',               'cartridge' => '7mm RSAUM',       'score' => 115],
        ['pos' => 13, 'name' => 'Danie Bierman',             'cartridge' => '7 PRC',           'score' => 113],
        ['pos' => 14, 'name' => 'Martin Prevoo',             'cartridge' => '7 RSAUM',         'score' => 111],
        ['pos' => 15, 'name' => 'Steven Coombs',             'cartridge' => '7 PRC',           'score' => 110],
        ['pos' => 16, 'name' => 'Frank vd Merwe',            'cartridge' => '7mm SAUM',        'score' => 109],
        ['pos' => 17, 'name' => 'Wouter Louw',               'cartridge' => '7 RSAUM',         'score' => 109],
        ['pos' => 18, 'name' => 'Andries de Beer',           'cartridge' => '7 RSAUM',         'score' => 108],
        ['pos' => 19, 'name' => 'Pieter Grobler',            'cartridge' => '6mm Dasher',      'score' => 108],
        ['pos' => 20, 'name' => 'Deon de Villiers',          'cartridge' => '300 Norma',       'score' => 108],
        ['pos' => 21, 'name' => 'Erwin Potgieter',           'cartridge' => '7 SAUM',          'score' => 108],
        ['pos' => 22, 'name' => 'Danie du Preez',            'cartridge' => '6.5 Creedmoor',   'score' => 108],
        ['pos' => 23, 'name' => 'Ronan Gamiza',              'cartridge' => '6.5 PRC',         'score' => 104],
        ['pos' => 24, 'name' => 'Jacco Christl',             'cartridge' => '6.5x47 Lapua',    'score' => 103],
        ['pos' => 25, 'name' => 'Johan Smith',               'cartridge' => '270 Win',         'score' => 101],
        ['pos' => 26, 'name' => 'AJ Snyman',                 'cartridge' => '6 Dasher',        'score' => 101],
        ['pos' => 27, 'name' => 'Jason McLean',              'cartridge' => '6mm Dasher',      'score' => 99],
        ['pos' => 28, 'name' => 'Nicole Stipp',              'cartridge' => '6mm',             'score' => 99],
        ['pos' => 29, 'name' => 'Donovan Cook',              'cartridge' => '25 SC',           'score' => 98],
        ['pos' => 30, 'name' => 'Giel van der Vernier',      'cartridge' => '6.5x47',          'score' => 97],
        ['pos' => 31, 'name' => 'Tiaan Mostert',             'cartridge' => '6.5x47 Lapua',    'score' => 97],
        ['pos' => 32, 'name' => 'Stephan van der Merwe',     'cartridge' => '6.5x47 Lapua',    'score' => 96],
        ['pos' => 33, 'name' => 'Konrad Diobo',              'cartridge' => '270 WSM',         'score' => 95],
        ['pos' => 34, 'name' => 'Ashley Erasmus',            'cartridge' => '300 PRC',         'score' => 94],
        ['pos' => 35, 'name' => 'Johan Blankenberg',         'cartridge' => '7mm SAUM',        'score' => 93],
        ['pos' => 36, 'name' => 'Paul Charsley',             'cartridge' => '6.5 Creedmoor',   'score' => 93],
        ['pos' => 37, 'name' => 'Daniel Bonthuys',           'cartridge' => '6.5 Creedmoor',   'score' => 93],
        ['pos' => 38, 'name' => 'Gerhardu Odendaal',         'cartridge' => '300 WSM',         'score' => 93],
        ['pos' => 39, 'name' => 'Trevor Cestman',            'cartridge' => '6.5 CM',          'score' => 91],
        ['pos' => 40, 'name' => 'Brandon Peket',             'cartridge' => '6.5 PRC',         'score' => 91],
        ['pos' => 41, 'name' => 'Jaco Venter',               'cartridge' => '6.5 Creedmoor',   'score' => 90],
        ['pos' => 42, 'name' => 'Ruan du Plessis',           'cartridge' => '260 Remington',   'score' => 90],
        ['pos' => 43, 'name' => 'Christo Louw',              'cartridge' => '7 SAUM',          'score' => 89],
        ['pos' => 44, 'name' => 'Quinten Kok',               'cartridge' => '6 Dasher',        'score' => 88],
        ['pos' => 45, 'name' => 'Thys Fourie',               'cartridge' => '6.5 Creedmoor',   'score' => 88],
        ['pos' => 46, 'name' => 'Corne van As',              'cartridge' => '6mm Dasher',      'score' => 88],
        ['pos' => 47, 'name' => 'Marc Koen',                 'cartridge' => '6mm Dasher',      'score' => 84],
        ['pos' => 48, 'name' => 'Danie Viljoen',             'cartridge' => '6mm',             'score' => 84],
        ['pos' => 49, 'name' => 'Liesl le Fo',               'cartridge' => '7 SAUM',          'score' => 84],
        ['pos' => 50, 'name' => 'Sean van Wyk',              'cartridge' => '300 Win Mag',     'score' => 83],
        ['pos' => 51, 'name' => 'Devan Kell',                'cartridge' => '308 Win',         'score' => 83],
        ['pos' => 52, 'name' => 'Theo Botha',                'cartridge' => '7mm RSAUM',       'score' => 81],
        ['pos' => 53, 'name' => 'Kobie Nel',                 'cartridge' => '7 RSAUM',         'score' => 80],
        ['pos' => 54, 'name' => 'JD Lis',                    'cartridge' => '308 Win',         'score' => 76],
        ['pos' => 55, 'name' => 'Franco Wild',               'cartridge' => '284 Winchester',  'score' => 76],
        ['pos' => 56, 'name' => 'Wilfred Robson',            'cartridge' => '6.5 Creedmoor',   'score' => 74],
        ['pos' => 57, 'name' => 'Gerhard Conje',             'cartridge' => '270 WSM',         'score' => 72],
        ['pos' => 58, 'name' => 'Jannie Jacobs',             'cartridge' => '300 Weatherby',   'score' => 72],
        ['pos' => 59, 'name' => 'Brian Beeming',             'cartridge' => '6.5 Creedmoor',   'score' => 72],
        ['pos' => 60, 'name' => 'Bruce Cronje',              'cartridge' => '6.5 Creedmoor',   'score' => 72],
        ['pos' => 61, 'name' => 'Petrus Wassermann',         'cartridge' => '6.5x55 SE',       'score' => 69],
        ['pos' => 62, 'name' => 'Andre du Preez',            'cartridge' => '7 PRC',           'score' => 68],
        ['pos' => 63, 'name' => 'Francois Davel',            'cartridge' => '308 Win',         'score' => 65],
        ['pos' => 64, 'name' => 'Chris Redelinghuys',        'cartridge' => '7 PRC',           'score' => 65],
        ['pos' => 65, 'name' => 'Brandon Ulrich',            'cartridge' => '6.5 Creedmoor',   'score' => 65],
        ['pos' => 66, 'name' => 'Robetis Axocarp',           'cartridge' => '6.5 Creedmoor',   'score' => 64],
        ['pos' => 67, 'name' => 'Zander Swart',              'cartridge' => '.300 Win Mag',    'score' => 62],
        ['pos' => 68, 'name' => 'Jose Alves',                'cartridge' => '6.5 PRC',         'score' => 62],
        ['pos' => 69, 'name' => 'Werner Bonthuys',           'cartridge' => '6.5 Creedmoor',   'score' => 60],
        ['pos' => 70, 'name' => 'Michael Coutinho',          'cartridge' => '6.5 Creedmoor',   'score' => 58],
        ['pos' => 71, 'name' => 'Michael Nortje',            'cartridge' => '7mm SAUM',        'score' => 58],
        ['pos' => 72, 'name' => 'Robert Meintjes',           'cartridge' => '6.5 SAUM',        'score' => 58],
        ['pos' => 73, 'name' => 'Craig van der Vyver',       'cartridge' => '6.5 CM',          'score' => 53],
        ['pos' => 74, 'name' => 'Jeane van der Merwe',       'cartridge' => '6.5 CM',          'score' => 53],
        ['pos' => 75, 'name' => 'Steek de Klerk',            'cartridge' => '6.5 CM',          'score' => 53],
        ['pos' => 76, 'name' => 'Walter de Kock',            'cartridge' => '6.5 CM',          'score' => 53],
        ['pos' => 77, 'name' => 'Imanuel Coutinho',          'cartridge' => '243 Win',         'score' => 45],
        ['pos' => 78, 'name' => 'Brandon Ulrich 2',          'cartridge' => '6.5 Creedmoor',   'score' => 43],
        ['pos' => 79, 'name' => 'Ismail Arbee',              'cartridge' => '6mm Creedmoor',   'score' => 38],
        ['pos' => 80, 'name' => 'Emil Engelbrecht',          'cartridge' => '6.5 Creedmoor',   'score' => 35],
        ['pos' => 81, 'name' => 'Pieter Veister',            'cartridge' => '7mm SS',          'score' => 33],
        ['pos' => 82, 'name' => 'Tiaan Coetzee',             'cartridge' => '6.5 PRC',         'score' => 33],
        ['pos' => 83, 'name' => 'Scott Flora',               'cartridge' => '6.5 Creedmoor',   'score' => 32],
        ['pos' => 84, 'name' => 'Renier Koekemoer',          'cartridge' => '308 Win',         'score' => 31],
        ['pos' => 85, 'name' => 'Duan Viljoen',              'cartridge' => '6.5 Creedmoor',   'score' => 29],
        ['pos' => 86, 'name' => 'Mike Nel',                  'cartridge' => '6.5 Creedmoor',   'score' => 25],
        ['pos' => 87, 'name' => 'Johan Nortje',              'cartridge' => '6.5 CM',          'score' => 25],
        ['pos' => 88, 'name' => 'DR PG',                     'cartridge' => '7 PRC',           'score' => 17],
        ['pos' => 89, 'name' => 'Dean Nortje',               'cartridge' => '7mm RSAUM',       'score' => 13],
        ['pos' => 90, 'name' => 'Francois van der Walt',     'cartridge' => '7mm Dakota',      'score' => 13],
        ['pos' => 91, 'name' => 'Abdul Aziz Amod',           'cartridge' => '6.5 PRC',         'score' => 9],
        ['pos' => 92, 'name' => 'Mangolus Mlinieti',         'cartridge' => '6.5 CM',          'score' => 8],
        ['pos' => 93, 'name' => 'Rhyno van der Westhuizen',  'cartridge' => '6.5 CM',          'score' => 4],
        // Pos 94–95 source scores were illegible in the screenshot; set to 0 (no-hit)
        // until confirmed. Minimum possible non-zero score is 4 (400m × G1), so anything
        // lower than that cannot be reconstructed.
        ['pos' => 94, 'name' => 'Abdul Aziz Amod 2',         'cartridge' => '6.5 CM',          'score' => 0],
        ['pos' => 95, 'name' => 'Abdul Aziz Amod 3',         'cartridge' => '6.5 CM',          'score' => 0],
        ['pos' => 96, 'name' => 'Alan Searle',               'cartridge' => '6.5 Creedmoor',   'score' => 0],
        ['pos' => 97, 'name' => 'Gideon du Plessis',         'cartridge' => '6.308',           'score' => 0],
    ],
];
