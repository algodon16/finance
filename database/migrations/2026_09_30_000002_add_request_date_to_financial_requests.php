<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('financial_requests', 'request_date')) {
                $table->date('request_date')->nullable()->after('department');
            }
        });
    }

    public function down(): void
    {
        Schema::table('financial_requests', function (Blueprint $table) {
            if (Schema::hasColumn('financial_requests', 'request_date')) {
                $table->dropColumn('request_date');
            }
        });
    }
};
