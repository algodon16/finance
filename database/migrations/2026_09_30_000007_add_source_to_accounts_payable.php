<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts_payable', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts_payable', 'financial_request_id')) {
                $table->foreignId('financial_request_id')
                    ->nullable()
                    ->after('expense_id')
                    ->constrained('financial_requests')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounts_payable', function (Blueprint $table) {
            if (Schema::hasColumn('accounts_payable', 'financial_request_id')) {
                $table->dropConstrainedForeignId('financial_request_id');
            }
        });
    }
};
