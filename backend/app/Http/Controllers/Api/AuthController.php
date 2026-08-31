<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendOtpRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Requests\Api\VerifyOtpRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        private OtpService $otpService,
    ) {}

    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $email = $request->validated('email');

        if (! $this->otpService->canRequestNewOtp($email)) {
            // Say how much longer, so the refused client can keep counting down
            // honestly rather than leaving the resend button lying about being
            // ready (EOP-3).
            return response()->json([
                'message' => 'Please wait before requesting a new code.',
                'resend_available_in_seconds' => $this->otpService->secondsUntilResendAllowed($email),
            ], 429);
        }

        $otp = $this->otpService->send($email);

        // The client can't show how long the code is good for, or when a
        // resend becomes available, unless we tell it (EOP-3, EOP-4).
        //
        // The deadline comes off the row that was just issued rather than the
        // configured duration: the two could drift, and the client should be
        // counting down to the moment the server will actually stop accepting
        // the code, not to a duration measured from whenever the response
        // happened to arrive.
        return response()->json([
            'message' => 'Verification code sent to your email.',
            'expires_at' => $otp->expires_at->toIso8601String(),
            'expires_in_seconds' => max(0, (int) ceil(now()->diffInSeconds($otp->expires_at, false))),
            'resend_available_in_seconds' => $this->otpService->resendCooldownSeconds(),
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (! $this->otpService->verify($validated['email'], $validated['code'])) {
            return response()->json([
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        // A soft-deleted account still owns its unique email address. Restore
        // it after successful email verification instead of attempting a
        // duplicate insert and leaking a database exception to the client.
        $user = User::withTrashed()->firstOrNew(['email' => $validated['email']]);

        if ($user->trashed()) {
            $user->restore();
        }

        if (! $user->exists) {
            $user->email_verified_at = now();
            $user->save();
        }

        if (! $user->email_verified_at) {
            $user->update(['email_verified_at' => now()]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'data' => [
                'user' => $this->formatUser($user, [
                    'has_onboarding' => $user->activeOnboarding() !== null,
                ]),
                'token' => $token,
            ],
        ]);
    }

    public function me(): JsonResponse
    {
        $user = auth()->user();
        $user->load('onboarding.steps');

        return response()->json([
            'data' => [
                'user' => $this->formatUser($user, [
                    'has_onboarding' => $user->activeOnboarding() !== null,
                    'onboarding' => $user->activeOnboarding(),
                ]),
            ],
        ]);
    }

    /**
     * Save the user's name and position. These details are collected only
     * once at the very start of onboarding and cannot be edited afterwards.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = auth()->user();

        if ($this->profileCompleted($user)) {
            return response()->json([
                'message' => 'Your name and position have already been provided and cannot be changed.',
            ], 422);
        }

        $user->update($request->validated());

        return response()->json([
            'message' => 'Profile saved successfully.',
            'data' => [
                'user' => $this->formatUser($user, [
                    'has_onboarding' => $user->activeOnboarding() !== null,
                ]),
            ],
        ]);
    }

    /**
     * Build the standard user payload returned by the auth endpoints.
     */
    private function formatUser(User $user, array $extra = []): array
    {
        return array_merge([
            'id' => $user->id,
            'name' => $user->name,
            'position' => $user->position,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'profile_completed' => $this->profileCompleted($user),
        ], $extra);
    }

    /**
     * A profile is complete once both the name and position are present.
     */
    private function profileCompleted(User $user): bool
    {
        return filled($user->name) && filled($user->position);
    }

    public function logout(): JsonResponse
    {
        auth()->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
