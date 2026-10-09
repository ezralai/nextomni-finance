<?php

namespace Database\Seeders;

use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ExchangeRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $today = Carbon::now()->toDateString();

        $rates = [
            // MYR base
            ['base_currency' => 'MYR', 'target_currency' => 'USD', 'rate' => '0.211864'],
            ['base_currency' => 'MYR', 'target_currency' => 'SGD', 'rate' => '0.284091'],
            ['base_currency' => 'MYR', 'target_currency' => 'EUR', 'rate' => '0.195313'],
            ['base_currency' => 'MYR', 'target_currency' => 'GBP', 'rate' => '0.166113'],

            // USD base
            ['base_currency' => 'USD', 'target_currency' => 'MYR', 'rate' => '4.720000'],
            ['base_currency' => 'USD', 'target_currency' => 'SGD', 'rate' => '1.340909'],
            ['base_currency' => 'USD', 'target_currency' => 'EUR', 'rate' => '0.921875'],
            ['base_currency' => 'USD', 'target_currency' => 'GBP', 'rate' => '0.784053'],

            // SGD base
            ['base_currency' => 'SGD', 'target_currency' => 'MYR', 'rate' => '3.520000'],
            ['base_currency' => 'SGD', 'target_currency' => 'USD', 'rate' => '0.745763'],
            ['base_currency' => 'SGD', 'target_currency' => 'EUR', 'rate' => '0.687500'],
            ['base_currency' => 'SGD', 'target_currency' => 'GBP', 'rate' => '0.584725'],

            // EUR base
            ['base_currency' => 'EUR', 'target_currency' => 'MYR', 'rate' => '5.120000'],
            ['base_currency' => 'EUR', 'target_currency' => 'USD', 'rate' => '1.084746'],
            ['base_currency' => 'EUR', 'target_currency' => 'SGD', 'rate' => '1.454545'],
            ['base_currency' => 'EUR', 'target_currency' => 'GBP', 'rate' => '0.850394'],

            // GBP base
            ['base_currency' => 'GBP', 'target_currency' => 'MYR', 'rate' => '6.020000'],
            ['base_currency' => 'GBP', 'target_currency' => 'USD', 'rate' => '1.275424'],
            ['base_currency' => 'GBP', 'target_currency' => 'SGD', 'rate' => '1.710227'],
            ['base_currency' => 'GBP', 'target_currency' => 'EUR', 'rate' => '1.175926'],
        ];

        foreach ($rates as $item) {
            ExchangeRate::updateOrCreate(
                [
                    'base_currency' => $item['base_currency'],
                    'target_currency' => $item['target_currency'],
                    'effective_date' => $today,
                ],
                [
                    'rate' => $item['rate'],
                    'source' => 'system_reference',
                ]
            );
        }
    }
}
