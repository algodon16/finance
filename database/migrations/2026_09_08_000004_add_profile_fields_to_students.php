<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Displayed read-only on the Student Settings page.
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('contact_number', 30)->nullable()->after('section');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['middle_name', 'contact_number']);
        });
    }
};
