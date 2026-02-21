<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\OtpCode;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    private const OTP_LENGTH = 6;
    private const OTP_EXPIRY_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;
    private const RATE_LIMIT_MINUTES = 1; // Minimum time between OTP requests

    public function generate(string $email): OtpCode
    {
        // Invalidate any existing unused OTPs
        OtpCode::where('email', $email)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), self::OTP_LENGTH, '0', STR_PAD_LEFT);

        $otp = OtpCode::create([
            'email' => $email,
            'code' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
        ]);

        // Store plain code temporarily for the mail
        $otp->plain_code = $code;

        return $otp;
    }

    public function send(string $email): OtpCode
    {
        $otp = $this->generate($email);

        Mail::to($email)->queue(new OtpMail($otp->plain_code));

        return $otp;
    }

    public function verify(string $email, string $code): bool
    {
        $otp = OtpCode::where('email', $email)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp) {
            return false;
        }

        if ($otp->hasExceededAttempts(self::MAX_ATTEMPTS)) {
            return false;
        }

        $otp->increment('attempts');

        if (!hash_equals($otp->code, hash('sha256', $code))) {
            return false;
        }

        $otp->update(['verified_at' => now()]);

        return true;
    }

    public function canRequestNewOtp(string $email): bool
    {
        $lastOtp = OtpCode::where('email', $email)
            ->latest()
            ->first();

        if (!$lastOtp) {
            return true;
        }

        return $lastOtp->created_at->addMinutes(self::RATE_LIMIT_MINUTES)->isPast();
    }
}
