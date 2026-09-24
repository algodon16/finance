<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            StudentSeeder::class,
            AcademicYearSeeder::class,
            FinancialCategorySeeder::class,
            ProcurementItemSeeder::class,
            StudentAccountSeeder::class,
            FinancialChargeSeeder::class,
            SamplePaymentSeeder::class,
            FeeAssessmentSeeder::class,
        ]);
    }
}
