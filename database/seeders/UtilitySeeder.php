<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\UtilityAccount;
use Illuminate\Database\Seeder;

class UtilitySeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['type' => 'electricity', 'provider' => 'Enerjisa', 'label' => 'Ev elektrik', 'subscriber_no' => '4001234567'],
            ['type' => 'natural_gas', 'provider' => 'İGDAŞ', 'label' => 'Ev doğalgaz', 'subscriber_no' => '9007654321'],
            ['type' => 'water', 'provider' => 'İSKİ', 'label' => 'Ev su', 'subscriber_no' => '5551112222'],
            ['type' => 'internet', 'provider' => 'Türk Telekom', 'label' => 'Ev internet', 'subscriber_no' => null],
            ['type' => 'phone', 'provider' => 'Turkcell', 'label' => 'İş telefonu', 'subscriber_no' => null],
        ];

        foreach ($accounts as $accountData) {
            $account = UtilityAccount::create($accountData + ['is_active' => true]);
            $hasMeter = $account->type->hasMeter();
            $meterStart = 1000;

            foreach (range(5, 0) as $offset) {
                $period = now()->subMonths($offset);
                $service = round(random_int(14000, 32000) / 100, 2);
                $tax = round($service * 0.2, 2);
                $meterEnd = $meterStart + random_int(180, 320);

                $bill = $account->bills()->create([
                    'bill_number' => 'FTR-'.random_int(1000000, 9999999),
                    'period_start' => $period->copy()->startOfMonth()->toDateString(),
                    'period_end' => $period->copy()->endOfMonth()->toDateString(),
                    'issued_at' => $period->copy()->endOfMonth()->toDateString(),
                    'due_date' => $period->copy()->endOfMonth()->addDays(12)->toDateString(),
                    'meter_start' => $hasMeter ? $meterStart : null,
                    'meter_end' => $hasMeter ? $meterEnd : null,
                    'total_amount' => round($service + $tax, 2),
                    'currency' => 'TRY',
                ]);

                $bill->lines()->createMany([
                    ['label' => 'Hizmet bedeli', 'amount' => $service, 'sort_order' => 0],
                    ['label' => 'KDV', 'amount' => $tax, 'sort_order' => 1],
                ]);

                $meterStart = $meterEnd;
            }
        }
    }
}
