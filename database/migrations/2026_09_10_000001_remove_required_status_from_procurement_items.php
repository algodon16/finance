<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_items', function (Blueprint $table) {
            $table->dropColumn('required_status');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_items', function (Blueprint $table) {
            $table->enum('required_status', ['required', 'optional'])->nullable();
        });
    }
};
