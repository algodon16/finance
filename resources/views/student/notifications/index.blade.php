@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
<div class="page-header">
    <h2>Notifications</h2>
    @if($notifications->where('is_read', false)->count() > 0)
        <form method="POST" action="{{ route('notifications.readAll') }}" class="inline-form">
            @csrf
            <button type="submit" class="btn btn-secondary">Mark all as read</button>
        </form>
    @endif
</div>

<div class="dashboard-card">
    @forelse($notifications as $notification)
        <div class="notification-item {{ $notification->is_read ? '' : 'unread' }}">
            <div class="notification-item-content">
                <p class="notification-item-title">{{ $notification->title }}</p>
                <p class="notification-item-message">{{ $notification->message }}</p>
                <span class="notification-time">{{ $notification->created_at ? $notification->created_at->diffForHumans() : '' }}</span>
            </div>
            @unless($notification->is_read)
                <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="inline-form">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-secondary">Mark as Read</button>
                </form>
            @endunless
        </div>
    @empty
        <div class="text-center" style="padding: 40px;">
            <p>No notifications</p>
        </div>
    @endforelse
</div>

@if(method_exists($notifications, 'links'))
    <div class="pagination-wrapper">
        {{ $notifications->links() }}
    </div>
@endif

@push('scripts')
<style>
    .notification-item {
        display: flex;
        align-items: center;
        padding: 16px 20px;
        border-bottom: 1px solid #e0e0e0;
        gap: 16px;
    }
    .notification-item:last-child { border-bottom: none; }
    .notification-item.unread { background: #f0f7ff; }
    .notification-item-content { flex: 1; }
    .notification-item-content p { margin: 0; }
    .notification-item-message { color: var(--text-muted); font-size: 0.875rem; }
    .notification-time { font-size: 0.8rem; color: #999; }
</style>
@endpush
@endsection
