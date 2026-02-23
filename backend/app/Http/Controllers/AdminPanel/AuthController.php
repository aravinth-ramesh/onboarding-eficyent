<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private OtpService $otpService,
    ) {}

    public function showLogin(): View
    {
        return view('admin.auth.login');
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        $email = $request->input('email');

        $user = User::where('email', $email)->where('is_admin', true)->first();

        if (!$user) {
            return back()->with('error', 'No admin account found with this email.')->withInput();
        }

        if (!$this->otpService->canRequestNewOtp($email)) {
            return back()->with('error', 'Please wait before requesting a new code.')->withInput();
        }

        $this->otpService->send($email);

        return back()->with([
            'otp_sent' => true,
            'otp_email' => $email,
            'success' => 'Verification code sent to your email.',
        ])->withInput();
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $email = $request->input('email');
        $code = $request->input('code');

        if (!$this->otpService->verify($email, $code)) {
            return back()->with([
                'error' => 'Invalid or expired verification code.',
                'otp_sent' => true,
                'otp_email' => $email,
            ])->withInput();
        }

        $user = User::where('email', $email)->where('is_admin', true)->first();

        if (!$user) {
            return back()->with('error', 'No admin account found.')->withInput();
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
