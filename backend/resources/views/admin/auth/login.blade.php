<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Eficyent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            padding: 2.5rem;
        }

        .login-brand {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-brand-icon {
            width: 48px;
            height: 48px;
            background: #1a3a5c;
            color: #fff;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.4rem;
            margin-bottom: 0.75rem;
        }

        .login-brand h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1a3a5c;
            margin: 0;
        }

        .login-brand p {
            font-size: 0.85rem;
            color: #6c757d;
            margin: 0.25rem 0 0;
        }

        .btn-primary {
            background: #1a3a5c;
            border-color: #1a3a5c;
        }

        .btn-primary:hover {
            background: #0f2440;
            border-color: #0f2440;
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

        @if(session('otp_sent'))
            {{-- OTP Verification Form --}}
            <form method="POST" action="{{ route('admin.login.verify-otp') }}">
                @csrf
                <input type="hidden" name="email" value="{{ session('otp_email') }}">

                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size: 0.875rem;">Email</label>
                    <input type="email" class="form-control" value="{{ session('otp_email') }}" disabled>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size: 0.875rem;">Verification Code</label>
                    <input type="text" name="code" class="form-control text-center fw-bold" style="letter-spacing: 0.3em; font-size: 1.2rem;"
                        maxlength="6" placeholder="000000" required autofocus
                        inputmode="numeric" pattern="[0-9]*">
                    @error('code')
                        <div class="text-danger mt-1" style="font-size: 0.8rem;">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">Verify & Sign In</button>

                <div class="text-center">
                    <a href="{{ route('admin.login') }}" class="text-decoration-none" style="font-size: 0.85rem;">
                        Back to login
                    </a>
                </div>
            </form>
        @else
            {{-- Email Form --}}
            <form method="POST" action="{{ route('admin.login.send-otp') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size: 0.875rem;">Email Address</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}"
                        placeholder="admin@eficyent.com" required autofocus>
                    @error('email')
                        <div class="text-danger mt-1" style="font-size: 0.8rem;">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary w-100">Send Verification Code</button>
            </form>
        @endif
    </div>
</body>
</html>
