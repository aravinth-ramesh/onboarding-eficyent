<?php

namespace Tests\Feature;

use App\Models\OnboardingStep;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Models\UserOnboardingStep;
use App\Models\UserType;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Editing one section from the Final Review page returns straight to Review
 * instead of demoting every later step and forcing a walk back through the
 * whole form (bug report EOP-52).
 */
class EditFromReviewTest extends TestCase
{
    use RefreshDatabase;

    private UserOnboarding $onboarding;

    /** @var array<int, UserOnboardingStep> */
    private array $steps = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $type = UserType::create(['name' => 'Corp', 'slug' => 'corp', 'order' => 1, 'is_active' => true]);
        $user = User::create(['email' => 'c@t.com', 'name' => 'Co', 'position' => 'CFO']);
        $this->onboarding = UserOnboarding::create([
            'user_id' => $user->id, 'user_type_id' => $type->id,
            'status' => 'in_progress', 'started_at' => now(),
        ]);

        // Four steps, all completed, sitting on the final Review step.
        foreach ([['One', 'questions'], ['Two', 'questions'], ['Three', 'questions'], ['Review', 'review']] as $i => [$name, $key]) {
            $master = OnboardingStep::create(['name' => $name, 'slug' => strtolower($name).'-s', 'component_key' => $key, 'order' => $i + 1, 'is_active' => true]);
            $this->steps[] = UserOnboardingStep::create([
                'user_onboarding_id' => $this->onboarding->id,
                'onboarding_step_id' => $master->id,
                'name' => $name, 'component_key' => $key,
                'order' => $i + 1, 'status' => 'completed',
            ]);
        }

        $this->onboarding->update(['current_step_id' => $this->reviewStep()->id]);
    }

    private function reviewStep(): UserOnboardingStep
    {
        return $this->steps[3];
    }

    public function test_editing_from_review_keeps_later_steps_completed(): void
    {
        $service = app(OnboardingService::class);

        $service->goToStep($this->onboarding, $this->steps[1], $this->reviewStep());

        $this->assertSame('in_progress', $this->steps[1]->fresh()->status);
        // The whole point: step Three is untouched, so it needn't be re-walked.
        $this->assertSame('completed', $this->steps[2]->fresh()->status);
        $this->assertSame($this->reviewStep()->id, $this->onboarding->fresh()->return_to_step_id);
    }

    public function test_completing_the_edited_step_returns_to_review(): void
    {
        $service = app(OnboardingService::class);
        $service->goToStep($this->onboarding, $this->steps[1], $this->reviewStep());

        $service->completeStep($this->onboarding->fresh(), $this->steps[1]->fresh());

        $onboarding = $this->onboarding->fresh();
        $this->assertSame($this->reviewStep()->id, $onboarding->current_step_id, 'must land back on Review, not the next step');
        $this->assertSame('in_progress', $this->reviewStep()->fresh()->status);
        $this->assertNull($onboarding->return_to_step_id, 'the return marker is one-shot');
        // Critically: the later steps were already complete, so the naive path
        // would have treated this as "all steps done" and submitted.
        $this->assertSame('in_progress', $onboarding->status);
        $this->assertNull($onboarding->completed_at);
    }

    public function test_plain_back_navigation_still_demotes_later_steps(): void
    {
        // Without a return target a change may invalidate what follows, so the
        // existing safety behaviour must be untouched.
        $service = app(OnboardingService::class);

        $service->goToStep($this->onboarding, $this->steps[1]);

        $this->assertSame('pending', $this->steps[2]->fresh()->status);
        $this->assertSame('pending', $this->reviewStep()->fresh()->status);
        $this->assertNull($this->onboarding->fresh()->return_to_step_id);
    }

    public function test_the_endpoint_accepts_a_return_target(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->onboarding->user);

        $this->postJson("/api/onboarding/steps/{$this->steps[1]->id}/goto", [
            'return_to' => $this->reviewStep()->id,
        ])->assertOk();

        $this->assertSame('completed', $this->steps[2]->fresh()->status);
        $this->assertSame($this->reviewStep()->id, $this->onboarding->fresh()->return_to_step_id);
    }

    public function test_a_return_target_from_another_application_is_ignored(): void
    {
        $type = UserType::create(['name' => 'Other', 'slug' => 'other', 'order' => 2, 'is_active' => true]);
        $stranger = User::create(['email' => 'x@t.com', 'name' => 'X', 'position' => 'CFO']);
        $otherOnboarding = UserOnboarding::create(['user_id' => $stranger->id, 'user_type_id' => $type->id, 'status' => 'in_progress', 'started_at' => now()]);
        $master = OnboardingStep::create(['name' => 'Foreign', 'slug' => 'foreign', 'component_key' => 'review', 'order' => 1, 'is_active' => true]);
        $foreignStep = UserOnboardingStep::create([
            'user_onboarding_id' => $otherOnboarding->id, 'onboarding_step_id' => $master->id,
            'name' => 'Foreign', 'component_key' => 'review', 'order' => 1, 'status' => 'completed',
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->onboarding->user);
        $this->postJson("/api/onboarding/steps/{$this->steps[1]->id}/goto", [
            'return_to' => $foreignStep->id,
        ])->assertOk();

        $this->assertNull($this->onboarding->fresh()->return_to_step_id);
        // Falls back to the safe path.
        $this->assertSame('pending', $this->steps[2]->fresh()->status);
    }
}
