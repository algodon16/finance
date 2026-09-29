<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts_receivable', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('financial_charge_id')->nullable()->unique()->constrained('financial_charges')->nullOnDelete();
            $table->text('description')->nullable();
            $table->decimal('billed_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('open'); // open, partial, paid, waived
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['student_id', 'status']);
            $table->index('due_date');
            $table->index('status');
        });

        // Backfill: isang AR row bawat existing financial charge.
        // Ang nabayaran na (total_paid) ikinakalat FIFO — pinakalumang due date muna.
        $paidByStudent = DB::table('student_accounts')->pluck('total_paid', 'student_id');
        $charges = DB::table('financial_charges')->orderBy('student_id')->orderBy('due_date')->orderBy('id')->get();
        $stamp = date('Ymd');
        $now = now()->toDateTimeString();
        foreach ($charges as $c) {
            $pool = (float) ($paidByStudent[$c->student_id] ?? 0);
            $amount = (float) $c->amount;
            if ($c->status === 'waived') {
                $paid = 0; $balance = 0; $status = 'waived';
            } elseif ($c->status === 'paid') {
                $paid = $amount; $balance = 0; $status = 'paid';
            } else {
                $paid = min($pool, $amount);
                $paidByStudent[$c->student_id] = $pool - $paid;
                $balance = $amount - $paid;
                $status = $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'open');
            }
            DB::table('accounts_receivable')->insert([
                'reference_number' => 'AR-'.$stamp.'-'.str_pad((string) $c->id, 6, '0', STR_PAD_LEFT),
                'student_id' => $c->student_id,
                'financial_charge_id' => $c->id,
                'description' => $c->description,
                'billed_amount' => $amount,
                'paid_amount' => $paid,
                'balance' => $balance,
                'due_date' => $c->due_date,
                'status' => $status,
                'assessed_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts_receivable');
    }
};
