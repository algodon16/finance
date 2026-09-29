<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Dashboard')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        .fms-stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 20px; }
        .fms-stat { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px 20px; border-left: 4px solid #1a56db; }
        a.fms-stat-link { display: block; color: inherit; text-decoration: none; cursor: pointer; transition: background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease; }
        a.fms-stat-link:hover { background: #f8fafc; border-color: #cbd5e1; border-left-color: #1a56db; box-shadow: 0 4px 12px rgba(15, 42, 90, 0.08); transform: translateY(-1px); text-decoration: none; color: inherit; }
        a.fms-stat-link:focus-visible { outline: 2px solid #1a56db; outline-offset: 2px; background: #f8fafc; box-shadow: 0 4px 12px rgba(15, 42, 90, 0.08); }
        .fms-stat h4 { margin: 0 0 6px; font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; }
        .fms-stat .val { margin: 0; font-size: 1.4rem; font-weight: 700; color: #0f2a5a; }
        .fms-stat .sub { margin: 4px 0 0; font-size: 0.8rem; color: #64748b; }
        .fms-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px 22px; margin-bottom: 20px; }
        .fms-panel h3 { margin: 0 0 14px; font-size: 1.05rem; color: #0f2a5a; }
        .fms-two { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .fms-analytics-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 20px; align-items: stretch; }
        .fms-chart-card { margin-bottom: 0; display: flex; flex-direction: column; }
        .fms-chart-card h3 { flex-shrink: 0; }
        .fms-chart-wrap { position: relative; height: 260px; }
        .fms-chart-wrap canvas { width: 100% !important; height: 100% !important; }
        .fms-donut-wrap { position: relative; height: 220px; }
        .fms-donut-wrap canvas { width: 100% !important; height: 100% !important; }
        .fms-donut-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; }
        .fms-donut-center strong { font-size: 1.6rem; color: #0f2a5a; line-height: 1; }
        .fms-donut-center span { font-size: 0.8rem; color: #64748b; margin-top: 4px; }
        .fms-budget-legend { margin-top: 14px; border-top: 1px solid #eef2f7; padding-top: 12px; display: flex; flex-direction: column; gap: 8px; font-size: 0.85rem; }
        .fms-budget-legend div { display: flex; justify-content: space-between; gap: 12px; }
        .fms-budget-legend span { color: #64748b; }
        .fms-budget-legend strong { color: #0f2a5a; }
        .fms-budget-legend .total { border-top: 1px dashed #e2e8f0; padding-top: 8px; }
        .fms-bar { height: 10px; background: #eef2f7; border-radius: 5px; overflow: hidden; margin-top: 6px; }
        .fms-bar > span { display: block; height: 100%; background: #1a56db; }
        .fms-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .fms-table th { background: #f1f5f9; text-align: left; padding: 10px 12px; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; border-bottom: 2px solid #e2e8f0; }
        .fms-table td { padding: 10px 12px; border-bottom: 1px solid #eef2f7; vertical-align: middle; }
        .fms-table tr:hover td { background: #f8fafc; }
        .fms-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .status { display: inline-block; padding: 2px 10px; font-size: 0.72rem; font-weight: 600; border-radius: 20px; background: #eef2ff; color: #1a56db; white-space: nowrap; }
        .status.st-green { background: #ecfdf5; color: #047857; }
        .status.st-red { background: #fef2f2; color: #b91c1c; }
        .status.st-amber { background: #fffbeb; color: #b45309; }
        .status.st-gray { background: #f1f5f9; color: #475569; }
        .ai-box { border: 1px solid #bfdbfe; background: #eff6ff; border-radius: 8px; padding: 16px 18px; margin-top: 16px; }
        .ai-box h4 { margin: 0 0 8px; color: #0f2a5a; font-size: 0.95rem; }
        .ai-tag { display: inline-block; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; background: #1a56db; color: #fff; border-radius: 4px; padding: 2px 8px; margin-bottom: 8px; }
        .main-content.reports-blurred { filter: blur(6px); pointer-events: none; user-select: none; }
        .reports-gate-overlay { position: fixed; inset: 0; z-index: 3000; display: flex; align-items: center; justify-content: center; padding: 20px; background: rgba(15, 42, 90, 0.35); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); }
        .reports-gate-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 12px 32px rgba(15, 42, 90, 0.18); width: 100%; max-width: 400px; padding: 28px 28px 24px; }
        .reports-gate-card h3 { margin: 0 0 6px; font-size: 1.15rem; color: #0f2a5a; text-align: center; }
        .reports-gate-sub { margin: 0 0 18px; font-size: 0.85rem; color: #64748b; text-align: center; line-height: 1.6; }
        .reports-gate-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 6px; padding: 10px 14px; font-size: 0.85rem; margin-bottom: 16px; line-height: 1.6; }
        .reports-gate-card .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 0.875rem; color: #0f2a5a; }
        .reports-gate-passwrap { position: relative; }
        .reports-gate-passwrap .form-control { width: 100%; padding-right: 64px; }
        .reports-gate-toggle { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--primary); font-size: 0.8rem; font-weight: 600; cursor: pointer; padding: 6px 8px; }
        .reports-gate-actions { display: flex; gap: 10px; }
        .reports-gate-actions .btn { flex: 1; }
        .admin-top-bar { gap: 16px; }
        .admin-title-block { display: flex; flex-direction: column; line-height: 1.25; min-width: 0; }
        .admin-title { margin: 0; font-size: 1.15rem; font-weight: 700; color: #0f2a5a; white-space: nowrap; }
        .admin-breadcrumb { margin: 2px 0 0; font-size: 0.78rem; color: #64748b; white-space: nowrap; }
        .admin-breadcrumb a { color: #64748b; text-decoration: none; }
        .admin-breadcrumb a:hover { color: #2563EB; text-decoration: underline; }
        .admin-breadcrumb .crumb-sep { margin: 0 6px; color: #94a3b8; }
        .admin-breadcrumb .crumb-current { color: #2563EB; font-weight: 500; }
        .admin-top-bar .top-bar-right { gap: 14px; }
        .admin-datetime { display: flex; align-items: center; gap: 12px; background: #EFF6FF; border: 1px solid #DBEAFE; border-radius: 10px; padding: 8px 16px; white-space: nowrap; }
        .admin-datetime .datetime-item { display: flex; align-items: center; gap: 8px; font-size: 0.83rem; color: #0f2a5a; font-weight: 500; }
        .admin-datetime .datetime-item svg { width: 15px; height: 15px; color: #2563EB; flex-shrink: 0; }
        .admin-datetime #adminTime { font-variant-numeric: tabular-nums; font-weight: 600; }
        .admin-datetime .datetime-sep { width: 1px; height: 18px; background: #BFDBFE; flex-shrink: 0; }
        .dropdown-chevron { width: 14px; height: 14px; color: #64748b; flex-shrink: 0; }
        @media (max-width: 1100px) { .fms-stat-grid { grid-template-columns: repeat(2, 1fr); } .fms-two { grid-template-columns: 1fr; } .fms-analytics-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 900px) {
            .admin-top-bar { flex-wrap: wrap; row-gap: 10px; }
            .admin-datetime { order: 3; flex: 1 1 100%; justify-content: flex-start; overflow-x: auto; }
        }
        @media (max-width: 760px) { .fms-stat-grid { grid-template-columns: 1fr; } .fms-analytics-grid { grid-template-columns: 1fr; } .fms-chart-wrap { height: 240px; } }
        @media (max-width: 640px) {
            .top-bar { padding: 10px 16px; }
            .admin-title { font-size: 1rem; }
            .admin-breadcrumb { font-size: 0.72rem; }
            .top-bar-user { display: none; }
            .admin-datetime { padding: 7px 12px; gap: 8px; }
            .admin-datetime .datetime-item { font-size: 0.75rem; }
        }
        @media print { .sidebar, .top-bar, .no-print { display: none !important; } .main-content { margin-left: 0 !important; } }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-logo">
                <a href="{{ route('admin.dashboard') }}" class="sidebar-brand">
                    <div class="brand-title">BESTLINK</div>
                    <div class="brand-subtitle">COLLEGE OF THE PHILIPPINES</div>
                </a>
                <p class="sidebar-role">ADMINISTRATOR</p>
            </div>

            <nav class="sidebar-nav">
                <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Dashboard</a>
                <a href="{{ route('admin.budgets.index') }}" class="{{ request()->routeIs('admin.budgets.*') ? 'active' : '' }}">Budget Planning and Allocation</a>
                <a href="{{ route('admin.revenues.index') }}" class="{{ request()->routeIs('admin.revenues.*') ? 'active' : '' }}">Revenue Management</a>
                <a href="{{ route('admin.expenses.index') }}" class="{{ request()->routeIs('admin.expenses.*') ? 'active' : '' }}">Expense and Disbursement Tracking</a>
                <a href="{{ route('admin.payables.index') }}" class="{{ request()->routeIs('admin.payables.*') ? 'active' : '' }}">Accounts Payable Management</a>
                <a href="{{ route('admin.receivables.index') }}" class="{{ request()->routeIs('admin.receivables.*') ? 'active' : '' }}">Accounts Receivable Management</a>
                <a href="{{ route('admin.funds.index') }}" class="{{ request()->routeIs('admin.funds.*') ? 'active' : '' }}">Fund Management and Allocation</a>
                <a href="{{ route('admin.procurement.index') }}" class="{{ request()->routeIs('admin.procurement.*') ? 'active' : '' }}">Procurement and Financial Requests</a>
                <a href="{{ route('admin.financial-requests.index') }}" class="{{ request()->routeIs('admin.financial-requests.*') ? 'active' : '' }}">Financial Requests Inbox</a>
                <a href="{{ route('admin.assets.index') }}" class="{{ request()->routeIs('admin.assets.*') ? 'active' : '' }}">Asset and Depreciation Management</a>
                <a href="{{ route('admin.reports.index') }}" class="{{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">Financial Reporting and Compliance</a>
                <a href="{{ route('admin.audit.index') }}" class="{{ request()->routeIs('admin.audit.*') ? 'active' : '' }}">Security and Audit Trail</a>
                <a href="{{ route('admin.settings.index') }}" class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">System Settings</a>
            </nav>

            <div class="sidebar-footer">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-logout">Logout</button>
                </form>
            </div>
        </aside>

        <main class="main-content{{ (request()->routeIs('admin.reports.*') && session('reports.unlocked') !== true) ? ' reports-blurred' : '' }}">
            <header class="top-bar admin-top-bar">
                <div class="top-bar-left">
                    <button class="hamburger" id="sidebarToggle" aria-label="Toggle menu">
                        <span></span><span></span><span></span>
                    </button>
                    <div class="admin-title-block">
                        <h1 class="admin-title">@yield('title', 'Admin Dashboard')</h1>
                        <p class="admin-breadcrumb"><a href="{{ route('admin.dashboard') }}">Dashboard</a><span class="crumb-sep">&gt;</span><span class="crumb-current">Financial Overview</span></p>
                    </div>
                </div>
                <div class="top-bar-right">
                    <div class="admin-datetime" aria-label="Current date and time">
                        <span class="datetime-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            <span id="adminDate"></span>
                        </span>
                        <span class="datetime-sep" aria-hidden="true"></span>
                        <span class="datetime-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            <span id="adminTime"></span>
                        </span>
                    </div>
                    @include('layouts.partials.notification-bell')
                    <div class="user-menu">
                        <span class="avatar-circle">{{ strtoupper(substr(Auth::user()->name ?? 'System Admin', 0, 1)) }}</span>
                        <span class="top-bar-user">
                            <span class="user-name">{{ Auth::user()->name ?? 'System Admin' }}</span>
                            <span class="user-role">Admin</span>
                        </span>
                        <svg class="dropdown-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        <div class="user-dropdown">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit">Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            @if(session('success'))
                <div class="alert alert-success" style="margin:16px 32px 0;">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-error" style="margin:16px 32px 0;">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-error" style="margin:16px 32px 0;">
                    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            <div class="content-area">
                @yield('content')
            </div>

            <footer class="app-footer">
                <p>&copy; 2026 Bestlink College of the Philippines. All rights reserved.</p>
            </footer>
        </main>
    </div>

    @include('admin.fms.reports.partials.secure-access')

    <script src="{{ asset('js/app.js') }}"></script>
    <script>
        // Session keep-alive: ping every 10 minutes while the tab is open
        // so long-open pages don't expire/logout on their own.
        (function () {
            setInterval(function () {
                fetch("{{ route('keep-alive') }}", { credentials: 'same-origin' }).catch(function () {});
            }, 10 * 60 * 1000);
        })();
    </script>
    <script>
        (function () {
            var dateEl = document.getElementById('adminDate');
            var timeEl = document.getElementById('adminTime');
            if (!dateEl || !timeEl) return;
            const updateDateTime = () => {
                const now = new Date();
                dateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
                timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
            };
            updateDateTime();
            setInterval(updateDateTime, 1000);
        })();
    </script>
    @stack('scripts')
</body>
</html>
