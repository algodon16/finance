@extends('layouts.app')
@section('title', 'System Settings')
@section('content')
<div class="page-header"><div><h2>System Settings</h2><p class="page-subtitle">Your profile only. System configuration is restricted to Admin.</p></div></div>
<div class="two-col-grid">
<div class="dashboard-card"><h3>Profile</h3>
<form method="POST" action="{{ route('accountant.settings.profile') }}">@csrf @method('PUT')
<div class="form-group"><label>Name</label><input type="text" name="name" class="form-control" value="{{ old('name',auth()->user()->name) }}" required></div>
<div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="{{ old('email',auth()->user()->email) }}" required></div>
<button class="btn btn-primary">Save Profile</button></form></div>
<div class="dashboard-card"><h3>Change Password</h3>
<form method="POST" action="{{ route('accountant.settings.password') }}">@csrf @method('PUT')
<div class="form-group"><label>Current Password</label><input type="password" name="current_password" class="form-control" required></div>
<div class="form-group"><label>New Password (min 8)</label><input type="password" name="password" class="form-control" required></div>
<div class="form-group"><label>Confirm Password</label><input type="password" name="password_confirmation" class="form-control" required></div>
<button class="btn btn-primary">Update Password</button></form></div>
</div>
@endsection
