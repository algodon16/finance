<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\StudentAccount;
use Illuminate\Database\Seeder;

class StudentAccountSeeder extends Seeder
{
    public function run(): void
    {
        $students = Student::all();

        foreach ($students as $student) {
            StudentAccount::create([
                'student_id' => $student->id,
                'total_charges' => 0,
                'total_paid' => 0,
                'outstanding_balance' => 0,
                'clearance_status' => 'not_cleared',
            ]);
        }
    }
}
