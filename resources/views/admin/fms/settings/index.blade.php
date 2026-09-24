@extends('layouts.admin')
@section('title', 'System Settings')
@section('content')
<div class="page-header"><h2>System Settings</h2></div>
<div class="fms-two">
    <div class="fms-panel" style="max-width:560px;">
        <h3>Admin Profile</h3>
        <form method="POST" action="{{ route('admin.settings.profile') }}">
            @csrf
            <div class="form-group"><label>Name <span class="required">*</span></label><input type="text" name="name" class="form-control" value="{{ old('name', auth()->user()->name) }}" required></div>
            <div class="form-group"><label>Email <span class="required">*</span></label><input type="email" name="email" class="form-control" value="{{ old('email', auth()->user()->email) }}" required></div>
            <div class="form-group"><label>Registered Phone Number (for OTP backup)</label><input type="text" name="phone_number" class="form-control" value="{{ old('phone_number', auth()->user()->phone_number) }}" placeholder="+63 917 123 4567"></div>
            <div class="form-actions"><button class="btn btn-primary" type="submit">Save Profile</button></div>
        </form>
    </div>
    <div class="fms-panel" style="max-width:560px;">
        <h3>Change Password</h3>
        <form method="POST" action="{{ route('admin.settings.password') }}">
            @csrf
            <div class="form-group"><label>Current Password <span class="required">*</span></label><input type="password" name="current_password" class="form-control" required></div>
            <div class="form-group"><label>New Password (min 8 characters) <span class="required">*</span></label><input type="password" name="password" class="form-control" required></div>
            <div class="form-group"><label>Confirm New Password <span class="required">*</span></label><input type="password" name="password_confirmation" class="form-control" required></div>
            <div class="form-actions"><button class="btn btn-primary" type="submit">Change Password</button></div>
        </form>
    </div>
</div>
<div class="fms-panel">
    <h3>Administrator Accounts</h3>
    <table class="fms-table"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Created</th></tr></thead><tbody>
    @foreach($admins as $a)<tr><td>{{ $a->name }}</td><td>{{ $a->email }}</td><td>{{ $a->phone_number ?? '—' }}</td><td><span class="status {{ $a->is_active ? 'st-green' : 'st-red' }}">{{ $a->is_active ? 'Active' : 'Inactive' }}</span></td><td>{{ $a->created_at?->format('Y-m-d') }}</td></tr>@endforeach
    </tbody></table>
</div>
@endsection
