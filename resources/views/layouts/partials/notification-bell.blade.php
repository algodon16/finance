@php
    $notifUser = Auth::user();
    $notifUnreadCount = $notifUser->notifications()->unread()->count();
    $notifRecent = $notifUser->notifications()->latest()->take(6)->get();

    $notifIcon = function ($notification) {
        $text = strtolower(($notification->title ?? '') . ' ' . ($notification->message ?? ''));
        if (str_contains($text, 'approv') || str_contains($text, 'clear') || str_contains($text, 'complet')) {
            return ['glyph' => '✓', 'tone' => 'green'];
        }
        if (str_contains($text, 'reject') || str_contains($text, 'fail') || str_contains($text, 'denied')) {
            return ['glyph' => '✕', 'tone' => 'red'];
        }
        if (str_contains($text, 'charge') || str_contains($text, 'payment') || str_contains($text, 'collection')) {
            return ['glyph' => '₱', 'tone' => 'blue'];
        }
        return ['glyph' => 'i', 'tone' => 'gray'];
    };
@endphp
<div class="notif-wrap">
    <button type="button" class="notification-bell" id="notifBell" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
        <svg class="bell-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.7 21a2 2 0 0 1-3.4 0"></path>
        </svg>
        @if($notifUnreadCount > 0)
            <span class="notification-badge" id="notifBadge">{{ $notifUnreadCount }}</span>
        @endif
    </button>
    <div class="notification-dropdown" id="notifDropdown">
        <div class="notification-dropdown-header">
            <h4>Notifications</h4>
            @if($notifUnreadCount > 0)
                <button type="button" class="notif-mark-all" id="notifMarkAll" data-url="{{ route('notifications.readAll') }}">Mark all read</button>
            @endif
        </div>
        <div class="notification-dropdown-body" id="notifBody">
            @forelse($notifRecent as $notif)
                @php $icon = $notifIcon($notif); @endphp
                <button type="button" class="notification-item {{ $notif->is_read ? '' : 'unread' }}" data-id="{{ $notif->id }}" data-read-url="{{ route('notifications.read', $notif->id) }}">
                    <span class="notification-item-icon notif-{{ $icon['tone'] }}">{{ $icon['glyph'] }}</span>
                    <span class="notification-item-content">
                        <span class="notification-item-title">{{ $notif->title }}<span class="notif-dot" aria-hidden="true"></span></span>
                        <span class="notification-item-message">{{ $notif->message }}</span>
                        <span class="notification-item-time">{{ $notif->created_at ? $notif->created_at->diffForHumans() : '' }}</span>
                    </span>
                </button>
            @empty
                <p class="notif-empty">No notifications</p>
            @endforelse
        </div>
        <div class="notification-dropdown-footer">
            <a href="{{ route('notifications.index') }}">View All Notifications</a>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        var bell = document.getElementById('notifBell');
        var dropdown = document.getElementById('notifDropdown');
        var body = document.getElementById('notifBody');
        if (!bell || !dropdown || !body) return;

        function csrf() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        }

        function post(url) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            });
        }

        function refreshBadge() {
            var remaining = body.querySelectorAll('.notification-item.unread').length;
            var badge = document.getElementById('notifBadge');
            if (remaining > 0) {
                if (badge) {
                    badge.textContent = remaining;
                } else {
                    badge = document.createElement('span');
                    badge.className = 'notification-badge';
                    badge.id = 'notifBadge';
                    badge.textContent = remaining;
                    bell.appendChild(badge);
                }
            } else if (badge) {
                badge.remove();
            }
            var markAll = document.getElementById('notifMarkAll');
            if (markAll && remaining === 0) markAll.remove();
        }

        bell.addEventListener('click', function () {
            setTimeout(function () {
                bell.setAttribute('aria-expanded', dropdown.classList.contains('show') ? 'true' : 'false');
            }, 0);
        });

        body.addEventListener('click', function (e) {
            var item = e.target.closest('.notification-item');
            if (!item || !item.classList.contains('unread') || item.dataset.busy === '1') return;
            item.dataset.busy = '1';
            post(item.getAttribute('data-read-url')).then(function () {
                item.classList.remove('unread');
                refreshBadge();
            }).catch(function () {
                /* keep unread state so the user can retry */
            }).finally(function () {
                item.dataset.busy = '0';
            });
        });

        document.addEventListener('click', function (e) {
            if (e.target.closest && e.target.closest('#notifMarkAll')) {
                var btn = e.target.closest('#notifMarkAll');
                if (btn.dataset.busy === '1') return;
                btn.dataset.busy = '1';
                post(btn.getAttribute('data-url')).then(function () {
                    body.querySelectorAll('.notification-item.unread').forEach(function (el) {
                        el.classList.remove('unread');
                    });
                    refreshBadge();
                }).catch(function () {
                    /* keep state so the user can retry */
                }).finally(function () {
                    btn.dataset.busy = '0';
                });
            }
        });
    })();
</script>
@endpush
