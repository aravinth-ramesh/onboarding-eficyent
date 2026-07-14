<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\OnboardingNote;
use App\Models\UserOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnboardingNoteController extends Controller
{
    public function store(Request $request, UserOnboarding $userOnboarding): RedirectResponse
    {
        $validated = $request->validate(['note' => 'required|string|max:5000']);

        $userOnboarding->notes()->create([
            'admin_id' => Auth::guard('admin')->id(),
            'note' => $validated['note'],
        ]);

        return redirect()->route('admin.user-onboardings.show', $userOnboarding)
            ->with('success', 'Note added.');
    }

    public function destroy(UserOnboarding $userOnboarding, OnboardingNote $note): RedirectResponse
    {
        if ((int) $note->user_onboarding_id !== (int) $userOnboarding->id) {
            abort(404);
        }

        // Notes are an internal record — only the author may remove one.
        if ((int) $note->admin_id !== (int) Auth::guard('admin')->id()) {
            abort(403, 'Only the author can delete a note.');
        }

        $note->delete();

        return redirect()->route('admin.user-onboardings.show', $userOnboarding)
            ->with('success', 'Note deleted.');
    }
}
