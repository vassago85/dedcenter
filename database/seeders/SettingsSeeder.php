<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'bank_name' => 'FNB',
            'bank_account_name' => 'DeadCenter Shooting',
            'bank_account_number' => '62000000000',
            'bank_branch_code' => '250655',
            'bank_reference_prefix' => 'DC',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
