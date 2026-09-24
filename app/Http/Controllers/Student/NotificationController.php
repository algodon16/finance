<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->latest()
            ->paginate(20);

        return view('student.notifications.index', compact('notifications'));
    }

    public function markAsRead($id)
    {
        $notification = Notification::where('user_id', auth()->id())->find($id);

        if (! $notification) {
            return back()->with('error', 'Notification not found.');
        }

        NotificationService::markAsRead($notification->id);

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllRead()
    {
        NotificationService::markAllAsRead(auth()->id());

        return back()->with('success', 'All notifications marked as read.');
    }
}
