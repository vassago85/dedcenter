<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Thin wrapper so the Dwandzani ALRHA match can also be seeded with:
 *   php artisan db:seed --class=AlrhaDwandzani20260815Seeder
 *
 * Prefer the artisan command for --dry-run / --fresh / --md overrides:
 *   php artisan match:seed-alrha-dwandzani
 */
class AlrhaDwandzani20260815Seeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('match:seed-alrha-dwandzani', [], $this->command?->getOutput());
    }
}
