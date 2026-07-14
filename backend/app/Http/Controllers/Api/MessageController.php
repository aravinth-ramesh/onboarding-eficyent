<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\NewMessageMail;
use App\Models\Admin;
use App\Models\UserOnboarding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Client side of the message thread. Messaging is available in every
 * onboarding state — asking questions must never be locked.
 */
class MessageController extends Controller
{
    public function index(): JsonResponse
    {
        $onboarding = $this->onboarding();
        if (! $onboarding) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $onboarding->messages()->with('admin:id,name')->get()->map(fn ($m) => [
                'id' => $m->id,
                'sender_type' => $m->sender_type,
                'sender_name' => $m->sender_type === 'admin' ? ($m->admin->name ?? 'Eficyent Team') : null,
                'body' => $m->body,
                'created_at' => $m->created_at,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['body' => 'required|string|max:5000']);

        $onboarding = $this->onboarding();
        if (! $onboarding) {
            return response()->json(['message' => 'Start your onboarding before sending messages.'], 422);
        }

        $message = $onboarding->messages()->create([
            'sender_type' => 'client',
            'user_id' => $onboarding->user_id,
            'body' => $validated['body'],
        ]);

        try {
            foreach (Admin::where('is_active', true)->pluck('email') as $email) {
                Mail::to($email)->queue(new NewMessageMail($message));
            }
        } catch (\Throwable $e) {
            Log::warning('message notification failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['data' => ['id' => $message->id]], 201);
    }

    /** Admin replies the client hasn't seen yet. */
    public function unreadCount(): JsonResponse
    {
        $onboarding = $this->onboarding();

        return response()->json([
            'count' => $onboarding
                ? $onboarding->messages()->where('sender_type', 'admin')->whereNull('read_at')->count()
                : 0,
        ]);
    }

    /** The client opened the thread — mark admin replies as read. */
    public function markRead(): JsonResponse
    {
        $this->onboarding()?->messages()
            ->where('sender_type', 'admin')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    private function onboarding(): ?UserOnboarding
    {
        /**@disregard */
        return auth()->user()->onboarding;
    }
}
