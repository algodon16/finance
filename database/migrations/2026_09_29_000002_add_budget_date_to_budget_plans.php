<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('budget_plans', 'budget_date')) {
                $table->date('budget_date')->nullable()->after('fiscal_year');
            }
        });
    }

    public function down(): void
    {
        Schema::table('budget_plans', function (Blueprint $table) {
            if (Schema::hasColumn('budget_plans', 'budget_date')) {
                $table->dropColumn('budget_date');
            }
        });
    }
};
