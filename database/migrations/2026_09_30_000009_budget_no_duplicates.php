<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One official budget per department + fiscal year.
 * Drafts/rejected/cancelled are working copies and stay exempt;
 * the uniqueness kicks in once a plan becomes official
 * (submitted / under_review / approved / active).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS budget_plans_dept_year_official_uniq
            ON budget_plans (fiscal_year, LOWER(department))
            WHERE status IN ('submitted','under_review','approved','active')
            AND department IS NOT NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS budget_plans_dept_year_official_uniq');
    }
};
