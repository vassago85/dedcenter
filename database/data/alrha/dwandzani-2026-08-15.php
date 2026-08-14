<?php

/**
 * ALRHA Dwandzani — 15 August 2026 (2nd draft squading).
 *
 * Source: "Squading Dwandzani 15 August 2026 2nd draft.pdf"
 * Dual-class on shared relays. Varmint is individual (3 per gong).
 * Hunters is 2-person teams (2 teams per gong). Relay 2 Hunters only
 * filled gong 1; Relay 4 Hunters gong 3 is empty.
 *
 * @return array{
 *     varmint: list<array{relay:int, gong:int, pos:int, name:string, categories?:list<string>}>,
 *     hunters: list<array{relay:int, gong:int, team_no:int, team:string, name:string, categories?:list<string>}>
 * }
 */
return [
    'varmint' => [
        // Relay 1 — 09:00–10:10
        ['relay' => 1, 'gong' => 1, 'pos' => 1, 'name' => 'Monica Makkink', 'categories' => ['open', 'ladies']],
        ['relay' => 1, 'gong' => 1, 'pos' => 2, 'name' => 'Dewald Makkink'],
        ['relay' => 1, 'gong' => 1, 'pos' => 3, 'name' => 'Danie Kruger'],
        ['relay' => 1, 'gong' => 2, 'pos' => 4, 'name' => 'DuToit Lambrechts'],
        ['relay' => 1, 'gong' => 2, 'pos' => 5, 'name' => 'Ethan Oosthuizen'],
        ['relay' => 1, 'gong' => 2, 'pos' => 6, 'name' => 'Louise Kamp', 'categories' => ['open', 'ladies']],
        ['relay' => 1, 'gong' => 3, 'pos' => 7, 'name' => 'Zinn van Burick'],
        ['relay' => 1, 'gong' => 3, 'pos' => 8, 'name' => 'Oliver Hart'],
        ['relay' => 1, 'gong' => 3, 'pos' => 9, 'name' => 'Pieter Daniel Hart'],
        ['relay' => 1, 'gong' => 4, 'pos' => 10, 'name' => 'Simon Steyn'],
        ['relay' => 1, 'gong' => 4, 'pos' => 11, 'name' => 'Engela Venter', 'categories' => ['open', 'ladies']],
        ['relay' => 1, 'gong' => 4, 'pos' => 12, 'name' => 'Karli van der Merwe', 'categories' => ['open', 'ladies']],
        ['relay' => 1, 'gong' => 5, 'pos' => 13, 'name' => 'Werner Bonthuys'],
        ['relay' => 1, 'gong' => 5, 'pos' => 14, 'name' => 'Dean Nortjé'],
        ['relay' => 1, 'gong' => 5, 'pos' => 15, 'name' => 'Bernard Classen'],

        // Relay 2 — 09:40–10:50
        ['relay' => 2, 'gong' => 1, 'pos' => 16, 'name' => 'Erin Vernon', 'categories' => ['open', 'ladies']],
        ['relay' => 2, 'gong' => 1, 'pos' => 17, 'name' => 'Maurits Pretorius'],
        ['relay' => 2, 'gong' => 1, 'pos' => 18, 'name' => 'Etienne Carstens'],
        ['relay' => 2, 'gong' => 2, 'pos' => 19, 'name' => 'Eugene Terblanche'],
        ['relay' => 2, 'gong' => 2, 'pos' => 20, 'name' => 'Andreas Nieuwoudt'],
        ['relay' => 2, 'gong' => 2, 'pos' => 21, 'name' => 'Johan Nortjé'],
        ['relay' => 2, 'gong' => 3, 'pos' => 22, 'name' => 'Ruan Schoeman'],
        ['relay' => 2, 'gong' => 3, 'pos' => 23, 'name' => 'David Malan'],
        ['relay' => 2, 'gong' => 3, 'pos' => 24, 'name' => 'Jan Vosloo'],
        ['relay' => 2, 'gong' => 4, 'pos' => 25, 'name' => 'Jeandre Vosloo'],
        ['relay' => 2, 'gong' => 4, 'pos' => 26, 'name' => 'Dewald Hurn'],
        ['relay' => 2, 'gong' => 4, 'pos' => 27, 'name' => 'Jonty Loubser'],
        ['relay' => 2, 'gong' => 5, 'pos' => 28, 'name' => 'Marco Terblanche'],
        ['relay' => 2, 'gong' => 5, 'pos' => 29, 'name' => "Jakes O'Neill"],
        ['relay' => 2, 'gong' => 5, 'pos' => 30, 'name' => 'Clayton Roberts'],

        // Relay 3 — 10:20–11:30
        ['relay' => 3, 'gong' => 1, 'pos' => 31, 'name' => 'Daniel Bonthuys'],
        ['relay' => 3, 'gong' => 1, 'pos' => 32, 'name' => 'Alex Pienaar'],
        ['relay' => 3, 'gong' => 1, 'pos' => 33, 'name' => 'Jaco Venter'],
        ['relay' => 3, 'gong' => 2, 'pos' => 34, 'name' => 'Danie Jacobs'],
        ['relay' => 3, 'gong' => 2, 'pos' => 35, 'name' => 'Gerhardu Odendaal'],
        ['relay' => 3, 'gong' => 2, 'pos' => 36, 'name' => 'Siebert Noeth'],
        ['relay' => 3, 'gong' => 3, 'pos' => 37, 'name' => 'Rudi Viljoen'],
        ['relay' => 3, 'gong' => 3, 'pos' => 38, 'name' => 'Yolanda van Heerden', 'categories' => ['open', 'ladies']],
        ['relay' => 3, 'gong' => 3, 'pos' => 39, 'name' => 'Ian van Heerden'],
        ['relay' => 3, 'gong' => 4, 'pos' => 40, 'name' => 'Dawid Heymans'],
        ['relay' => 3, 'gong' => 4, 'pos' => 41, 'name' => 'Christa Heymans', 'categories' => ['open', 'ladies']],
        ['relay' => 3, 'gong' => 4, 'pos' => 42, 'name' => 'Hannes Dippenaar'],
        ['relay' => 3, 'gong' => 5, 'pos' => 43, 'name' => 'AJ Snyman'],
        ['relay' => 3, 'gong' => 5, 'pos' => 44, 'name' => 'Fred vd Westhuizen'],
        ['relay' => 3, 'gong' => 5, 'pos' => 45, 'name' => 'Shaun Snyman'],

        // Relay 4 — 11:00–12:10
        ['relay' => 4, 'gong' => 1, 'pos' => 46, 'name' => 'Xandri Pretorius', 'categories' => ['open', 'ladies']],
        ['relay' => 4, 'gong' => 1, 'pos' => 47, 'name' => 'Isabella Ebersohn', 'categories' => ['open', 'ladies']],
        ['relay' => 4, 'gong' => 1, 'pos' => 48, 'name' => 'Jacob Steyn'],
        ['relay' => 4, 'gong' => 2, 'pos' => 49, 'name' => 'Derick Human'],
        ['relay' => 4, 'gong' => 2, 'pos' => 50, 'name' => 'Andries J Fourie'],
        ['relay' => 4, 'gong' => 2, 'pos' => 51, 'name' => 'Jeannine Kruger', 'categories' => ['open', 'ladies']],
        ['relay' => 4, 'gong' => 3, 'pos' => 52, 'name' => 'Theunis Duvenage'],
        ['relay' => 4, 'gong' => 3, 'pos' => 53, 'name' => 'Michael Nortjé'],
        ['relay' => 4, 'gong' => 3, 'pos' => 54, 'name' => 'Giel van Niekerk'],
    ],

    'hunters' => [
        // Relay 1 — 09:00–10:10
        ['relay' => 1, 'gong' => 1, 'team_no' => 1, 'team' => 'Victory Vixens', 'name' => 'Lizett Nel', 'categories' => ['open', 'ladies']],
        ['relay' => 1, 'gong' => 1, 'team_no' => 1, 'team' => 'Victory Vixens', 'name' => 'Chantal Buys', 'categories' => ['open', 'ladies']],
        ['relay' => 1, 'gong' => 1, 'team_no' => 2, 'team' => 'TOPGUN', 'name' => 'Morne vd Merwe'],
        ['relay' => 1, 'gong' => 1, 'team_no' => 2, 'team' => 'TOPGUN', 'name' => 'Jani Goosen', 'categories' => ['open', 'ladies']],
        ['relay' => 1, 'gong' => 2, 'team_no' => 3, 'team' => 'Boereballistics', 'name' => 'Jan Visagie Nel'],
        ['relay' => 1, 'gong' => 2, 'team_no' => 3, 'team' => 'Boereballistics', 'name' => 'Erwee Buys'],
        ['relay' => 1, 'gong' => 2, 'team_no' => 4, 'team' => 'Lock and Louwded', 'name' => 'Wouter Louw'],
        ['relay' => 1, 'gong' => 2, 'team_no' => 4, 'team' => 'Lock and Louwded', 'name' => 'Christo Louw'],
        ['relay' => 1, 'gong' => 3, 'team_no' => 5, 'team' => 'Acuti Surculus', 'name' => 'Tian Ebersohn'],
        ['relay' => 1, 'gong' => 3, 'team_no' => 5, 'team' => 'Acuti Surculus', 'name' => 'Edward Holmes'],
        ['relay' => 1, 'gong' => 3, 'team_no' => 6, 'team' => 'Smith 2.0', 'name' => 'Andre Smith'],
        ['relay' => 1, 'gong' => 3, 'team_no' => 6, 'team' => 'Smith 2.0', 'name' => 'Andre Smith'],

        // Relay 2 — 09:40–10:50 (gongs 2–3 empty on the draft)
        ['relay' => 2, 'gong' => 1, 'team_no' => 7, 'team' => 'Kettie en Klip', 'name' => 'Christo Els'],
        ['relay' => 2, 'gong' => 1, 'team_no' => 7, 'team' => 'Kettie en Klip', 'name' => 'Zander Els'],
        ['relay' => 2, 'gong' => 1, 'team_no' => 8, 'team' => 'Vapour Trail LR', 'name' => 'Mike de Beer'],
        ['relay' => 2, 'gong' => 1, 'team_no' => 8, 'team' => 'Vapour Trail LR', 'name' => 'Philip van Rooyen'],

        // Relay 3 — 10:20–11:30
        ['relay' => 3, 'gong' => 1, 'team_no' => 9, 'team' => 'Oosies', 'name' => 'Marco Oosthuizen'],
        ['relay' => 3, 'gong' => 1, 'team_no' => 9, 'team' => 'Oosies', 'name' => 'Sonnika Oosthuizen', 'categories' => ['open', 'ladies']],
        ['relay' => 3, 'gong' => 1, 'team_no' => 10, 'team' => 'Cruiser 69', 'name' => 'Chris Badenhorst'],
        ['relay' => 3, 'gong' => 1, 'team_no' => 10, 'team' => 'Cruiser 69', 'name' => 'Lizette Els', 'categories' => ['open', 'ladies']],
        ['relay' => 3, 'gong' => 2, 'team_no' => 11, 'team' => 'snetraM', 'name' => 'Fanie Martens'],
        ['relay' => 3, 'gong' => 2, 'team_no' => 11, 'team' => 'snetraM', 'name' => 'André Martens'],
        ['relay' => 3, 'gong' => 2, 'team_no' => 12, 'team' => 'The 657', 'name' => 'Jaco van der Merwe'],
        ['relay' => 3, 'gong' => 2, 'team_no' => 12, 'team' => 'The 657', 'name' => 'Jacques Potgieter'],
        ['relay' => 3, 'gong' => 3, 'team_no' => 13, 'team' => "The V's", 'name' => 'Howard Vernon'],
        ['relay' => 3, 'gong' => 3, 'team_no' => 13, 'team' => "The V's", 'name' => 'Sampie van den Berg'],
        ['relay' => 3, 'gong' => 3, 'team_no' => 14, 'team' => 'Team 1/2 TiTaN', 'name' => 'Christiaan Alberts'],
        ['relay' => 3, 'gong' => 3, 'team_no' => 14, 'team' => 'Team 1/2 TiTaN', 'name' => 'Hennie Viljoen'],

        // Relay 4 — 11:00–12:10 (gong 3 / teams 19–20 empty)
        ['relay' => 4, 'gong' => 1, 'team_no' => 15, 'team' => 'Beauty and the Beast', 'name' => 'Amelie Nieuwoudt', 'categories' => ['open', 'ladies']],
        ['relay' => 4, 'gong' => 1, 'team_no' => 15, 'team' => 'Beauty and the Beast', 'name' => 'Andries Nieuwoudt'],
        ['relay' => 4, 'gong' => 1, 'team_no' => 16, 'team' => 'Helber', 'name' => 'Bernard Classen'],
        ['relay' => 4, 'gong' => 1, 'team_no' => 16, 'team' => 'Helber', 'name' => 'Louise Kamp', 'categories' => ['open', 'ladies']],
        ['relay' => 4, 'gong' => 2, 'team_no' => 17, 'team' => 'I-Shooter', 'name' => 'Jaco Venter'],
        ['relay' => 4, 'gong' => 2, 'team_no' => 17, 'team' => 'I-Shooter', 'name' => 'Antonie van Rensburg'],
        ['relay' => 4, 'gong' => 2, 'team_no' => 18, 'team' => 'Steel Seal Team', 'name' => 'Danie Viljoen'],
        ['relay' => 4, 'gong' => 2, 'team_no' => 18, 'team' => 'Steel Seal Team', 'name' => 'Andries de Beer'],
    ],
];
