<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - Eficyent Admin</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ================================================================
           AURORA — Modern SaaS Theme (admin panel)
           Matches the React client app: indigo→purple gradients, soft
           layered shadows, 10–20px radii, glassy surfaces.
           ================================================================ */
        :root {
            --sidebar-width: 260px;
            --topbar-height: 56px;
            --color-primary: #6366F1;
            --color-primary-dark: #4F46E5;
            --color-primary-light: #8B5CF6;
            --color-accent: #6366F1;
            --color-accent-hover: #5B5BD6;
            --color-accent-soft: rgba(99, 102, 241, 0.10);
            --color-success: #10B981;
            --color-warning: #F59E0B;
            --color-danger: #EF4444;
            --color-text-primary: #0F172A;
            --color-text-secondary: #64748B;
            --color-text-muted: #94A3B8;
            --color-border: #E2E8F0;
            --color-border-light: #F1F5F9;

            --radius-sm: 10px;
            --radius-md: 14px;
            --radius-lg: 20px;

            --gradient-primary: linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%);
            --gradient-primary-soft: linear-gradient(135deg, rgba(99,102,241,0.12) 0%, rgba(139,92,246,0.12) 100%);
            --gradient-page: linear-gradient(135deg, #EEF2FF 0%, #FDF4FF 50%, #FAE8FF 100%);

            --shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.04);
            --shadow-md: 0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(99, 102, 241, 0.06);
            --shadow-lg: 0 1px 2px rgba(15, 23, 42, 0.04), 0 12px 32px rgba(99, 102, 241, 0.10);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--gradient-page) fixed;
            color: var(--color-text-primary);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Sidebar */
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--gradient-primary);
            color: #fff;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            box-shadow: 0 12px 32px rgba(99, 102, 241, 0.18);
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
            /* background: var(--color-accent); */
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
            background: rgba(255,255,255,0.16);
            border-left-color: #fff;
            font-weight: 600;
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
            background: rgba(255,255,255,0.18);
            border: 1px solid rgba(255,255,255,0.25);
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
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--color-border);
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
            font-weight: 700;
            margin: 0;
            color: var(--color-text-primary);
            letter-spacing: -0.02em;
        }

        .admin-content {
            padding: 1.5rem;
        }

        .admin-footer {
            padding: 1rem 1.5rem;
            text-align: center;
            font-size: 0.8rem;
            color: var(--color-text-muted);
            border-top: 1px solid var(--color-border);
        }

        /* Cards */
        .card {
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            background: var(--color-bg-card, #fff);
        }

        .card-header {
            background: linear-gradient(135deg, rgba(99,102,241,0.04), rgba(139,92,246,0.02));
            border-bottom: 1px solid var(--color-border-light);
            font-weight: 700;
            letter-spacing: -0.01em;
            padding: 0.875rem 1.25rem;
        }

        /* Buttons & links — align Bootstrap primary with Aurora */
        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.22);
        }

        .btn-primary:hover,
        .btn-primary:focus,
        .btn-primary:active {
            background: linear-gradient(135deg, #5B5BD6 0%, #7C4DEF 100%);
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.30);
        }

        .btn-outline-primary {
            color: var(--color-accent);
            border-color: var(--color-accent);
        }

        .btn-outline-primary:hover {
            background: var(--color-accent);
            border-color: var(--color-accent);
        }

        a { color: var(--color-accent); }
        a:hover { color: var(--color-accent-hover); }
        .text-primary { color: var(--color-accent) !important; }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--color-accent);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        }

        /* Tables */
        .table th {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--color-accent);
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

        .badge-approved {
            background: #d1e7dd;
            color: #0f5132;
        }

        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-in_progress {
            background: #cce5ff;
            color: #004085;
        }

        /* Stat Cards */
        .stat-card {
            border-radius: var(--radius-md);
            padding: 1.25rem;
            background: #fff;
            border: 1px solid var(--color-border);
            box-shadow: var(--shadow-md);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card .stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--color-primary);
        }

        .stat-card .stat-label {
            font-size: 0.8rem;
            color: var(--color-text-secondary);
            margin-top: 0.25rem;
        }

        .stat-card .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
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
            <div class="sidebar-brand-icon"><img class="img-fluid" src="{{ asset('favicon.png') }}" /></div>
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

            <a href="{{ route('admin.country-registrations.index') }}" class="sidebar-link {{ request()->routeIs('admin.country-registrations.*') ? 'active' : '' }}">
                <i class="bi bi-globe2"></i> Country Registrations
            </a>

            <a href="{{ route('admin.email-templates.index') }}" class="sidebar-link {{ request()->routeIs('admin.email-templates.*') ? 'active' : '' }}">
                <i class="bi bi-envelope-paper"></i> Email Templates
            </a>

            <div class="sidebar-heading">Monitoring</div>

            <a href="{{ route('admin.user-onboardings.index') }}" class="sidebar-link {{ request()->routeIs('admin.user-onboardings.*') ? 'active' : '' }}">
                <i class="bi bi-clipboard-check"></i> User Onboardings
            </a>

            <a href="{{ route('admin.document-reviews.index') }}" class="sidebar-link {{ request()->routeIs('admin.document-reviews.*') ? 'active' : '' }}">
                <i class="bi bi-file-earmark-check"></i> Document Reviews
            </a>

            <a href="{{ route('admin.audit-logs.index') }}" class="sidebar-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">
                <i class="bi bi-journal-text"></i> Audit Logs
            </a>

            <a href="{{ route('admin.admin-activity.index') }}" class="sidebar-link {{ request()->routeIs('admin.admin-activity.*') ? 'active' : '' }}">
                <i class="bi bi-person-lines-fill"></i> Admin Activity
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
