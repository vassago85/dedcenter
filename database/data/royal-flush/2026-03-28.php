<?php

/**
 * Royal Flush Gong Shoot — 28 March 2026
 *
 * Source: results spreadsheet screenshot supplied by the MD.
 * See database/data/royal-flush/2026-02-21.php for the full format
 * notes and reconstruction caveats.
 *
 * Note: this match has two distinct shooters named "Brian Beeming"
 * (Pos 40 and Pos 63). The second one is preserved as
 * "Brian Beeming 2" so the placeholder import users don't collide.
 */

return [
    'meta' => [
        'name' => 'Royal Flush Gong Shoot — 28 Mar 2026',
        'date' => '2026-03-28',
        'organization_slug' => 'royal-flush',
        'location' => null,
    ],

    'shooters' => [
        ['pos' => 1,  'name' => 'Fred vd Westhuizen',          'cartridge' => '6 Dasher',          'score' => 159, 'hit_pct' => 95.0],
        ['pos' => 2,  'name' => 'Gerrit van Rooyen',           'cartridge' => '30 Sherman Magnum', 'score' => 126, 'hit_pct' => 80.0],
        ['pos' => 3,  'name' => 'Gerhardu Odendaal',           'cartridge' => '300 WSM',           'score' => 123, 'hit_pct' => 80.0],
        ['pos' => 4,  'name' => 'Robert Meintjes',             'cartridge' => '7 RSAUM',           'score' => 123, 'hit_pct' => 80.0],
        ['pos' => 5,  'name' => 'Francois van der Walt',       'cartridge' => '7mm Dakota',        'score' => 122, 'hit_pct' => 80.0],
        ['pos' => 6,  'name' => 'Kobie Nel',                   'cartridge' => '300 Norma Magnum',  'score' => 120, 'hit_pct' => 80.0],
        ['pos' => 7,  'name' => 'Simon Steyn',                 'cartridge' => '7 RSAUM',           'score' => 118, 'hit_pct' => 80.0],
        ['pos' => 8,  'name' => 'Daniel Bonthuys',             'cartridge' => '6.5 Creedmoor',     'score' => 117, 'hit_pct' => 75.0],
        // Hit % corrected from 80% → 75%: 4 misses cannot mathematically
        // produce a score ≤ 117.8, so 116 must come from 5 misses (15 hits).
        ['pos' => 9,  'name' => 'Erwin Potgieter',             'cartridge' => '7 RSAUM',           'score' => 116, 'hit_pct' => 75.0],
        ['pos' => 10, 'name' => 'Aiden Boshoff',               'cartridge' => '243 Win',           'score' => 116, 'hit_pct' => 75.0],
        ['pos' => 11, 'name' => 'Johan Nel',                   'cartridge' => '25x47',             'score' => 114, 'hit_pct' => 75.0],
        ['pos' => 12, 'name' => 'Christo Els',                 'cartridge' => '284 Shehane',       'score' => 114, 'hit_pct' => 75.0],
        ['pos' => 13, 'name' => 'Pieter Meyer',                'cartridge' => '.260 Rem',          'score' => 111, 'hit_pct' => 75.0],
        ['pos' => 14, 'name' => 'Schalk van der Merwe',        'cartridge' => '6.5 Creedmoor',     'score' => 110, 'hit_pct' => 75.0],
        ['pos' => 15, 'name' => 'Steven Dyke',                 'cartridge' => '243 Win',           'score' => 110, 'hit_pct' => 75.0],
        ['pos' => 16, 'name' => 'Andries de Beer',             'cartridge' => '7 RSAUM',           'score' => 109, 'hit_pct' => 75.0],
        ['pos' => 17, 'name' => 'Andre PJ van der Westhuizen', 'cartridge' => '6 GT',              'score' => 107, 'hit_pct' => 75.0],
        ['pos' => 18, 'name' => 'Morne van der Merwe',         'cartridge' => '7 Shehane',         'score' => 105, 'hit_pct' => 70.0],
        ['pos' => 19, 'name' => 'Danie Viljoen',               'cartridge' => '6.5 Creedmoor',     'score' => 104, 'hit_pct' => 65.0],
        ['pos' => 20, 'name' => 'Shaun Flink',                 'cartridge' => '284 Shehane',       'score' => 103, 'hit_pct' => 65.0],
        ['pos' => 21, 'name' => 'Danie Koch',                  'cartridge' => '7 PRCW',            'score' => 103, 'hit_pct' => 65.0],
        ['pos' => 22, 'name' => 'Emil Engelbrecht',            'cartridge' => '6.5 Creedmoor',     'score' => 100, 'hit_pct' => 70.0],
        ['pos' => 23, 'name' => 'Morton Mynhardt',             'cartridge' => '6.5 PRC',           'score' => 98,  'hit_pct' => 70.0],
        ['pos' => 24, 'name' => 'Louis Raubenheimer',          'cartridge' => '6.5 Creedmoor',     'score' => 94,  'hit_pct' => 65.0],
        ['pos' => 25, 'name' => 'Werner Bonthuys',             'cartridge' => '6.5 Creedmoor',     'score' => 91,  'hit_pct' => 65.0],
        ['pos' => 26, 'name' => 'Warren Britnell',             'cartridge' => '300 Norma Magnum',  'score' => 91,  'hit_pct' => 65.0],
        ['pos' => 27, 'name' => 'Pieter Grobler',              'cartridge' => '7 RSAUM',           'score' => 88,  'hit_pct' => 65.0],
        // Hit % corrected from 65% → 60% for the three rows below: a
        // 13-hit (= 7-miss) pattern caps at 79.0 max, so any score above
        // that requires at least 8 misses (60%). Confirmed by solver.
        ['pos' => 28, 'name' => 'Coenie van Tonder',           'cartridge' => '6 GT',              'score' => 87,  'hit_pct' => 60.0],
        ['pos' => 29, 'name' => 'Jason McLean',                'cartridge' => '6mm Dasher',        'score' => 85,  'hit_pct' => 60.0],
        ['pos' => 30, 'name' => 'Brandon Ulrich',              'cartridge' => '6.5 Creedmoor',     'score' => 85,  'hit_pct' => 60.0],
        ['pos' => 31, 'name' => 'Mohamed Daya',                'cartridge' => '308 Win',           'score' => 83,  'hit_pct' => 55.0],
        ['pos' => 32, 'name' => 'AJ Snyman',                   'cartridge' => '6mm Dasher',        'score' => 82,  'hit_pct' => 55.0],
        ['pos' => 33, 'name' => 'Jose Alves',                  'cartridge' => '.260 Remington',    'score' => 82,  'hit_pct' => 55.0],
        ['pos' => 34, 'name' => 'Paul Charsley',               'cartridge' => '6.5 Creedmoor',     'score' => 81,  'hit_pct' => 50.0],
        ['pos' => 35, 'name' => 'Rudi Viljoen',                'cartridge' => '7mm PRC',           'score' => 76,  'hit_pct' => 55.0],
        ['pos' => 36, 'name' => 'Mohamed Ayob',                'cartridge' => '308 Win',           'score' => 75,  'hit_pct' => 55.0],
        ['pos' => 37, 'name' => 'Dries Bekker',                'cartridge' => '6.5 Creedmoor',     'score' => 75,  'hit_pct' => 55.0],
        ['pos' => 38, 'name' => 'Ruan du Plessis',             'cartridge' => '6.5 PRC',           'score' => 75,  'hit_pct' => 55.0],
        ['pos' => 39, 'name' => 'Jannie Jacobs',               'cartridge' => '6mm',               'score' => 73,  'hit_pct' => 50.0],
        ['pos' => 40, 'name' => 'Brian Beeming',               'cartridge' => '6.5 Creedmoor',     'score' => 70,  'hit_pct' => 35.0],
        ['pos' => 41, 'name' => 'Zander Els',                  'cartridge' => '6.5 Creedmoor',     'score' => 69,  'hit_pct' => 50.0],
        ['pos' => 42, 'name' => 'Morne Steyn',                 'cartridge' => '6mm Creedmoor',     'score' => 69,  'hit_pct' => 50.0],
        ['pos' => 43, 'name' => 'Stephan van der Merwe',       'cartridge' => '6.5 Creedmoor',     'score' => 68,  'hit_pct' => 50.0],
        ['pos' => 44, 'name' => 'Aresi Viljoen',               'cartridge' => '6.5 Creedmoor',     'score' => 68,  'hit_pct' => 50.0],
        ['pos' => 45, 'name' => 'Lee Thompson',                'cartridge' => '6.5 Creedmoor',     'score' => 68,  'hit_pct' => 50.0],
        ['pos' => 46, 'name' => 'Steven Coombs',               'cartridge' => '7mm PRC',           'score' => 67,  'hit_pct' => 50.0],
        ['pos' => 47, 'name' => 'Carel',                       'cartridge' => '6mm Creedmoor',     'score' => 65,  'hit_pct' => 50.0],
        ['pos' => 48, 'name' => 'Johan Smith',                 'cartridge' => '308 Win',           'score' => 65,  'hit_pct' => 40.0],
        ['pos' => 49, 'name' => 'Danie du Preez',              'cartridge' => '6.5 Creedmoor',     'score' => 65,  'hit_pct' => 40.0],
        ['pos' => 50, 'name' => 'Sean van Wyk',                'cartridge' => '7mm PRC',           'score' => 65,  'hit_pct' => 40.0],
        ['pos' => 51, 'name' => 'Ismail Arbee',                'cartridge' => '6.5 Creedmoor',     'score' => 64,  'hit_pct' => 45.0],
        ['pos' => 52, 'name' => 'Imanuel Coutinho',            'cartridge' => '243 Win',           'score' => 62,  'hit_pct' => 45.0],
        ['pos' => 53, 'name' => 'Mohammed Ahmed',              'cartridge' => '6.5 Creedmoor',     'score' => 61,  'hit_pct' => 45.0],
        ['pos' => 54, 'name' => 'Jeane van der Merwe',         'cartridge' => '6.5 Creedmoor',     'score' => 56,  'hit_pct' => 40.0],
        ['pos' => 55, 'name' => 'Ian van Heerden',             'cartridge' => '6.5 Creedmoor',     'score' => 55,  'hit_pct' => 35.0],
        ['pos' => 56, 'name' => 'Abdul Aziz Amod',             'cartridge' => '6.5 PRC',           'score' => 50,  'hit_pct' => 40.0],
        ['pos' => 57, 'name' => 'MC van Tonder',               'cartridge' => '6 GT',              'score' => 47,  'hit_pct' => 35.0],
        ['pos' => 58, 'name' => 'Muzzammil Hassim',            'cartridge' => '7mm PRC',           'score' => 46,  'hit_pct' => 35.0],
        ['pos' => 59, 'name' => 'Quinten Kok',                 'cartridge' => '6.5 Creedmoor',     'score' => 45,  'hit_pct' => 35.0],
        ['pos' => 60, 'name' => 'Jurgen Roets',                'cartridge' => '6.5 PRC',           'score' => 43,  'hit_pct' => 35.0],
        ['pos' => 61, 'name' => 'Martin Roets',                'cartridge' => '6.5 Creedmoor',     'score' => 41,  'hit_pct' => 35.0],
        ['pos' => 62, 'name' => 'Michael Coutinho',            'cartridge' => '6.5 Creedmoor',     'score' => 38,  'hit_pct' => 30.0],
        ['pos' => 63, 'name' => 'Brian Beeming 2',             'cartridge' => '7 PRC',             'score' => 38,  'hit_pct' => 30.0],
        ['pos' => 64, 'name' => 'Diedrik Pretorius',           'cartridge' => '22-250',            'score' => 37,  'hit_pct' => 30.0],
        ['pos' => 65, 'name' => 'Harry Wassermann',            'cartridge' => '6.5 Creedmoor',     'score' => 37,  'hit_pct' => 30.0],
        ['pos' => 66, 'name' => 'Savvas Xenophontos',          'cartridge' => '30-06 Spring',      'score' => 31,  'hit_pct' => 25.0],
        ['pos' => 67, 'name' => 'Brian Koen',                  'cartridge' => '7mm PRC',           'score' => 27,  'hit_pct' => 25.0],
        ['pos' => 68, 'name' => 'Petrus Wassermann',           'cartridge' => '6.5x55 SM',         'score' => 27,  'hit_pct' => 25.0],
        ['pos' => 69, 'name' => 'Reinier Kuschke',             'cartridge' => '308 Win',           'score' => 27,  'hit_pct' => 20.0],
        ['pos' => 70, 'name' => 'Wilfred Robson',              'cartridge' => '223 REM',           'score' => 24,  'hit_pct' => 15.0],
    ],
];
