<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** Accountant → System Settings (own profile only; no system configuration). */
class SettingController extends Controller
{
    public function index()
    {
        return view('accountant.settings.index');
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
        ]);
        $old = $user->only(['name', 'email']);
        $user->update($data);
        AuditService::log('update', 'settings', (string) $user->id, $old, $data, "Accountant updated own profile.");
        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request)
    {
        $user = auth()->user();
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password' => 'required|string|min:8|confirmed',
        ]);
        $user->update(['password' => Hash::make($data['password'])]);
        AuditService::log('update', 'settings', (string) $user->id, null, null, "Accountant changed own password.");
        return back()->with('success', 'Password updated.');
    }
}
