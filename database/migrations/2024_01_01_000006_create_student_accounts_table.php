<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained('students')->cascadeOnDelete();
            $table->decimal('total_charges', 12, 2)->default(0);
            $table->decimal('total_paid', 12, 2)->default(0);
            $table->decimal('outstanding_balance', 12, 2)->default(0);
            $table->enum('clearance_status', ['cleared', 'not_cleared', 'pending_review'])->default('not_cleared');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_accounts');
    }
};
