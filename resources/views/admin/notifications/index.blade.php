@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
<div class="page-header">
    <div>
        <h2>Notifications</h2>
        <p class="page-subtitle">Manage automatic student notifications</p>
    </div>
</div>

<div class="dashboard-card">
    <h3>Automatic Payment Reminders</h3>
    <p class="section-subtitle">Automatically notify students when their payment deadline is approaching.</p>

    <form method="POST" action="{{ route('admin.notifications.settings.update') }}">
        @csrf
        @method('PUT')

        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Status</span>
                <p class="detail-value">
                    @if($settings['enabled'])
                        <span class="badge badge-green">ENABLED</span>
                    @else
                        <span class="badge badge-gray">DISABLED</span>
                    @endif
                </p>
            </div>
            <div class="detail-item">
                <span class="detail-label">Email Delivery</span>
                <p class="detail-value">
                    @if($settings['email'])
                        <span class="badge badge-green">ENABLED</span>
                    @else
                        <span class="badge badge-gray">DISABLED</span>
                    @endif
                </p>
            </div>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="enabled" value="1" {{ $settings['enabled'] ? 'checked' : '' }}>
                Enable automatic payment reminders
            </label>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="reminder_7_days" value="1" {{ $settings['7_days'] ? 'checked' : '' }}>
                7 days before deadline
            </label>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="reminder_3_days" value="1" {{ $settings['3_days'] ? 'checked' : '' }}>
                3 days before deadline
            </label>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="reminder_1_day" value="1" {{ $settings['1_day'] ? 'checked' : '' }}>
                1 day before deadline
            </label>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="reminder_due_today" value="1" {{ $settings['due_today'] ? 'checked' : '' }}>
                On deadline
            </label>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="reminder_overdue" value="1" {{ $settings['overdue'] ? 'checked' : '' }}>
                After deadline
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
    </form>
</div>

<div class="dashboard-card">
    <h3>Notification History</h3>

    <div class="filters-bar">
        <form method="GET" action="{{ route('admin.notifications.index') }}" class="filter-form">
            <div class="filter-group">
                <select name="status" aria-label="Filter by delivery status" class="form-control">
                    <option value="">All</option>
                    <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Sent</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                </select>
            </div>
            <div class="filter-group">
                <select name="type" aria-label="Filter by notification type" class="form-control">
                    <option value="">All Types</option>
                    <option value="payment_reminder" {{ request('type') === 'payment_reminder' ? 'selected' : '' }}>Payment Reminder</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            @if(request('status') || request('type'))
                <a href="{{ route('admin.notifications.index') }}" class="btn btn-secondary">Clear</a>
            @endif
        </form>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Notification</th>
                    <th>Payment Type</th>
                    <th>Deadline</th>
                    <th>Sent At</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>{{ $log->student->full_name ?? 'N/A' }}</td>
                        <td>
                            {{ $log->subject ?? ucfirst(str_replace('_', ' ', $log->reminder_type)) }}
                            @if($log->error_message)
                                <br><small class="text-danger">{{ $log->error_message }}</small>
                            @endif
                        </td>
                        <td>{{ $log->charge->financialCategory->name ?? $log->charge->description ?? 'N/A' }}</td>
                        <td>{{ $log->deadline ? date('M d, Y', strtotime($log->deadline)) : 'N/A' }}</td>
                        <td>{{ $log->sent_at ? date('M d, Y h:i A', strtotime($log->sent_at)) : '-' }}</td>
                        <td>
                            <span class="badge badge-{{ $log->delivery_status === 'sent' ? 'green' : ($log->delivery_status === 'failed' ? 'red' : 'yellow') }}">
                                {{ ucfirst($log->delivery_status) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">No notifications found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-wrapper">
        {{ $logs->withQueryString()->links() }}
    </div>
</div>
@endsection
