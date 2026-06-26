<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Eficyent</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #EEF2FF 0%, #FDF4FF 50%, #FAE8FF 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: "";
            position: absolute;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(139,92,246,0.22) 0%, transparent 70%);
            top: -220px; left: -180px;
            filter: blur(40px);
            pointer-events: none;
        }

        .login-card {
            position: relative;
            width: 100%;
            max-width: 420px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            box-shadow: 0 12px 40px rgba(99, 102, 241, 0.14);
            padding: 2.5rem;
        }

        .login-brand {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-brand-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%);
            color: #fff;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.4rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.30);
        }

        .login-brand h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0F172A;
            letter-spacing: -0.02em;
            margin: 0;
        }

        .login-brand p {
            font-size: 0.85rem;
            color: #64748B;
            margin: 0.25rem 0 0;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%);
            border: none;
            font-weight: 600;
            padding: 0.6rem;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.24);
        }

        .btn-primary:hover,
        .btn-primary:focus,
        .btn-primary:active {
            background: linear-gradient(135deg, #5B5BD6 0%, #7C4DEF 100%);
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.32);
        }

        .form-control:focus {
            border-color: #6366F1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-brand">
            <div class="login-brand-icon">E</div>
            <h2>Eficyent Admin</h2>
            <p>Sign in to manage your platform</p>
        </div>

        @if(session('error'))
            <div class="alert alert-danger py-2 px-3" style="font-size: 0.875rem;">
                {{ session('error') }}
            </div>
        @endif

        @if(session('success'))
            <div class="alert alert-success py-2 px-3" style="font-size: 0.875rem;">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.submit') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size: 0.875rem;">Email Address</label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                    value="{{ old('email') }}" placeholder="Enter your email" required autofocus>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size: 0.875rem;">Password</label>
                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                    placeholder="Enter your password" required>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" name="remember" class="form-check-input" id="remember"
                    {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label" for="remember" style="font-size: 0.85rem;">Remember me</label>
            </div>

            <button type="submit" class="btn btn-primary w-100">Sign In</button>
        </form>
    </div>
</body>
</html>
