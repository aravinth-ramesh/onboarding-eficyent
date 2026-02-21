<?php

namespace App\Services;

use App\Models\OnboardingStep;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Models\UserOnboardingStep;

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

        // Find next pending step
        $nextStep = $onboarding->steps()
            ->where('order', '>', $step->order)
            ->where('status', '!=', 'completed')
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
        }

        return $onboarding->fresh('steps');
    }

    private function getCurrentTemplateVersion(): int
    {
        return OnboardingStep::max('version') ?? 1;
    }
}
