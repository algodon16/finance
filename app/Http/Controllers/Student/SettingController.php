<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SettingController extends Controller
{
    public function index()
    {
        $student = auth()->user()->student;

        if (! $student) {
            abort(403, 'No student record linked to this account.');
        }

        $student->load('user');

        return view('student.settings.index', compact('student'));
    }

    public function updateEmail(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
        ]);

        $oldEmail = $user->email;
        $user->update(['email' => $validated['email']]);

        AuditService::log('update', 'user_email', $user->id, ['email' => $oldEmail], ['email' => $user->email]);

        return back()->with('success', 'Email address updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'current_password' => 'required|current_password',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update(['password' => Hash::make($validated['password'])]);

        AuditService::log('update', 'user_password', $user->id);

        return back()->with('success', 'Password updated successfully.');
    }
}
