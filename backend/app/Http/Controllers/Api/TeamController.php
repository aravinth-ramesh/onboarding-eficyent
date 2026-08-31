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
use Illuminate\Support\Str;

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

        // Mark the account as invitation-only so that removing this membership
        // later does not hand them an application of their own, which would
        // show them as its owner (EOP-56).
        //
        // This has to cover an account that already existed, not just one
        // created here: someone who had signed in but never started an
        // application kept a null invited_at, so removing them still minted a
        // fresh application they owned. Anyone reaching this point has no
        // application of their own -- the guard above returns early otherwise.
        if ($invitee->invited_at === null) {
            $invitee->forceFill(['invited_at' => now()])->save();
        }

        $collaborator = OnboardingCollaborator::create([
            'user_onboarding_id' => $onboarding->id,
            'user_id' => $invitee->id,
            'invited_by' => $user->id,
            'invite_token' => Str::random(48),
        ]);

        try {
            Mail::to($email)->queue(new TeamInviteMail($onboarding->load('user'), $user, $collaborator->invite_token));
        } catch (\Throwable $e) {
            Log::warning('team invite email failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['data' => ['id' => $collaborator->id]], 201);
    }

    /**
     * Public: describe an invitation so the portal can tell the visitor which
     * address it was sent to, before anyone authenticates. Reveals only the
     * invited email and who invited them — never the application's contents.
     */
    public function showInvitation(string $token): JsonResponse
    {
        $collaborator = OnboardingCollaborator::with(['user', 'inviter', 'onboarding'])
            ->where('invite_token', $token)
            ->first();

        if (! $collaborator) {
            return response()->json(['message' => 'This invitation link is not valid.'], 404);
        }

        return response()->json(['data' => [
            'email' => $collaborator->user->email,
            'inviter' => $collaborator->inviter->name ?? $collaborator->inviter->email,
            'company' => $collaborator->onboarding->displayName ?? null,
            'accepted' => $collaborator->accepted_at !== null,
        ]]);
    }

    /**
     * Accept an invitation as the signed-in user. The authenticated account
     * must be the invited address — following the link from someone else's
     * session must never join (or expose) their application (EOP-53).
     */
    public function acceptInvitation(string $token): JsonResponse
    {
        /**@disregard */
        $user = auth()->user();

        $collaborator = OnboardingCollaborator::with('user')
            ->where('invite_token', $token)
            ->first();

        if (! $collaborator) {
            return response()->json(['message' => 'This invitation link is not valid.'], 404);
        }

        if (strtolower($collaborator->user->email) !== strtolower($user->email)) {
            return response()->json([
                'message' => 'This invitation was sent to '.$collaborator->user->email
                    .'. Sign in with that address to join.',
            ], 403);
        }

        if (! $collaborator->accepted_at) {
            $collaborator->update(['accepted_at' => now()]);
        }

        return response()->json(['data' => ['accepted' => true]]);
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
