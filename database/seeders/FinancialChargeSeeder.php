<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\FinancialCategory;
use App\Models\FinancialCharge;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentAccount;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FinancialChargeSeeder extends Seeder
{
    public function run(): void
    {
        $students = Student::all();
        $academicYear = AcademicYear::where('name', '2025-2026')->first();
        $semester = Semester::where('academic_year_id', $academicYear->id)->where('name', '1st Semester')->first();

        $chargeData = [
            'Tuition Fee' => ['description' => 'Tuition Fee - 1st Semester 2025-2026', 'amount' => 15000.00],
            'Miscellaneous Fee' => ['description' => 'Miscellaneous Fee - 1st Semester 2025-2026', 'amount' => 3500.00],
            'Laboratory Fee' => ['description' => 'Laboratory Fee - 1st Semester 2025-2026', 'amount' => 2000.00],
            'Library Fee' => ['description' => 'Library Fee - 1st Semester 2025-2026', 'amount' => 800.00],
            'Athletic Fee' => ['description' => 'Athletic Fee - 1st Semester 2025-2026', 'amount' => 500.00],
            'Medical Fee' => ['description' => 'Medical Fee - 1st Semester 2025-2026', 'amount' => 600.00],
        ];

        foreach ($students as $student) {
            $totalCharges = 0;

            foreach ($chargeData as $categoryName => $data) {
                $category = FinancialCategory::where('name', $categoryName)->first();

                $charge = FinancialCharge::create([
                    'student_id' => $student->id,
                    'financial_category_id' => $category->id,
                    'description' => $data['description'],
                    'amount' => $data['amount'],
                    'due_date' => Carbon::parse('2025-10-15'),
                    'academic_year_id' => $academicYear->id,
                    'semester_id' => $semester->id,
                    'status' => 'active',
                ]);

                \App\Models\AccountReceivable::create([
                    'reference_number' => \App\Models\AccountReceivable::nextReferenceNumber(),
                    'student_id' => $student->id,
                    'financial_charge_id' => $charge->id,
                    'description' => $data['description'],
                    'billed_amount' => $data['amount'],
                    'paid_amount' => 0,
                    'balance' => $data['amount'],
                    'due_date' => $charge->due_date,
                    'status' => 'open',
                    'assessed_by' => null,
                ]);

                $totalCharges += $data['amount'];
            }

            $account = StudentAccount::where('student_id', $student->id)->first();
            if ($account) {
                $account->update([
                    'total_charges' => $totalCharges,
                    'outstanding_balance' => $totalCharges,
                ]);
            }
        }
    }
}
