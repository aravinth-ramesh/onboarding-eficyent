<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendOtpRequest;
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

        if (!$this->otpService->canRequestNewOtp($email)) {
            return response()->json([
                'message' => 'Please wait before requesting a new code.',
            ], 429);
        }

        $this->otpService->send($email);

        return response()->json([
            'message' => 'Verification code sent to your email.',
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (!$this->otpService->verify($validated['email'], $validated['code'])) {
            return response()->json([
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        $user = User::firstOrCreate(
            ['email' => $validated['email']],
            ['email_verified_at' => now()],
        );

        if (!$user->email_verified_at) {
            $user->update(['email_verified_at' => now()]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_admin' => $user->is_admin,
                    'has_onboarding' => $user->onboarding()->exists(),
                ],
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
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_admin' => $user->is_admin,
                    'has_onboarding' => $user->onboarding !== null,
                    'onboarding' => $user->onboarding,
                ],
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        auth()->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
