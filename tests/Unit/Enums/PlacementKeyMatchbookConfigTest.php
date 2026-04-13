<?php

use App\Enums\PlacementKey;

test('match director placements omit match book placement when match books are disabled', function () {
    config(['deadcenter.matchbook_enabled' => false]);

    $values = collect(PlacementKey::matchDirectorPlacements())->map->value->all();

    expect($values)->not->toContain('match_matchbook');
});

test('match director placements include match book placement when match books are enabled', function () {
    config(['deadcenter.matchbook_enabled' => true]);

    $values = collect(PlacementKey::matchDirectorPlacements())->map->value->all();

    expect($values)->toContain('match_matchbook');
});

test('platform placements omit global match book when match books are disabled', function () {
    config(['deadcenter.matchbook_enabled' => false]);

    $values = collect(PlacementKey::platformPlacements())->map->value->all();

    expect($values)->not->toContain('global_matchbook');
});
