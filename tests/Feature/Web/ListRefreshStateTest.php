<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('results shows the empty state and a skeleton target while the list refreshes', function () {
    $shooter = User::factory()->create();

    $this->actingAs($shooter)
        ->get(route('results'))
        ->assertOk()
        ->assertSee('No results yet')
        ->assertSee('wire:target="search,yearFilter,clearFilters"', false)
        ->assertSee("This list didn't refresh");
});

test('organizations shows the empty state and a skeleton target while the list refreshes', function () {
    $shooter = User::factory()->create();

    $this->actingAs($shooter)
        ->get(route('organizations'))
        ->assertOk()
        ->assertSee('No organizations yet.')
        ->assertSee('wire:target="search,type"', false);
});
