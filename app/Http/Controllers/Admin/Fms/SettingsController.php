<?php

namespace App\Http\Controllers\Admin\Fms;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    public function index()
    {
        $admins = User::where('role', 'admin')->orderBy('name')->get();
        return view('admin.fms.settings.index', compact('admins'));
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.auth()->id(),
            'phone_number' => 'nullable|string|max:20|regex:/^[+\d][\d\s\-()]{5,19}$/',
        ]);
        $old = auth()->user()->only(['name', 'email', 'phone_number']);
        auth()->user()->update($data);
        AuditService::log('settings', 'settings', (string) auth()->id(), $old, $data, "Admin ".auth()->user()->name." updated profile settings.");
        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|max:255|confirmed',
        ]);
        if (!Hash::check($data['current_password'], auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }
        auth()->user()->update(['password' => Hash::make($data['password'])]);
        AuditService::log('settings', 'settings', (string) auth()->id(), null, null, "Admin ".auth()->user()->name." changed account password.");
        return back()->with('success', 'Password changed.');
    }
}
