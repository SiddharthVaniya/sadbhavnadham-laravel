<?php

namespace Database\Seeders;

use App\Models\AisensyAccount;
use Illuminate\Database\Seeder;

class AisensyAccountSeeder extends Seeder
{
    public function run(): void
    {
        AisensyAccount::firstOrCreate(
            ['name' => 'Sadbhavna Dham'],
            [
                'api_key' => env('AISENSY_API_KEY', 'replace-with-actual-api-key'),
                'country_code' => '91',
                'is_active' => false,
            ]
        );
    }
}
