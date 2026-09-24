<?php

namespace Database\Seeders;

use App\Models\FinancialCategory;
use Illuminate\Database\Seeder;

class FinancialCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Tuition Fee', 'description' => 'Standard tuition fee for the semester'],
            ['name' => 'Miscellaneous Fee', 'description' => 'General miscellaneous and administrative fees'],
            ['name' => 'Laboratory Fee', 'description' => 'Fee for laboratory usage and materials'],
            ['name' => 'Library Fee', 'description' => 'Fee for library access and resources'],
            ['name' => 'Athletic Fee', 'description' => 'Fee for athletic facilities and activities'],
            ['name' => 'Medical Fee', 'description' => 'Fee for medical and health services'],
        ];

        foreach ($categories as $category) {
            FinancialCategory::create($category);
        }
    }
}
