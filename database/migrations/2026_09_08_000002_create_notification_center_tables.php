<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('financial_charge_id')->nullable()->constrained('financial_charges')->cascadeOnDelete();
            $table->string('notification_type', 50);
            $table->string('reminder_type', 50);
            $table->string('recipient_email')->nullable();
            $table->string('subject')->nullable();
            $table->decimal('amount_due', 12, 2)->nullable();
            $table->date('deadline')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->enum('delivery_status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'financial_charge_id', 'reminder_type'], 'notif_log_unique');
            $table->index('delivery_status');
        });

        $now = now();
        $defaults = [
            'payment_reminders.enabled' => '1',
            'payment_reminders.7_days' => '1',
            'payment_reminders.3_days' => '1',
            'payment_reminders.1_day' => '1',
            'payment_reminders.due_today' => '1',
            'payment_reminders.overdue' => '1',
            'delivery.email' => '1',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('notification_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_settings');
    }
};
