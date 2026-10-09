<?php

namespace Database\Seeders;

use App\Models\ApprovalLimit;
use Illuminate\Database\Seeder;

class ApprovalLimitSeeder extends Seeder
{
    public function run(): void
    {
        $limits = [
            ['stock_adjustment', 'Stock adjustment / write-off', '1000.00', 'quantity'],
            ['expense', 'Expense', '5000.00', 'amount'],
            ['discount_percent', 'Discount', '5.00', 'percent'],
            ['delivery_order_overdue', 'Delivery order with dues', '10000.00', 'amount'],
            ['weight_tolerance', 'Weight correction tolerance', '2.00', 'percent'],
        ];

        foreach ($limits as [$code, $name, $limit, $unit]) {
            ApprovalLimit::query()->updateOrCreate(
                ['scope_key' => 'platform', 'code' => $code],
                [
                    'name' => $name,
                    'manager_limit' => $limit,
                    'unit' => $unit,
                    'tenant_id' => null,
                ],
            );
        }
    }
}
