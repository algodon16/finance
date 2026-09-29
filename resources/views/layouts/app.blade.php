<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <div class="app-container">
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-logo">
                <a href="{{ route(Auth::user()->role . '.dashboard') }}" class="sidebar-brand">
                    <div class="brand-title">BESTLINK</div>
                    <div class="brand-subtitle">COLLEGE OF THE PHILIPPINES</div>
                </a>
                <p class="sidebar-role">{{ strtoupper(Auth::user()->role) }}</p>
            </div>

            <nav class="sidebar-nav">
                @if(Auth::user()->role === 'student')
                    <a href="{{ route('student.dashboard') }}" class="{{ request()->routeIs('student.dashboard') ? 'active' : '' }}">
                        Dashboard
                    </a>
                    <a href="{{ route('student.payments.index') }}" class="{{ request()->routeIs('student.payments.*') ? 'active' : '' }}">
                        Payments
                    </a>
                    <a href="{{ route('student.budget.index') }}" class="{{ request()->routeIs('student.budget.*') ? 'active' : '' }}">
                        Budget Planner
                    </a>
                    <div class="sidebar-submenu {{ request()->routeIs('student.procurement.*') ? 'open active-parent' : '' }}">
                        <a href="{{ route('student.procurement.index') }}" class="submenu-toggle {{ request()->routeIs('student.procurement.*') ? 'active' : '' }}">
                            Procurement
                        </a>
                        <div class="submenu-items">
                            <a href="{{ route('student.procurement.index') }}" class="{{ request()->routeIs('student.procurement.index', 'student.procurement.show', 'student.procurement.request.show', 'student.procurement.request') ? 'active' : '' }}">
                                Academic Items
                            </a>
                            <a href="{{ route('student.procurement.materials.index') }}" class="{{ request()->routeIs('student.procurement.materials.*') ? 'active' : '' }}">
                                Learning Materials
                            </a>
                        </div>
                    </div>
                    <a href="{{ route('student.clearance') }}" class="{{ request()->routeIs('student.clearance') ? 'active' : '' }}">
                        Clearance
                    </a>
                    <a href="{{ route('student.settings.index') }}" class="{{ request()->routeIs('student.settings.*') ? 'active' : '' }}">
                        Settings
                    </a>
                @elseif(Auth::user()->role === 'cashier')
                    <a href="{{ route('cashier.dashboard') }}" class="{{ request()->routeIs('cashier.dashboard') ? 'active' : '' }}">
                        Dashboard
                    </a>
                    <a href="{{ route('cashier.payment.index') }}" class="{{ request()->routeIs('cashier.payment.*') ? 'active' : '' }}">
                        Payment
                    </a>
                    <a href="{{ route('cashier.payments.index') }}" class="{{ request()->routeIs('cashier.payments.*') ? 'active' : '' }}">
                        Payment Verification
                    </a>
                    <a href="{{ route('cashier.reports.summary') }}" class="{{ request()->routeIs('cashier.reports.summary') ? 'active' : '' }}">
                        Payment History
                    </a>
                    <a href="{{ route('cashier.reports.index') }}" class="{{ request()->routeIs('cashier.reports.index', 'cashier.reports.daily') ? 'active' : '' }}">
                        Reports
                    </a>
                @elseif(Auth::user()->role === 'accountant')
                    <a href="{{ route('accountant.dashboard') }}" class="{{ request()->routeIs('accountant.dashboard') ? 'active' : '' }}">
                        Dashboard
                    </a>
                    <div class="sidebar-section-label">Financial Management</div>
                    <a href="{{ route('accountant.budgets.index') }}" class="{{ request()->routeIs('accountant.budgets.*') ? 'active' : '' }}">
                        Budget Planning and Allocation
                    </a>
                    <a href="{{ route('accountant.revenue.index') }}" class="{{ request()->routeIs('accountant.revenue.*') ? 'active' : '' }}">
                        Revenue Management
                    </a>
                    <a href="{{ route('accountant.expenses.index') }}" class="{{ request()->routeIs('accountant.expenses.*') ? 'active' : '' }}">
                        Expense and Disbursement Tracking
                    </a>
                    <a href="{{ route('accountant.payables.index') }}" class="{{ request()->routeIs('accountant.payables.*') ? 'active' : '' }}">
                        Accounts Payable Management
                    </a>
                    <a href="{{ route('accountant.accounts-receivable.index') }}" class="{{ request()->routeIs('accountant.accounts-receivable.*') ? 'active' : '' }}">
                        Accounts Receivable Management
                    </a>
                    <a href="{{ route('accountant.fund-allocations.index') }}" class="{{ request()->routeIs('accountant.fund-allocations.*') ? 'active' : '' }}">
                        Fund Management and Allocation
                    </a>
                    <a href="{{ route('accountant.financial-requests.index') }}" class="{{ request()->routeIs('accountant.financial-requests.*', 'accountant.procurement.*') ? 'active' : '' }}">
                        Procurement and Financial Requests
                    </a>
                    <a href="{{ route('accountant.assets.index') }}" class="{{ request()->routeIs('accountant.assets.*') ? 'active' : '' }}">
                        Asset and Depreciation Management
                    </a>
                    <div class="sidebar-section-label">Reporting</div>
                    <a href="{{ route('accountant.financial-reports.index') }}" class="{{ request()->routeIs('accountant.financial-reports.*', 'accountant.reconciliation.*', 'accountant.reconciliation-records.*') ? 'active' : '' }}">
                        Financial Reporting and Compliance
                    </a>
                    <div class="sidebar-section-label">Audit</div>
                    <a href="{{ route('accountant.audit.index') }}" class="{{ request()->routeIs('accountant.audit.*') ? 'active' : '' }}">
                        Security and Audit Trail
                    </a>
                    <a href="{{ route('accountant.settings.index') }}" class="{{ request()->routeIs('accountant.settings.*') ? 'active' : '' }}">
                        System Settings
                    </a>
                @elseif(Auth::user()->role === 'admin')
                    <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        Dashboard
                    </a>
                    <a href="{{ route('admin.students.index') }}" class="{{ request()->routeIs('admin.students.*') ? 'active' : '' }}">
                        Student Management
                    </a>
                    <a href="{{ route('admin.procurementItems.index') }}" class="{{ request()->routeIs('admin.procurementItems.*') ? 'active' : '' }}">
                        Academic Items
                    </a>
                    <a href="{{ route('admin.feeAssessment.index') }}" class="{{ request()->routeIs('admin.feeAssessment.*', 'admin.assessmentRules.*') ? 'active' : '' }}">
                        Fee Assessment
                    </a>
                    <a href="{{ route('admin.payments.index') }}" class="{{ request()->routeIs('admin.payments.*') ? 'active' : '' }}">
                        Payments
                    </a>
                    <a href="{{ route('admin.notifications.index') }}" class="{{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}">
                        Notifications
                    </a>
                @endif
            </nav>

            <div class="sidebar-footer">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-logout">
                        Logout
                    </button>
                </form>
            </div>
        </aside>

        <main class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="hamburger" id="sidebarToggle" aria-label="Toggle menu">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                </div>

                <div class="top-bar-right">
                    @if(in_array(Auth::user()->role, ['student', 'cashier']))
                        <span class="top-bar-clock" id="topBarClock"></span>
                    @endif
                    @include('layouts.partials.notification-bell')

                    <div class="user-menu">
                        <span class="avatar-circle">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                        <span class="top-bar-user">
                            <span class="user-name">{{ Auth::user()->name }}</span>
                            <span class="user-role">{{ ucfirst(Auth::user()->role) }}</span>
                        </span>
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
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if(session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif

            <div class="content-area">
                @yield('content')
            </div>

            <footer class="app-footer">
                <p>&copy; 2026 Bestlink College of the Philippines. All rights reserved.</p>
                <p class="footer-tagline">Together for a Brighter Tomorrow.</p>
            </footer>
        </main>
    </div>

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
    @stack('scripts')
</body>
</html>
