<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Services\AuditService;
use App\Services\PaymentReminderService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $settings = [
            'enabled' => NotificationSetting::enabled('payment_reminders.enabled'),
            '7_days' => NotificationSetting::enabled('payment_reminders.7_days'),
            '3_days' => NotificationSetting::enabled('payment_reminders.3_days'),
            '1_day' => NotificationSetting::enabled('payment_reminders.1_day'),
            'due_today' => NotificationSetting::enabled('payment_reminders.due_today'),
            'overdue' => NotificationSetting::enabled('payment_reminders.overdue'),
            'email' => NotificationSetting::enabled('delivery.email'),
        ];

        $query = NotificationLog::with(['student', 'charge.financialCategory'])->latest();

        if ($request->filled('status') && in_array($request->status, ['sent', 'failed', 'pending'])) {
            $query->where('delivery_status', $request->status);
        }

        if ($request->filled('type') && $request->type === PaymentReminderService::TYPE) {
            $query->where('notification_type', PaymentReminderService::TYPE);
        }

        $logs = $query->paginate(15);

        return view('admin.notifications.index', compact('settings', 'logs'));
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'enabled' => 'boolean',
            'reminder_7_days' => 'boolean',
            'reminder_3_days' => 'boolean',
            'reminder_1_day' => 'boolean',
            'reminder_due_today' => 'boolean',
            'reminder_overdue' => 'boolean',
        ]);

        $old = NotificationSetting::pluck('value', 'key')->all();

        NotificationSetting::set('payment_reminders.enabled', $request->boolean('enabled') ? '1' : '0');
        NotificationSetting::set('payment_reminders.7_days', $request->boolean('reminder_7_days') ? '1' : '0');
        NotificationSetting::set('payment_reminders.3_days', $request->boolean('reminder_3_days') ? '1' : '0');
        NotificationSetting::set('payment_reminders.1_day', $request->boolean('reminder_1_day') ? '1' : '0');
        NotificationSetting::set('payment_reminders.due_today', $request->boolean('reminder_due_today') ? '1' : '0');
        NotificationSetting::set('payment_reminders.overdue', $request->boolean('reminder_overdue') ? '1' : '0');

        AuditService::log('update', 'notification_setting', null, $old, NotificationSetting::pluck('value', 'key')->all());

        return redirect()->route('admin.notifications.index')
            ->with('success', 'Notification settings saved successfully.');
    }
}
