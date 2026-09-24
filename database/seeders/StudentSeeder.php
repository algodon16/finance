<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $jose = User::where('email', 'jose@student.edu')->first();
        $ana = User::where('email', 'ana@student.edu')->first();

        Student::create([
            'user_id' => $jose->id,
            'student_number' => '2025-00001',
            'first_name' => 'Jose',
            'last_name' => 'Cruz',
            'program' => 'Bachelor of Science in Computer Science',
            'year_level' => 2,
            'section' => 'A',
        ]);

        Student::create([
            'user_id' => $ana->id,
            'student_number' => '2025-00002',
            'first_name' => 'Ana',
            'last_name' => 'Dela Cruz',
            'program' => 'Bachelor of Science in Accountancy',
            'year_level' => 1,
            'section' => 'B',
        ]);
    }
}
