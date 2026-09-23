<?php

namespace Tests\Feature;

use App\Models\OnboardingStep;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\QuestionTypeMapping;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Models\UserOnboardingStep;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Disabling a step correctly stops the client being asked its questions, but
 * its question groups still reached the client, so the review and
 * submitted-answers screens rendered a heading for each with nothing under it
 * (report item 6).
 */
class DisabledStepSectionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UserOnboarding $onboarding;

    private UserOnboardingStep $optionalStep;

    protected function setUp(): void
    {
        parent::setUp();

        $type = UserType::create(['name' => 'Corporate', 'slug' => 'corp', 'order' => 1, 'is_active' => true]);

        foreach ([['company-information', 'Company Information'], ['extras', 'Extras']] as [$slug, $name]) {
            $group = QuestionGroup::create(['name' => $name, 'slug' => $slug, 'order' => 1, 'is_active' => true]);
            $question = Question::create([
                'question_group_id' => $group->id, 'label' => $name.' question', 'type' => 'text',
                'is_required' => false, 'order' => 1, 'is_active' => true,
            ]);
            QuestionTypeMapping::create([
                'question_id' => $question->id, 'user_type_id' => $type->id,
                'is_required' => null, 'order' => 1, 'is_active' => true,
            ]);
        }

        $this->user = User::create(['email' => 'client@test.com', 'name' => 'Client', 'position' => 'CFO']);
        $this->onboarding = UserOnboarding::create([
            'user_id' => $this->user->id, 'reference' => 'REF-DS', 'status' => 'in_progress',
            'user_type_id' => $type->id, 'started_at' => now(),
        ]);

        foreach ([['Basic', ['company-information'], 1], ['Optional', ['extras'], 2]] as [$name, $groups, $order]) {
            $master = OnboardingStep::create([
                'name' => $name, 'slug' => strtolower($name), 'component_key' => 'questions',
                'order' => $order, 'is_active' => true, 'config' => ['groups' => $groups],
            ]);
            $step = UserOnboardingStep::create([
                'user_onboarding_id' => $this->onboarding->id, 'onboarding_step_id' => $master->id,
                'name' => $name, 'component_key' => 'questions', 'order' => $order,
                'status' => 'in_progress', 'config' => ['groups' => $groups],
            ]);

            if ($name === 'Optional') {
                $this->optionalStep = $step;
            }
        }
    }

    private function groupSlugs(): array
    {
        Sanctum::actingAs($this->user);

        return collect($this->getJson('/api/onboarding/questions')->assertOk()->json('data'))
            ->pluck('slug')->all();
    }

    public function test_both_sections_are_offered_while_every_step_is_enabled(): void
    {
        $this->assertEqualsCanonicalizing(['company-information', 'extras'], $this->groupSlugs());
    }

    public function test_a_disabled_step_takes_its_section_with_it(): void
    {
        $this->optionalStep->update(['status' => 'skipped']);

        $this->assertSame(['company-information'], $this->groupSlugs());
    }

    public function test_re_enabling_the_step_brings_the_section_back(): void
    {
        $this->optionalStep->update(['status' => 'skipped']);
        $this->assertSame(['company-information'], $this->groupSlugs());

        $this->optionalStep->update(['status' => 'pending']);

        $this->assertEqualsCanonicalizing(['company-information', 'extras'], $this->groupSlugs());
    }

    public function test_a_step_with_no_group_config_disables_nothing(): void
    {
        // A step that renders a component rather than question groups must not
        // silently remove anything when it is disabled.
        $this->optionalStep->update(['status' => 'skipped', 'config' => null]);

        $this->assertEqualsCanonicalizing(['company-information', 'extras'], $this->groupSlugs());
    }
}
