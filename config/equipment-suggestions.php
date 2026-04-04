<?php

/**
 * Equipment autocomplete suggestions sourced from NRAPA / SARP firearm reference data.
 * Calibres from NRAPA calibres.csv + calibre_aliases.csv (SAPS 350A).
 * Makes/models from NRAPA firearm_makes.csv / firearm_models.csv.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Calibres — full NRAPA reference (rifle, rimfire, handgun, shotgun)
    |--------------------------------------------------------------------------
    */
    'calibers' => [
        // ── Rimfire ─────────────────────────────────
        '.17 HMR',
        '.17 WSM',
        '.22 LR',
        '.22 WMR',

        // ── PRS / Competition / ELR (most common in DeadCenter) ──
        '6mm GT',
        '6 Dasher',
        '6 BRA',
        '6 BR',
        '6 BRX',
        '6 XC',
        '6mm PPC',
        '6mm Remington',
        '22 Creedmoor',
        '25 Creedmoor',
        '6.5x47 Lapua',
        '6.5 Creedmoor',
        '6.5 PRC',
        '6.5-284 Norma',
        '6.5-06 A-Square',
        '6.5 Remington Magnum',
        '7 SAUM',
        '7mm PRC',
        '7mm-08 Remington',
        '7mm Remington Magnum',
        '7mm Weatherby Magnum',
        '7mm WSM',
        '300 PRC',
        '300 Norma Magnum',
        '338 Lapua Magnum',
        '338 Norma Magnum',
        '375/408 CheyTac',
        '416 Barrett',

        // ── Rifle — common hunting / service ────────
        '.17 Remington',
        '.204 Ruger',
        '.22 Hornet',
        '.218 Bee',
        '.220 Swift',
        '.221 Fireball',
        '.222 Remington',
        '.22 PPC',
        '.22-250 Remington',
        '.223 Remington',
        '.223 WSSM',
        '.224 Valkyrie',
        '5.56 NATO',
        '.240 Weatherby Magnum',
        '.243 Winchester',
        '.243 WSSM',
        '.25-06 Remington',
        '.264 Winchester Magnum',
        '.270 Winchester',
        '.270 WSM',
        '.270 Weatherby Magnum',
        '.280 Remington',
        '6.5x55 Swedish',
        '7x57 Mauser',
        '7.62x39',
        '7.62x54R',
        '7.62 NATO',
        '.30-30 Winchester',
        '.30-40 Krag',
        '.30-06 Springfield',
        '.300 AAC Blackout',
        '.300 H&H Magnum',
        '.300 Remington Ultra Mag',
        '.300 RUM',
        '.300 Winchester Magnum',
        '.300 Weatherby Magnum',
        '.300 WSM',
        '.303 British',
        '.308 Norma Magnum',
        '.308 Winchester',
        '.32 Winchester Special',
        '.325 WSM',
        '.338 Federal',
        '.338 Winchester Magnum',
        '.338-06 A-Square',
        '.340 Weatherby Magnum',
        '.35 Remington',
        '.35 Whelen',
        '.358 Winchester',
        '8x57 Mauser',

        // ── Rifle — big bore / dangerous game ───────
        '.375 H&H Magnum',
        '.375 Ruger',
        '.378 Weatherby Magnum',
        '.404 Jeffery',
        '.416 Remington Magnum',
        '.416 Rigby',
        '.416 Ruger',
        '.425 Westley Richards',
        '.444 Marlin',
        '.45-70 Government',
        '.45-90 Sharps',
        '.450 Bushmaster',
        '.450 Marlin',
        '.450 Nitro Express',
        '.450/400 Nitro Express',
        '.458 Lott',
        '.458 Winchester Magnum',
        '.460 Weatherby Magnum',
        '.470 Nitro Express',
        '.500 Nitro Express',
        '.50 Beowulf',
        '.50 BMG',
        '.577 Nitro Express',
        '.577 Tyrannosaur',
        '.585 Nyati',
        '.600 Nitro Express',
        '.600 Overkill',
        '.700 Nitro Express',
        '.700 NE',
        '.950 JDJ',

        // ── Handgun ─────────────────────────────────
        '9mm Luger',
        '9mm Parabellum',
        '.40 S&W',
        '.45 ACP',
        '.357 Magnum',
        '.44 Magnum',
        '.454 Casull',
        '.460 S&W Magnum',
        '.475 Linebaugh',
        '.480 Ruger',
        '.500 Linebaugh',
        '.500 S&W Magnum',
        '.50 Action Express',

        // ── Shotgun ─────────────────────────────────
        '12 Gauge',
        '20 Gauge',
        '.410 Bore',
    ],

    /*
    |--------------------------------------------------------------------------
    | Common calibre aliases (shorter names users might type)
    |--------------------------------------------------------------------------
    */
    'caliber_aliases' => [
        '6GT', '6.5 CM', '6.5x47', '.223', '.223 Rem', '.243', '.243 Win',
        '.270', '.270 Win', '.308', '.308 Win', '.30-06', '.300 Win Mag',
        '.300 WM', '.300 BLK', '.300 Blackout', '.338 LM', '.338 Lapua Mag',
        '.338 Win Mag', '.375 H&H', '5.56', '5.56x45', '7.62x51', '9mm',
        '.22LR', '.22 Mag', '.357 Mag', '.44 Mag', '.50 BMG',
        '300 NM', '300 Norma Mag', '338 NM', '338 Norma Mag',
        '375 CheyTac', '408 CheyTac', '6.5 PRC', '300 PRC', '7 SAUM',
        '6.5 Swede', '6.5x55', '7x57', '7mm Mauser', '8x57', '8mm Mauser',
        '.303', '.458 Win Mag', '.458 Lott', '.460 Wby Mag',
        '.300 Wby Mag', '.300 WSM', '12ga', '20ga', '.410',
    ],

    /*
    |--------------------------------------------------------------------------
    | Action / Rifle makes — from NRAPA firearm_makes.csv (rifle-relevant)
    |--------------------------------------------------------------------------
    */
    'action_brands' => [
        'Accuracy International',
        'BAT Machine',
        'Barrett',
        'Bergara',
        'Bighorn Arms',
        'Blaser',
        'Browning',
        'CZ',
        'Christensen Arms',
        'Cooper',
        'Curtis',
        'Defiance Machine',
        'Desert Tech',
        'FN Herstal',
        'Fierce Firearms',
        'GA Precision',
        'Hall',
        'Heckler & Koch',
        'Howa',
        'IWI',
        'Kelbly\'s',
        'Kimber',
        'Mauser',
        'Musgrave',
        'Proof Research',
        'Remington',
        'Rigby',
        'Ruger',
        'Sako',
        'Savage',
        'Seekins Precision',
        'Sig Sauer',
        'Steyr',
        'Stiller Precision',
        'Stolle',
        'Surgeon Rifles',
        'Tikka',
        'Truvelo',
        'Weatherby',
        'Westley Richards',
        'Winchester',
        'Zastava',
    ],

    /*
    |--------------------------------------------------------------------------
    | Barrel brands
    |--------------------------------------------------------------------------
    */
    'barrel_brands' => [
        'Bartlein 22"',
        'Bartlein 24"',
        'Bartlein 26"',
        'Bartlein 28"',
        'Bartlein 30"',
        'Brux 24"',
        'Brux 26"',
        'Brux 28"',
        'Douglas 24"',
        'Douglas 26"',
        'Hart 24"',
        'Hart 26"',
        'Krieger 22"',
        'Krieger 24"',
        'Krieger 26"',
        'Krieger 28"',
        'Krieger 30"',
        'Lilja 24"',
        'Lilja 26"',
        'Lothar Walther 24"',
        'Lothar Walther 26"',
        'Proof Research CF 22"',
        'Proof Research CF 24"',
        'Proof Research CF 26"',
        'Proof Research SS 24"',
        'Proof Research SS 26"',
        'Rock Creek 24"',
        'Rock Creek 26"',
        'Shilen 24"',
        'Shilen 26"',
        'Truvelo 24"',
        'Truvelo 26"',
        'Truvelo 28"',
        'Walther 24"',
        'Walther 26"',
    ],

    /*
    |--------------------------------------------------------------------------
    | Trigger brands
    |--------------------------------------------------------------------------
    */
    'trigger_brands' => [
        "Bix'n Andy Competition",
        "Bix'n Andy TacSport",
        'Huber Concepts',
        'Jewell',
        'Rifle Basix',
        'Timney Calvin Elite',
        'Timney Elite Hunter',
        'Timney Hit',
        'TriggerTech Diamond',
        'TriggerTech Field',
        'TriggerTech Primary',
        'TriggerTech Special',
    ],

    /*
    |--------------------------------------------------------------------------
    | Stock / chassis brands
    |--------------------------------------------------------------------------
    */
    'stock_chassis_brands' => [
        'Accuracy International AICS',
        'Cadex CDX',
        'Cadex Strike Nuke Evo',
        'Foundation Genesis',
        'Foundation Revelation',
        'GRS Bifrost',
        'GRS Ragnarok',
        'GRS Warg',
        'KRG Bravo',
        'KRG Whiskey-3',
        'KRG X-Ray',
        'Manners EH1',
        'Manners MCS-T',
        'Manners PRS1',
        'Manners TF4',
        'Masterpiece Arms BA',
        'Masterpiece Arms BA Comp',
        'Masterpiece Arms Matrix',
        'McMillan A5',
        'McMillan A6',
        'McMillan Game Scout',
        'MDT ACC',
        'MDT ESS',
        'MDT HNT26',
        'MDT LSS-XL',
        'MDT TAC21',
        'MDT XRS',
        'Oryx Chassis',
        'XLR Element',
        'XLR Envy',
        'XLR Evolution',
    ],

    /*
    |--------------------------------------------------------------------------
    | Muzzle brake / silencer / suppressor brands
    |--------------------------------------------------------------------------
    */
    'muzzle_brake_silencer_brands' => [
        'A-Tec CMM-4',
        'A-Tec Optima',
        'Area 419 Hellfire',
        'Area 419 Sidewinder',
        'Ase Utra SL7',
        'Ase Utra SL9',
        'Dead Air Nomad',
        'Dead Air Sandman',
        'DPT',
        'Hardy Gen 2',
        'Hardy Gen 3',
        'Nielson Sonic 50',
        'SAI',
        'SilencerCo Harvester',
        'SilencerCo Omega 300',
        'Sonic Reflex',
        'Sonic Reflex S',
        'Thunderbeast Ultra 7',
        'Thunderbeast Ultra 9',
    ],

    /*
    |--------------------------------------------------------------------------
    | Scope brands / models
    |--------------------------------------------------------------------------
    */
    'scope_brands' => [
        'Athlon Ares ETR 4.5-30x56',
        'Athlon Cronus BTR 4.5-29x56',
        'Bushnell Elite Tactical XRS3 4.5-30x50',
        'Kahles K318i 3.5-18x50',
        'Kahles K525i 5-25x56',
        'Leupold Mark 4HD 4.5-18x52',
        'Leupold Mark 5HD 3.6-18x44',
        'Leupold Mark 5HD 5-25x56',
        'Leupold Mark 5HD 7-35x56',
        'March FX 4.5-28x52',
        'March Genesis 6-60x56',
        'March High Master 10-60x56',
        'Nightforce ATACR 4-20x50',
        'Nightforce ATACR 5-25x56',
        'Nightforce ATACR 7-35x56',
        'Nightforce NX8 2.5-20x50',
        'Nightforce NX8 4-32x50',
        'Nightforce NXS 5.5-22x50',
        'Nightforce NXS 5.5-22x56',
        'Schmidt & Bender PM II 5-25x56',
        'Schmidt & Bender PM II 5-45x56',
        'Steiner M7Xi 4-28x56',
        'Steiner T6Xi 5-30x56',
        'Swarovski X5i 5-25x56',
        'Swarovski dS 5-25x52',
        'Tangent Theta TT525P 5-25x56',
        'Tract Toric UHD 4.5-30x56',
        'Vortex PST Gen II 5-25x50',
        'Vortex Razor HD Gen II 4.5-27x56',
        'Vortex Razor HD Gen III 6-36x56',
        'Vortex Razor HD LHT 3-15x42',
        'Vortex Viper PST Gen II 5-25x50',
        'ZeroTech Trace Advanced 4.5-27x50',
        'ZeroTech Trace Advanced 6-24x50',
    ],

    /*
    |--------------------------------------------------------------------------
    | Scope mount / ring brands
    |--------------------------------------------------------------------------
    */
    'scope_mount_brands' => [
        'American Precision Arms',
        'Badger Ordnance',
        'Badger Ordnance Condition One',
        'Bobro',
        'ERA-TAC',
        'Hawkins Precision',
        'Leupold',
        'MDT',
        'Nightforce',
        'Seekins',
        'Spuhr',
        'Unity Tactical',
        'Vortex',
        'Warne',
    ],

    /*
    |--------------------------------------------------------------------------
    | Bipod brands
    |--------------------------------------------------------------------------
    */
    'bipod_brands' => [
        'Atlas CAL',
        'Atlas PSR',
        'Atlas V8',
        'Harris HBRMS',
        'Harris S-BRM',
        'Harris SL',
        'Javelin',
        'Javelin Pro',
        'MDT CKYE-POD',
        'MDT CKYE-POD Gen 2',
        'Really Right Stuff',
        'Spartan',
        'Spartan Javelin',
        'Tier One Evolution',
        'Tier One FTR',
        'Tier One Tactical',
    ],

    /*
    |--------------------------------------------------------------------------
    | Bullet brands / lines — from NRAPA BulletSeeder + SA manufacturers
    |--------------------------------------------------------------------------
    */
    'bullet_brands' => [
        // Hornady
        'Hornady A-Tip Match',
        'Hornady ELD Match',
        'Hornady ELD-X',
        'Hornady InterLock',
        'Hornady SST',
        'Hornady V-Max',
        // Berger
        'Berger Hybrid Target',
        'Berger Juggernaut',
        'Berger Long Range Hybrid Target',
        'Berger VLD Hunting',
        'Berger VLD Target',
        // Sierra
        'Sierra MatchKing',
        'Sierra Tipped MatchKing',
        'Sierra GameKing',
        // Lapua
        'Lapua Scenar',
        'Lapua Scenar-L',
        'Lapua OTM',
        // Barnes
        'Barnes LRX',
        'Barnes Match Burner',
        'Barnes TAC-TX',
        'Barnes TTSX',
        'Barnes TSX',
        // Nosler
        'Nosler AccuBond',
        'Nosler AccuBond LR',
        'Nosler Custom Competition',
        'Nosler Partition',
        'Nosler RDF',
        // South African
        'GS Custom',
        'GS Custom HV',
        'GS Custom SP',
        'Peregrine',
        'Peregrine BushMaster',
        'Peregrine PlainsMaster',
        'Peregrine VRG',
        // Other
        'Cutting Edge',
        'Cutting Edge Lazer',
        'Cutting Edge MTH',
        'Lehigh Defense',
        'Lehigh Defense Controlled Chaos',
        'Norma HPBT',
        'Norma Oryx',
        'Swift A-Frame',
        'Swift Scirocco',
        'Woodleigh Weldcore',
    ],

];
