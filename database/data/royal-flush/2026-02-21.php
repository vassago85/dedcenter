<?php

/**
 * Royal Flush Gong Shoot — 21 February 2026
 *
 * Source: results spreadsheet screenshot supplied by the MD.
 * Format: per-shooter row with name, cartridge, integer Score (sum of
 * distance × gong multipliers for hit gongs) and Hit Rate %.
 *
 * Per-cell hit/miss patterns were not OCR'd from the screenshot — instead
 * the importer (App\Console\Commands\ImportRoyalFlushResults) reconstructs
 * a pattern that exactly matches each shooter's documented Score and Hit
 * Rate, preferring misses on the harder gongs at the longer distances
 * (consistent with how skilled shooters typically miss). Totals,
 * leaderboard order, and hit-rate analytics are therefore exact; only the
 * fine-grained per-gong pattern is reconstructed.
 *
 * Spreadsheet gong multipliers (G1…G5): 1.00, 1.30, 1.50, 1.80, 2.00
 * Banks: 400 m, 500 m, 600 m, 700 m (distance multiplier = distance / 100)
 *
 * Shape:
 *   meta:     match identity used by the importer
 *   shooters: ordered by finishing position (Pos 1 first)
 */

return [
    'meta' => [
        'name' => 'Royal Flush Gong Shoot — 21 Feb 2026',
        'date' => '2026-02-21',
        'organization_slug' => 'royal-flush',
        'location' => null,
    ],

    'shooters' => [
        ['pos' => 1,  'name' => 'Andries de Beer',         'cartridge' => '7 RSAUM',         'score' => 157, 'hit_pct' => 95.0],
        ['pos' => 2,  'name' => 'Theo Botha',              'cartridge' => '7 RSAUM',         'score' => 146, 'hit_pct' => 90.0],
        ['pos' => 3,  'name' => 'Werner Bonthuys',         'cartridge' => '6.5 Creedmoor',   'score' => 141, 'hit_pct' => 90.0],
        ['pos' => 4,  'name' => 'Ruan du Plessis',         'cartridge' => '6.5 PRC',         'score' => 141, 'hit_pct' => 90.0],
        ['pos' => 5,  'name' => 'JD Els',                  'cartridge' => '300 WSM',         'score' => 136, 'hit_pct' => 85.0],
        ['pos' => 6,  'name' => 'Abdul Aziz Amod',         'cartridge' => '6.5 Creedmoor',   'score' => 136, 'hit_pct' => 80.0],
        ['pos' => 7,  'name' => 'Simon Steyn',             'cartridge' => '7 RSAUM',         'score' => 135, 'hit_pct' => 85.0],
        ['pos' => 8,  'name' => 'Steven Dyke',             'cartridge' => '243 Win',         'score' => 133, 'hit_pct' => 85.0],
        ['pos' => 9,  'name' => 'Deon de Villiers',        'cartridge' => '260 AI',          'score' => 132, 'hit_pct' => 80.0],
        ['pos' => 10, 'name' => 'Jeane van der Merwe',     'cartridge' => '6.5 Creedmoor',   'score' => 132, 'hit_pct' => 80.0],
        ['pos' => 11, 'name' => 'Daniel Bonthuys',         'cartridge' => '6.5 Creedmoor',   'score' => 130, 'hit_pct' => 85.0],
        ['pos' => 12, 'name' => 'Morne van der Merwe',     'cartridge' => '7mm PRCW',        'score' => 126, 'hit_pct' => 80.0],
        ['pos' => 13, 'name' => 'DW de Klerk',             'cartridge' => '7 RSAUM',         'score' => 125, 'hit_pct' => 80.0],
        ['pos' => 14, 'name' => 'Johan Steenkamp',         'cartridge' => '7 RSAUM',         'score' => 125, 'hit_pct' => 80.0],
        ['pos' => 15, 'name' => 'Gerhardu Odendaal',       'cartridge' => '300 WSM',         'score' => 117, 'hit_pct' => 80.0],
        ['pos' => 16, 'name' => 'Pieter Meyer',            'cartridge' => '.243 Win',        'score' => 115, 'hit_pct' => 75.0],
        ['pos' => 17, 'name' => 'André du Toit',           'cartridge' => '6.5 Creedmoor',   'score' => 115, 'hit_pct' => 75.0],
        ['pos' => 18, 'name' => 'Plank van der Merwe',     'cartridge' => '7 RSAUM',         'score' => 114, 'hit_pct' => 75.0],
        ['pos' => 19, 'name' => 'Stephan van der Merwe',   'cartridge' => '6.5 Creedmoor',   'score' => 111, 'hit_pct' => 75.0],
        ['pos' => 20, 'name' => 'Robert van der Merwe',    'cartridge' => '6.5 Creedmoor',   'score' => 111, 'hit_pct' => 70.0],
        ['pos' => 21, 'name' => 'Shaun Snyman',            'cartridge' => '300 WSM',         'score' => 110, 'hit_pct' => 70.0],
        ['pos' => 22, 'name' => 'Danie Koch',              'cartridge' => '7 PRCW',          'score' => 110, 'hit_pct' => 75.0],
        ['pos' => 23, 'name' => 'Petrus Wassermann',       'cartridge' => '6.5x55SM',        'score' => 107, 'hit_pct' => 70.0],
        ['pos' => 24, 'name' => 'Dries Bekker',            'cartridge' => '6.5 Creedmoor',   'score' => 105, 'hit_pct' => 70.0],
        ['pos' => 25, 'name' => 'Philip Venter',           'cartridge' => '.308 Winchester', 'score' => 105, 'hit_pct' => 65.0],
        ['pos' => 26, 'name' => 'Wilfred Robson',          'cartridge' => '6.5 Creedmoor',   'score' => 102, 'hit_pct' => 70.0],
        ['pos' => 27, 'name' => 'Zander Swart',            'cartridge' => '6.5 PRC',         'score' => 99,  'hit_pct' => 65.0],
        ['pos' => 28, 'name' => 'Danie Viljoen',           'cartridge' => '6mm',             'score' => 99,  'hit_pct' => 65.0],
        ['pos' => 29, 'name' => 'Steven Nel',              'cartridge' => '6.5 PRC',         'score' => 99,  'hit_pct' => 65.0],
        ['pos' => 30, 'name' => 'Paul Charsley',           'cartridge' => '6.5 Creedmoor',   'score' => 98,  'hit_pct' => 70.0],
        ['pos' => 31, 'name' => 'Braam Bester',            'cartridge' => '6.5 Creedmoor',   'score' => 98,  'hit_pct' => 65.0],
        ['pos' => 32, 'name' => 'Louis Swart',             'cartridge' => '6.5 Creedmoor',   'score' => 98,  'hit_pct' => 55.0],
        ['pos' => 33, 'name' => 'Ronan Gamble',            'cartridge' => '6.5 PRC',         'score' => 96,  'hit_pct' => 55.0],
        ['pos' => 34, 'name' => 'Julius Hartmann',         'cartridge' => '308 Win',         'score' => 95,  'hit_pct' => 65.0],
        ['pos' => 35, 'name' => 'Brian Beeming',           'cartridge' => '6.5 Creedmoor',   'score' => 93,  'hit_pct' => 65.0],
        ['pos' => 36, 'name' => 'Francois Davel',          'cartridge' => '308 Win',         'score' => 89,  'hit_pct' => 60.0],
        ['pos' => 37, 'name' => 'Reinier Kuschke',         'cartridge' => '308 Win',         'score' => 89,  'hit_pct' => 60.0],
        ['pos' => 38, 'name' => 'Jaco Venter',             'cartridge' => '6.5 Creedmoor',   'score' => 88,  'hit_pct' => 60.0],
        ['pos' => 39, 'name' => 'Tiaan Gomes',             'cartridge' => '6.5 PRC',         'score' => 81,  'hit_pct' => 50.0],
        ['pos' => 40, 'name' => 'Richard Meissner',        'cartridge' => '300 PRC',         'score' => 78,  'hit_pct' => 45.0],
        ['pos' => 41, 'name' => 'Pieter Grobler',          'cartridge' => '6mm Dasher',      'score' => 73,  'hit_pct' => 45.0],
        ['pos' => 42, 'name' => 'Brian Koen',              'cartridge' => '7 PRC',           'score' => 73,  'hit_pct' => 45.0],
        ['pos' => 43, 'name' => 'Mike Nel',                'cartridge' => '6.5 Creedmoor',   'score' => 63,  'hit_pct' => 40.0],
        ['pos' => 44, 'name' => 'Harry Wassermann',        'cartridge' => '6.5 Creedmoor',   'score' => 62,  'hit_pct' => 40.0],
        ['pos' => 45, 'name' => 'De Wet Odendaal',         'cartridge' => '6.5 PRC',         'score' => 58,  'hit_pct' => 40.0],
        ['pos' => 46, 'name' => 'Brendon Bieldt',          'cartridge' => '6.5 PRC',         'score' => 55,  'hit_pct' => 45.0],
        ['pos' => 47, 'name' => 'Abri Potgieter',          'cartridge' => '6.5 Creedmoor',   'score' => 49,  'hit_pct' => 35.0],
        ['pos' => 48, 'name' => 'Alan Searle',             'cartridge' => '6.5 Creedmoor',   'score' => 42,  'hit_pct' => 25.0],
        ['pos' => 49, 'name' => 'Donovan Dauth',           'cartridge' => '6.5 Creedmoor',   'score' => 41,  'hit_pct' => 30.0],
        ['pos' => 50, 'name' => 'Etienne Gouws',           'cartridge' => '308 Win',         'score' => 0,   'hit_pct' => 0.0],
        ['pos' => 51, 'name' => 'Duan Viljoen',            'cartridge' => '6.5 Creedmoor',   'score' => 0,   'hit_pct' => 0.0],
    ],
];
