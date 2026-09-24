<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('student_number')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('program');
            $table->integer('year_level');
            $table->string('section');
            $table->timestamps();

            $table->index('first_name');
            $table->index('last_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
