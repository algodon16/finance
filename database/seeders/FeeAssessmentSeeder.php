<?php

namespace Database\Seeders;

use App\Models\FeeAssessment;
use Illuminate\Database\Seeder;

class FeeAssessmentSeeder extends Seeder
{
    public function run(): void
    {
        $fees = [
            ['fee_name' => 'Library Fee', 'description' => 'Access to library resources and services', 'default_amount' => 300.00],
            ['fee_name' => 'Student ID Fee', 'description' => 'Issuance of student identification card', 'default_amount' => 200.00],
            ['fee_name' => 'Guidance Fee', 'description' => 'Guidance and counseling services', 'default_amount' => 150.00],
            ['fee_name' => 'Medical/Dental Fee', 'description' => 'Medical and dental services', 'default_amount' => 250.00],
            ['fee_name' => 'Athletic Fee', 'description' => 'Sports and athletic activities', 'default_amount' => 300.00],
            ['fee_name' => 'Cultural Fee', 'description' => 'Cultural and student activities', 'default_amount' => 200.00],
            ['fee_name' => 'Computer Laboratory Fee', 'description' => 'Use of computer laboratory facilities', 'default_amount' => 400.00],
            ['fee_name' => 'Registration Fee', 'description' => 'Enrollment and registration services', 'default_amount' => 500.00],
            ['fee_name' => 'Other Fees', 'description' => 'Other applicable fees and charges', 'default_amount' => 0.00],
        ];

        foreach ($fees as $fee) {
            FeeAssessment::updateOrCreate(
                ['fee_name' => $fee['fee_name']],
                $fee
            );
        }
    }
}
