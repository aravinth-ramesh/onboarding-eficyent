<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - Eficyent Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --topbar-height: 56px;
            --color-primary: #1a3a5c;
            --color-primary-dark: #0f2440;
            --color-accent: #2e86de;
            --color-success: #27ae60;
            --color-warning: #f39c12;
            --color-danger: #e74c3c;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }

        /* Sidebar */
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--color-primary);
            color: #fff;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .sidebar-brand {
            padding: 1.25rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-brand-icon {
            width: 36px;
            height: 36px;
            background: var(--color-accent);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .sidebar-brand-text {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .sidebar-brand-text small {
            display: block;
            font-size: 0.7rem;
            font-weight: 400;
            opacity: 0.7;
        }

        .sidebar-nav {
            flex: 1;
            padding: 0.75rem 0;
        }

        .sidebar-heading {
            padding: 0.5rem 1rem;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.5;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 1rem;
            color: rgba(255,255,255,0.75);
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.15s;
            border-left: 3px solid transparent;
        }

        .sidebar-link:hover {
            color: #fff;
            background: rgba(255,255,255,0.08);
        }

        .sidebar-link.active {
            color: #fff;
            background: rgba(255,255,255,0.12);
            border-left-color: var(--color-accent);
        }

        .sidebar-link i {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar-user-avatar {
            width: 32px;
            height: 32px;
            background: var(--color-accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .sidebar-user-info {
            flex: 1;
            min-width: 0;
        }

        .sidebar-user-name {
            font-size: 0.85rem;
            font-weight: 600;
        }

        .sidebar-user-email {
            font-size: 0.7rem;
            opacity: 0.6;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Main Content */
        .admin-main {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        .admin-topbar {
            height: var(--topbar-height);
            background: #fff;
            border-bottom: 1px solid #e1e5eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .admin-topbar h1 {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
            color: #2c3e50;
        }

        .admin-content {
            padding: 1.5rem;
        }

        .admin-footer {
            padding: 1rem 1.5rem;
            text-align: center;
            font-size: 0.8rem;
            color: #95a5a6;
            border-top: 1px solid #e1e5eb;
        }

        /* Cards */
        .card {
            border: 1px solid #e1e5eb;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }

        .card-header {
            background: #fff;
            border-bottom: 1px solid #e1e5eb;
            font-weight: 600;
            padding: 0.875rem 1.25rem;
        }

        /* Tables */
        .table th {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #6c757d;
            border-bottom-width: 1px;
        }

        .table td {
            vertical-align: middle;
            font-size: 0.9rem;
        }

        /* Badges */
        .badge-active {
            background: #d4edda;
            color: #155724;
        }

        .badge-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-in-progress {
            background: #cce5ff;
            color: #004085;
        }

        .badge-completed {
            background: #d4edda;
            color: #155724;
        }

        .badge-skipped {
            background: #e2e3e5;
            color: #383d41;
        }

        /* Stat Cards */
        .stat-card {
            border-radius: 8px;
            padding: 1.25rem;
            background: #fff;
            border: 1px solid #e1e5eb;
        }

        .stat-card .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--color-primary);
        }

        .stat-card .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .stat-card .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        /* Action buttons */
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        .actions-col {
            width: 120px;
            text-align: right;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            .admin-main {
                margin-left: 0;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon">E</div>
            <div class="sidebar-brand-text">
                Eficyent
                <small>Admin Panel</small>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <i class="bi bi-grid-1x2"></i> Dashboard
            </a>

            <div class="sidebar-heading">Configuration</div>

            <a href="{{ route('admin.user-types.index') }}" class="sidebar-link {{ request()->routeIs('admin.user-types.*') ? 'active' : '' }}">
                <i class="bi bi-people"></i> User Types
            </a>

            <a href="{{ route('admin.question-groups.index') }}" class="sidebar-link {{ request()->routeIs('admin.question-groups.*') ? 'active' : '' }}">
                <i class="bi bi-collection"></i> Question Groups
            </a>

            <a href="{{ route('admin.questions.index') }}" class="sidebar-link {{ request()->routeIs('admin.questions.*') ? 'active' : '' }}">
                <i class="bi bi-question-circle"></i> Questions
            </a>

            <a href="{{ route('admin.conditional-rules.index') }}" class="sidebar-link {{ request()->routeIs('admin.conditional-rules.*') ? 'active' : '' }}">
                <i class="bi bi-diagram-3"></i> Conditional Rules
            </a>

            <a href="{{ route('admin.onboarding-steps.index') }}" class="sidebar-link {{ request()->routeIs('admin.onboarding-steps.*') ? 'active' : '' }}">
                <i class="bi bi-list-ol"></i> Onboarding Steps
            </a>

            <div class="sidebar-heading">Monitoring</div>

            <a href="{{ route('admin.user-onboardings.index') }}" class="sidebar-link {{ request()->routeIs('admin.user-onboardings.*') ? 'active' : '' }}">
                <i class="bi bi-clipboard-check"></i> User Onboardings
            </a>

            <a href="{{ route('admin.audit-logs.index') }}" class="sidebar-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">
                <i class="bi bi-journal-text"></i> Audit Logs
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar">{{ strtoupper(substr(auth('admin')->user()->name ?? auth('admin')->user()->email, 0, 1)) }}</div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name">{{ auth('admin')->user()->name ?? 'Admin' }}</div>
                    <div class="sidebar-user-email">{{ auth('admin')->user()->email }}</div>
                </div>
                <form action="{{ route('admin.logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-link text-white-50 p-0" title="Sign out">
                        <i class="bi bi-box-arrow-right"></i>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- Main -->
    <div class="admin-main">
        <div class="admin-topbar">
            <h1>@yield('title', 'Dashboard')</h1>
            <div>
                @yield('actions')
            </div>
        </div>

        <div class="admin-content">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Please fix the following errors:</strong>
                    <ul class="mb-0 mt-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </div>

        <footer class="admin-footer">
            &copy; {{ date('Y') }} Eficyent. All rights reserved.
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2-enable').each(function() {
                $(this).select2({
                    theme: 'bootstrap-5',
                    placeholder: $(this).data('placeholder') || '-- Select --',
                    allowClear: !$(this).prop('required'),
                    width: '100%',
                    dropdownParent: $(this).closest('.modal').length ? $(this).closest('.modal') : $(document.body),
                });
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
