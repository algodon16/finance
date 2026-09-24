<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SamplePaymentSeeder extends Seeder
{
    public function run(): void
    {
        $students = Student::all();
        $cashier = User::where('email', 'cashier@school.edu')->first();

        if ($students->isEmpty()) {
            return;
        }

        // Payment 1 - Jose - Pending
        $jose = $students->first();
        Payment::create([
            'student_id' => $jose->id,
            'amount' => 15000.00,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'BTR-2025-00001',
            'payment_date' => Carbon::parse('2025-09-15'),
            'description' => 'Tuition Fee payment for 1st Semester',
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ]);

        // Payment 2 - Ana - Approved
        $ana = $students->last();
        Payment::create([
            'student_id' => $ana->id,
            'amount' => 3500.00,
            'payment_method' => 'gcash',
            'reference_number' => 'GC-2025-00002',
            'payment_date' => Carbon::parse('2025-09-10'),
            'description' => 'Miscellaneous Fee payment',
            'status' => 'approved',
            'reviewed_by' => $cashier->id,
            'reviewed_at' => Carbon::parse('2025-09-11'),
            'rejection_reason' => null,
        ]);

        // Payment 3 - Ana - Approved (another payment)
        Payment::create([
            'student_id' => $ana->id,
            'amount' => 800.00,
            'payment_method' => 'cash',
            'reference_number' => 'CASH-2025-00003',
            'payment_date' => Carbon::parse('2025-09-12'),
            'description' => 'Library Fee payment',
            'status' => 'approved',
            'reviewed_by' => $cashier->id,
            'reviewed_at' => Carbon::parse('2025-09-12'),
            'rejection_reason' => null,
        ]);
    }
}
