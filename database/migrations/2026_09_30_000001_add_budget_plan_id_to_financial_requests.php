<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('financial_requests', 'budget_plan_id')) {
                $table->foreignId('budget_plan_id')->nullable()->after('reference_id')->constrained('budget_plans')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('financial_requests', function (Blueprint $table) {
            if (Schema::hasColumn('financial_requests', 'budget_plan_id')) {
                $table->dropConstrainedForeignId('budget_plan_id');
            }
        });
    }
};
