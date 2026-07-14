<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\TeamInviteMail;
use App\Models\OnboardingCollaborator;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Team management for an application: the owner invites colleagues by
 * email; invitees log in with the normal OTP flow and land on the shared
 * application. Owner-only for invite/remove; everyone can list.
 */
class TeamController extends Controller
{
    public function index(): JsonResponse
    {
        /**@disregard */
        $user = auth()->user();
        $onboarding = $user->activeOnboarding();

        if (! $onboarding) {
            return response()->json(['data' => ['owner' => null, 'members' => [], 'is_owner' => false]]);
        }

        $onboarding->load(['user', 'collaborators.user']);

        return response()->json(['data' => [
            'is_owner' => $user->ownsActiveOnboarding(),
            'owner' => [
                'name' => $onboarding->user->name,
                'email' => $onboarding->user->email,
            ],
            'members' => $onboarding->collaborators->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->user->name,
                'email' => $c->user->email,
                'joined' => $c->user->profile_completed ?? (bool) $c->user->name,
                'invited_at' => $c->created_at,
            ])->values(),
        ]]);
    }

    public function invite(Request $request): JsonResponse
    {
        /**@disregard */
        $user = auth()->user();
        $onboarding = $user->activeOnboarding();

        if (! $onboarding || ! $user->ownsActiveOnboarding()) {
            return response()->json(['message' => 'Only the application owner can invite team members.'], 403);
        }

        $validated = $request->validate(['email' => 'required|email|max:255']);
        $email = strtolower($validated['email']);

        if ($email === strtolower($user->email)) {
            return response()->json(['message' => 'That is your own email address.'], 422);
        }

        $invitee = User::where('email', $email)->first();

        if ($invitee) {
            if ($invitee->onboarding()->exists()) {
                return response()->json(['message' => 'This person already has their own application and cannot be added.'], 422);
            }
            if ($invitee->collaboration()->exists()) {
                return response()->json(['message' => 'This person already belongs to a team.'], 422);
            }
        } else {
            $invitee = User::create(['email' => $email]);
        }

        $collaborator = OnboardingCollaborator::create([
            'user_onboarding_id' => $onboarding->id,
            'user_id' => $invitee->id,
            'invited_by' => $user->id,
        ]);

        try {
            Mail::to($email)->queue(new TeamInviteMail($onboarding->load('user'), $user));
        } catch (\Throwable $e) {
            Log::warning('team invite email failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['data' => ['id' => $collaborator->id]], 201);
    }

    public function remove(OnboardingCollaborator $collaborator): JsonResponse
    {
        /**@disregard */
        $user = auth()->user();
        $onboarding = $user->activeOnboarding();

        if (! $onboarding
            || ! $user->ownsActiveOnboarding()
            || (int) $collaborator->user_onboarding_id !== (int) $onboarding->id) {
            return response()->json(['message' => 'Only the application owner can remove team members.'], 403);
        }

        $collaborator->delete();

        return response()->json(['success' => true]);
    }
}
