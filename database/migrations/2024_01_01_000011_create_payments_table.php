<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['cash', 'bank_transfer', 'gcash', 'maya', 'other']);
            $table->string('reference_number')->nullable();
            $table->date('payment_date');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'under_review', 'approved', 'rejected', 'posted', 'cancelled'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('payment_date');
            $table->index('reference_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
