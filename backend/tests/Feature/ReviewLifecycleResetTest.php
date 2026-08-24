<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\AnswerFile;
use App\Models\OnboardingSectionReview;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use App\Models\UserType;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Review-lifecycle resets (bug report EOP-79 / EOP-74 / EOP-71).
 */
class ReviewLifecycleResetTest extends TestCase
{
    use RefreshDatabase;

    private UserType $type;

    private QuestionGroup $group;

    private Question $textQ;

    private Question $fileQ;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->type = UserType::create(['name' => 'Corp', 'slug' => 'corp', 'order' => 1, 'is_active' => true]);
        $this->group = QuestionGroup::create(['name' => 'Company', 'slug' => 'company', 'order' => 1, 'is_active' => true]);
        $this->textQ = Question::create(['question_group_id' => $this->group->id, 'label' => 'Legal name', 'type' => 'text', 'is_required' => true, 'order' => 1, 'is_active' => true]);
        $this->fileQ = Question::create(['question_group_id' => $this->group->id, 'label' => 'Certificate', 'type' => 'file', 'is_required' => true, 'order' => 2, 'is_active' => true]);
        $this->user = User::create(['email' => 'c@t.com', 'name' => 'Co', 'position' => 'CFO']);
    }

    private function onboarding(string $status): UserOnboarding
    {
        return UserOnboarding::create(['user_id' => $this->user->id, 'user_type_id' => $this->type->id, 'status' => $status, 'started_at' => now(), 'completed_at' => now()]);
    }

    public function test_reopening_resets_section_reviews_and_document_verdicts(): void
    {
        $onb = $this->onboarding('rejected');
        $textAns = UserAnswer::create(['user_id' => $this->user->id, 'question_id' => $this->textQ->id, 'user_onboarding_id' => $onb->id, 'value' => 'Acme']);
        $fileAns = UserAnswer::create(['user_id' => $this->user->id, 'question_id' => $this->fileQ->id, 'user_onboarding_id' => $onb->id, 'value' => 'cert.pdf']);
        $file = AnswerFile::create(['user_answer_id' => $fileAns->id, 'original_filename' => 'cert.pdf', 's3_path' => 'u/c.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100, 'disk' => 'local', 'validation_status' => 'passed', 'review_decision' => 'verified', 'reviewed_at' => now(), 'reviewed_by' => null]);
        OnboardingSectionReview::create(['user_onboarding_id' => $onb->id, 'question_group_id' => $this->group->id, 'status' => 'completed', 'reviewed_at' => now()]);

        app(OnboardingService::class)->reopen($onb);

        $this->assertSame(0, OnboardingSectionReview::where('user_onboarding_id', $onb->id)->count(), 'section reviews reset');
        $file->refresh();
        $this->assertNull($file->review_decision, 'document verdict reset');
        $this->assertNull($file->reviewed_at);
    }

    public function test_a_section_with_a_pending_change_request_cannot_be_marked_reviewed(): void
    {
        $onb = $this->onboarding('completed');
        $answer = UserAnswer::create(['user_id' => $this->user->id, 'question_id' => $this->textQ->id, 'user_onboarding_id' => $onb->id, 'value' => 'Acme']);
        $admin = Admin::create(['name' => 'M', 'email' => 'm@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Manager]);
        AdminNotification::create(['user_id' => $this->user->id, 'admin_id' => $admin->id, 'type' => 'change_request', 'user_answer_id' => $answer->id, 'message' => 'fix', 'status' => 'pending']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.user-onboardings.sections.review', [$onb, $this->group]), ['status' => 'completed'])
            ->assertSessionHas('error');

        $review = OnboardingSectionReview::where('user_onboarding_id', $onb->id)->first();
        $this->assertTrue($review === null || $review->status !== 'completed', 'section must not be marked reviewed while a change is pending');
    }

    public function test_a_section_without_pending_changes_can_still_be_reviewed(): void
    {
        $onb = $this->onboarding('completed');
        UserAnswer::create(['user_id' => $this->user->id, 'question_id' => $this->textQ->id, 'user_onboarding_id' => $onb->id, 'value' => 'Acme']);
        $admin = Admin::create(['name' => 'M', 'email' => 'm@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Manager]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.user-onboardings.sections.review', [$onb, $this->group]), ['status' => 'completed'])
            ->assertSessionHas('success');

        $this->assertSame('completed', OnboardingSectionReview::where('user_onboarding_id', $onb->id)->first()->status);
    }

    public function test_a_reviewed_section_cannot_be_walked_back(): void
    {
        // Sign-off is one-way; reverting would silently decrement the approval
        // progress gate (EOP-74).
        $onb = $this->onboarding('completed');
        UserAnswer::create(['user_id' => $this->user->id, 'question_id' => $this->textQ->id, 'user_onboarding_id' => $onb->id, 'value' => 'Acme']);
        $admin = Admin::create(['name' => 'M', 'email' => 'm@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Manager]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.user-onboardings.sections.review', [$onb, $this->group]), ['status' => 'completed'])
            ->assertSessionHas('success');

        foreach (['in_progress', 'pending'] as $regression) {
            $this->actingAs($admin, 'admin')
                ->post(route('admin.user-onboardings.sections.review', [$onb, $this->group]), ['status' => $regression])
                ->assertSessionHas('error');
        }

        $this->assertSame('completed', OnboardingSectionReview::where('user_onboarding_id', $onb->id)->first()->status);
    }

    public function test_requesting_a_change_reopens_an_already_reviewed_section(): void
    {
        // Otherwise the completed marker (and the approval gate) survives a
        // change the client still has to make (EOP-71, ordering gap).
        $onb = $this->onboarding('completed');
        $answer = UserAnswer::create(['user_id' => $this->user->id, 'question_id' => $this->textQ->id, 'user_onboarding_id' => $onb->id, 'value' => 'Acme']);
        $admin = Admin::create(['name' => 'M', 'email' => 'm@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Manager]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.user-onboardings.sections.review', [$onb, $this->group]), ['status' => 'completed'])
            ->assertSessionHas('success');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.user-onboardings.answers.request-change', [$onb, $answer]), ['message' => 'Please correct this.'])
            ->assertRedirect();

        $review = OnboardingSectionReview::where('user_onboarding_id', $onb->id)->first();
        $this->assertSame('in_progress', $review->status, 'the section must reopen when a change is requested');
        $this->assertNull($review->reviewed_at);
        $this->assertFalse($onb->fresh()->sectionReviewProgress()['complete'], 'the approval gate must close again');
    }
}
