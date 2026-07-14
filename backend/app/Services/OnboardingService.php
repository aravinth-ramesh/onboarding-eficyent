<?php

namespace App\Services;

use App\Mail\OnboardingSubmittedAdminMail;
use App\Mail\OnboardingSubmittedClientMail;
use App\Models\Admin;
use App\Models\OnboardingStep;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Models\UserOnboardingStep;
use Illuminate\Support\Facades\Mail;

class OnboardingService
{
    /**
     * Initialize onboarding for a user by copying master template steps.
     */
    public function initializeForUser(User $user): UserOnboarding
    {
        $onboarding = UserOnboarding::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'template_version' => $this->getCurrentTemplateVersion(),
            'started_at' => now(),
        ]);

        $masterSteps = OnboardingStep::where('is_active', true)
            ->orderBy('order')
            ->get();

        foreach ($masterSteps as $step) {
            UserOnboardingStep::create([
                'user_onboarding_id' => $onboarding->id,
                'onboarding_step_id' => $step->id,
                'name' => $step->name,
                'component_key' => $step->component_key,
                'order' => $step->order,
                'status' => 'pending',
                'config' => $step->config,
            ]);
        }

        // Set current step to first step
        $firstStep = $onboarding->steps()->orderBy('order')->first();
        if ($firstStep) {
            $onboarding->update(['current_step_id' => $firstStep->id]);
        }

        return $onboarding->load('steps');
    }

    /**
     * Set user type and optionally subcategory.
     */
    public function setUserType(UserOnboarding $onboarding, int $userTypeId, ?int $subcategoryId = null): UserOnboarding
    {
        $onboarding->update([
            'user_type_id' => $userTypeId,
            'user_type_subcategory_id' => $subcategoryId,
            'status' => 'in_progress',
        ]);

        return $onboarding->fresh();
    }

    /**
     * Complete a step and advance to the next one.
     */
    public function completeStep(UserOnboarding $onboarding, UserOnboardingStep $step): UserOnboarding
    {
        $step->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Find next pending step (skip over skipped steps)
        $nextStep = $onboarding->steps()
            ->where('order', '>', $step->order)
            ->whereNotIn('status', ['completed', 'skipped'])
            ->orderBy('order')
            ->first();

        if ($nextStep) {
            $nextStep->update([
                'status' => 'in_progress',
                'started_at' => now(),
            ]);
            $onboarding->update(['current_step_id' => $nextStep->id]);
        } else {
            // All steps completed
            $onboarding->update([
                'status' => 'completed',
                'completed_at' => now(),
                'current_step_id' => null,
            ]);

            $this->notifySubmission($onboarding->fresh());
        }

        return $onboarding->fresh('steps');
    }

    /**
     * Submission emails: confirmation to the client, heads-up to every
     * active admin. Queued, and a mail hiccup must never undo a submission —
     * hence the blanket catch.
     */
    private function notifySubmission(UserOnboarding $onboarding): void
    {
        $onboarding->load(['user', 'userType', 'subcategory']);

        try {
            if ($onboarding->user?->email) {
                Mail::to($onboarding->user->email)->queue(new OnboardingSubmittedClientMail($onboarding));
            }

            $adminEmails = Admin::where('is_active', true)->pluck('email');
            foreach ($adminEmails as $email) {
                Mail::to($email)->queue(new OnboardingSubmittedAdminMail($onboarding));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Go back to the previous step.
     */
    public function goToPreviousStep(UserOnboarding $onboarding, UserOnboardingStep $currentStep): UserOnboarding
    {
        $previousStep = $onboarding->steps()
            ->where('order', '<', $currentStep->order)
            ->where('status', '!=', 'skipped')
            ->reorder()
            ->orderByDesc('order')
            ->first();

        if (!$previousStep) {
            return $onboarding->fresh('steps');
        }

        // Reset current step back to pending
        $currentStep->update([
            'status' => 'pending',
            'started_at' => null,
        ]);

        // Re-open the previous step
        $previousStep->update([
            'status' => 'in_progress',
            'completed_at' => null,
        ]);

        $onboarding->update(['current_step_id' => $previousStep->id]);

        return $onboarding->fresh('steps');
    }

    /**
     * Jump directly to an earlier step (e.g. from the sidebar tracker).
     *
     * Only allows navigating to a step at or before the current one. Every
     * non-skipped step after the target is demoted back to pending so the
     * user re-advances through them — this keeps later steps consistent when
     * an earlier answer (and its conditional logic) may have changed.
     */
    public function goToStep(UserOnboarding $onboarding, UserOnboardingStep $targetStep): UserOnboarding
    {
        $current = $onboarding->steps()
            ->where('id', $onboarding->current_step_id)
            ->first();

        // Never allow skipping forward past the current step.
        if ($current && $targetStep->order > $current->order) {
            return $onboarding->fresh('steps');
        }

        // Demote everything after the target back to pending.
        $onboarding->steps()
            ->where('order', '>', $targetStep->order)
            ->where('status', '!=', 'skipped')
            ->update(['status' => 'pending', 'started_at' => null, 'completed_at' => null]);

        // Re-open the target step.
        $targetStep->update([
            'status' => 'in_progress',
            'completed_at' => null,
            'started_at' => $targetStep->started_at ?? now(),
        ]);

        $onboarding->update([
            'status' => 'in_progress',
            'completed_at' => null,
            'current_step_id' => $targetStep->id,
        ]);

        return $onboarding->fresh('steps');
    }

    private function getCurrentTemplateVersion(): int
    {
        return OnboardingStep::max('version') ?? 1;
    }
}
