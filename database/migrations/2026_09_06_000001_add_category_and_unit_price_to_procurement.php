<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_items', function (Blueprint $table) {
            // Book/category grouping for the Learning Materials section.
            // NULL = general catalog item shown on Academic Items page.
            $table->string('category')->nullable()->after('description');
        });

        Schema::table('procurement_request_items', function (Blueprint $table) {
            // Snapshot of the unit price at request time.
            $table->decimal('unit_price', 12, 2)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_request_items', function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });

        Schema::table('procurement_items', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
