<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Semester;
use Illuminate\Database\Seeder;

class AcademicYearSeeder extends Seeder
{
    public function run(): void
    {
        $academicYear = AcademicYear::create([
            'name' => '2025-2026',
            'is_active' => true,
        ]);

        Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => '1st Semester',
            'is_active' => true,
        ]);

        Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => '2nd Semester',
            'is_active' => false,
        ]);
    }
}
