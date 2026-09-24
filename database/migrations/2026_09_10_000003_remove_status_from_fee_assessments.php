<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_assessments', function (Blueprint $table) {
            $table->decimal('default_amount', 10, 2)->default(0);
        });

        DB::table('fee_assessments')->update([
            'default_amount' => DB::raw('amount'),
        ]);

        Schema::table('fee_assessments', function (Blueprint $table) {
            $table->dropColumn(['amount', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('fee_assessments', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
        });

        DB::table('fee_assessments')->update([
            'amount' => DB::raw('default_amount'),
        ]);

        Schema::table('fee_assessments', function (Blueprint $table) {
            $table->dropColumn('default_amount');
        });
    }
};
