<?php

namespace Database\Seeders;

use App\Models\ProcurementItem;
use Illuminate\Database\Seeder;

class ProcurementItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'item_name' => 'Academic Uniform',
                'description' => 'Standard academic uniform for daily school use',
                'price' => 1500.00,
                'stock_quantity' => 100,
                'is_available' => true,
            ],
            [
                'item_name' => 'Gala Uniform',
                'description' => 'Formal gala uniform for school events and ceremonies',
                'price' => 2500.00,
                'stock_quantity' => 50,
                'is_available' => true,
            ],
            [
                'item_name' => 'NSTP Uniform',
                'description' => 'National Service Training Program uniform',
                'price' => 800.00,
                'stock_quantity' => 80,
                'is_available' => true,
            ],
            [
                'item_name' => 'Learning Materials',
                'description' => 'Set of printed modules and reference materials',
                'price' => 500.00,
                'stock_quantity' => 200,
                'is_available' => true,
            ],
            [
                'item_name' => 'Lab Gown',
                'description' => 'Protective laboratory gown for science classes',
                'price' => 350.00,
                'stock_quantity' => 60,
                'is_available' => true,
            ],
        ];

        foreach ($items as $item) {
            ProcurementItem::create($item);
        }
    }
}
