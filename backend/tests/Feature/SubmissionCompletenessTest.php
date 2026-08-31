<?php

namespace Tests\Feature;

use App\Models\ConditionalRule;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\QuestionTypeMapping;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use App\Models\OnboardingStep;
use App\Models\UserOnboardingStep;
use App\Models\UserType;
use App\Services\SubmissionCompleteness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Conditional visibility was enforced only in the browser, so an application
 * could reach review with a required question unanswered — a client simply had
 * to not ask it. Checked at submission, not on every save, so a half-filled
 * draft is never blocked.
 */
class SubmissionCompletenessTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UserOnboarding $onboarding;

    private UserType $type;

    private QuestionGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = UserType::create(['name' => 'Corporate', 'slug' => 'corp', 'order' => 1, 'is_active' => true]);
        $this->group = QuestionGroup::create(['name' => 'G', 'slug' => 'g', 'order' => 1, 'is_active' => true]);

        $this->user = User::create(['email' => 'client@test.com', 'name' => 'Client', 'position' => 'CFO']);
        $this->onboarding = UserOnboarding::create([
            'user_id' => $this->user->id, 'reference' => 'REF-S', 'status' => 'in_progress',
            'user_type_id' => $this->type->id, 'country_code' => 'IN', 'started_at' => now(),
        ]);
    }

    private function question(string $label, bool $required = true): Question
    {
        $q = Question::create([
            'question_group_id' => $this->group->id, 'label' => $label, 'type' => 'text',
            'is_required' => $required, 'order' => 1, 'is_active' => true,
        ]);
        QuestionTypeMapping::create([
            'question_id' => $q->id, 'user_type_id' => $this->type->id,
            'is_required' => $required, 'order' => 1, 'is_active' => true,
        ]);

        return $q;
    }

    private function answer(Question $q, string $value): void
    {
        UserAnswer::create([
            'user_onboarding_id' => $this->onboarding->id, 'user_id' => $this->user->id,
            'question_id' => $q->id, 'value' => $value,
        ]);
    }

    private function step(string $name, int $order, string $status): UserOnboardingStep
    {
        $master = OnboardingStep::firstOrCreate(
            ['slug' => 'step-'.$order],
            ['name' => $name, 'component_key' => 'questions', 'order' => $order, 'is_active' => true],
        );

        return UserOnboardingStep::create([
            'user_onboarding_id' => $this->onboarding->id,
            'onboarding_step_id' => $master->id,
            'name' => $name, 'component_key' => 'questions',
            'order' => $order, 'status' => $status,
        ]);
    }

    private function check(): SubmissionCompleteness
    {
        return app(SubmissionCompleteness::class);
    }

    public function test_an_unconditional_required_question_must_be_answered(): void
    {
        $this->question('Legal entity name');

        $this->assertSame(['Legal entity name'], $this->check()->missingRequired($this->onboarding));
    }

    public function test_answering_it_clears_the_complaint(): void
    {
        $q = $this->question('Legal entity name');
        $this->answer($q, 'Acme Ltd');

        $this->assertSame([], $this->check()->missingRequired($this->onboarding));
    }

    public function test_whitespace_is_not_an_answer(): void
    {
        $q = $this->question('Legal entity name');
        $this->answer($q, '   ');

        $this->assertSame(['Legal entity name'], $this->check()->missingRequired($this->onboarding));
    }

    public function test_an_empty_table_is_not_an_answer(): void
    {
        $q = $this->question('Beneficial owners');
        $this->answer($q, json_encode([['full_name' => '', 'stake' => '']]));

        $this->assertSame(['Beneficial owners'], $this->check()->missingRequired($this->onboarding));
    }

    public function test_a_hidden_required_question_is_not_demanded(): void
    {
        // The rule shows GSTIN only for India; this application is Singapore.
        $this->onboarding->update(['country_code' => 'SG']);
        $gstin = $this->question('GSTIN');
        ConditionalRule::create([
            'question_id' => $gstin->id, 'parent_question_id' => null, 'parent_field' => 'country_code',
            'comparison_type' => 'equals', 'trigger_value' => 'IN', 'action' => 'show',
            'logical_operator' => 'and', 'is_active' => true,
        ]);

        $this->assertSame([], $this->check()->missingRequired($this->onboarding));
    }

    public function test_a_visible_required_question_is_demanded(): void
    {
        // Same rule, but the application is Indian, so the question applies.
        $gstin = $this->question('GSTIN');
        ConditionalRule::create([
            'question_id' => $gstin->id, 'parent_question_id' => null, 'parent_field' => 'country_code',
            'comparison_type' => 'equals', 'trigger_value' => 'IN', 'action' => 'show',
            'logical_operator' => 'and', 'is_active' => true,
        ]);

        $this->assertSame(['GSTIN'], $this->check()->missingRequired($this->onboarding));
    }

    public function test_an_optional_question_is_never_demanded(): void
    {
        $this->question('Trading name', required: false);

        $this->assertSame([], $this->check()->missingRequired($this->onboarding));
    }

    public function test_an_answer_left_on_a_now_hidden_question_is_not_an_error(): void
    {
        // Answering then making it irrelevant is not a mistake worth blocking.
        $this->onboarding->update(['country_code' => 'SG']);
        $gstin = $this->question('GSTIN');
        ConditionalRule::create([
            'question_id' => $gstin->id, 'parent_question_id' => null, 'parent_field' => 'country_code',
            'comparison_type' => 'equals', 'trigger_value' => 'IN', 'action' => 'show',
            'logical_operator' => 'and', 'is_active' => true,
        ]);
        $this->answer($gstin, '27AAPFU0939F1ZV');

        $this->assertSame([], $this->check()->missingRequired($this->onboarding));
    }

    public function test_submitting_an_incomplete_application_is_refused(): void
    {
        $this->question('Legal entity name');
        $step = $this->step('Review', 1, 'in_progress');

        Sanctum::actingAs($this->user);
        $this->postJson("/api/onboarding/steps/{$step->id}/complete")
            ->assertStatus(422)
            ->assertJsonPath('code', 'incomplete_application')
            ->assertJsonPath('missing.0', 'Legal entity name');

        $this->assertNotSame('completed', $this->onboarding->fresh()->status);
    }

    public function test_a_complete_application_submits(): void
    {
        $q = $this->question('Legal entity name');
        $this->answer($q, 'Acme Ltd');
        $step = $this->step('Review', 1, 'in_progress');

        Sanctum::actingAs($this->user);
        $this->postJson("/api/onboarding/steps/{$step->id}/complete")->assertOk();

        $this->assertSame('completed', $this->onboarding->fresh()->status);
    }

    public function test_an_earlier_step_still_completes_with_the_form_half_filled(): void
    {
        // The check is at submission only — a draft must stay free to move on.
        $this->question('Legal entity name');
        $first = $this->step('Basic', 1, 'in_progress');
        $this->step('Review', 2, 'pending');

        Sanctum::actingAs($this->user);
        $this->postJson("/api/onboarding/steps/{$first->id}/complete")->assertOk();

        $this->assertSame('completed', $first->fresh()->status);
        $this->assertNotSame('completed', $this->onboarding->fresh()->status);
    }
}
