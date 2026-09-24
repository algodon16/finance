<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additive only: existing users/admin accounts are preserved.
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_number', 20)->nullable()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('phone_number');
        });

        Schema::create('login_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 10); // email | phone
            $table->string('code_hash', 255); // hashed OTP only, never plain text
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'channel', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_otps');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_number', 'phone_verified_at']);
        });
    }
};
