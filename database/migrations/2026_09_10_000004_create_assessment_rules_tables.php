<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_rules', function (Blueprint $table) {
            $table->id();
            $table->string('program');
            $table->string('year_level');
            $table->string('semester');
            $table->string('academic_year');
            $table->timestamps();

            $table->unique(['program', 'year_level', 'semester', 'academic_year'], 'assessment_rules_unique');
        });

        Schema::create('assessment_rule_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_rule_id')->constrained('assessment_rules')->cascadeOnDelete();
            $table->foreignId('fee_assessment_id')->constrained('fee_assessments')->cascadeOnDelete();
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['assessment_rule_id', 'fee_assessment_id'], 'rule_fee_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_rule_fees');
        Schema::dropIfExists('assessment_rules');
    }
};
