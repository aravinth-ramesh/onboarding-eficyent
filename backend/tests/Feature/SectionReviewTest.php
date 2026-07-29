<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\AnswerFile;
use App\Models\OnboardingSectionReview;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 — sectioned review: per-section progress that survives across days,
 * per-document verdicts, and a Documents view listing every uploaded file.
 */
class SectionReviewTest extends TestCase
{
    use RefreshDatabase;

    private UserType $type;
    private UserOnboarding $onboarding;
    private QuestionGroup $companyGroup;
    private QuestionGroup $docsGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = UserType::create(['name' => 'Corporate', 'slug' => 'corporate', 'order' => 1, 'is_active' => true]);
        $user = User::create(['email' => 'client@test.com', 'name' => 'Acme Ltd', 'position' => 'CFO']);
        $this->onboarding = UserOnboarding::create([
            'user_id' => $user->id, 'user_type_id' => $this->type->id,
            'status' => 'completed', 'started_at' => now(),
        ]);

        // Two sections: a text section and a documents section.
        $this->companyGroup = QuestionGroup::create(['name' => 'Company Details', 'slug' => 'company', 'order' => 1, 'is_active' => true]);
        $this->docsGroup = QuestionGroup::create(['name' => 'Documents', 'slug' => 'docs', 'order' => 2, 'is_active' => true]);

        $companyQ = Question::create(['question_group_id' => $this->companyGroup->id, 'label' => 'Legal name', 'type' => 'text', 'is_required' => true, 'order' => 1, 'is_active' => true]);
        UserAnswer::create(['user_id' => $user->id, 'question_id' => $companyQ->id, 'user_onboarding_id' => $this->onboarding->id, 'value' => 'Acme Ltd']);

        $docQ = Question::create(['question_group_id' => $this->docsGroup->id, 'label' => 'Certificate of Incorporation', 'type' => 'file', 'is_required' => true, 'order' => 1, 'is_active' => true]);
        $docAnswer = UserAnswer::create(['user_id' => $user->id, 'question_id' => $docQ->id, 'user_onboarding_id' => $this->onboarding->id, 'value' => 'certificate.pdf']);
        AnswerFile::create([
            'user_answer_id' => $docAnswer->id, 'original_filename' => 'certificate.pdf',
            's3_path' => 'uploads/certificate.pdf', 'mime_type' => 'application/pdf',
            'file_size' => 2048, 'disk' => 'local', 'validation_status' => 'passed',
        ]);
    }

    private function admin(AdminRole $role, ?int $assignTo = null): Admin
    {
        $admin = Admin::create(['name' => ucfirst($role->value), 'email' => $role->value . '@test.com', 'password' => 'x', 'is_active' => true, 'role' => $role]);

        return $admin;
    }

    // --- section progress ----------------------------------------------------

    public function test_marking_a_section_reviewed_records_a_completed_marker(): void
    {
        $manager = $this->admin(AdminRole::Manager);

        $this->actingAs($manager, 'admin')
            ->post(route('admin.user-onboardings.sections.review', [$this->onboarding, $this->companyGroup]), [
                'status' => 'completed', 'note' => 'Names match the register.',
            ])
            ->assertRedirect();

        $review = OnboardingSectionReview::firstOrFail();
        $this->assertSame('completed', $review->status);
        $this->assertSame($manager->id, $review->reviewed_by);
        $this->assertNotNull($review->reviewed_at);
        $this->assertSame('Names match the register.', $review->note);
    }

    public function test_in_progress_marker_leaves_reviewed_at_null_and_updates_in_place(): void
    {
        $manager = $this->admin(AdminRole::Manager);

        $this->actingAs($manager, 'admin')
            ->post(route('admin.user-onboardings.sections.review', [$this->onboarding, $this->companyGroup]), ['status' => 'in_progress'])
            ->assertRedirect();
        // Second save on the same section updates the one row, not a new one.
        $this->actingAs($manager, 'admin')
            ->post(route('admin.user-onboardings.sections.review', [$this->onboarding, $this->companyGroup]), ['status' => 'in_progress']);

        $this->assertSame(1, OnboardingSectionReview::count());
        $review = OnboardingSectionReview::firstOrFail();
        $this->assertSame('in_progress', $review->status);
        $this->assertNull($review->reviewed_at);
    }

    public function test_a_section_not_in_this_application_cannot_be_marked(): void
    {
        $manager = $this->admin(AdminRole::Manager);
        $orphan = QuestionGroup::create(['name' => 'Elsewhere', 'slug' => 'elsewhere', 'order' => 9, 'is_active' => true]);

        $this->actingAs($manager, 'admin')
            ->post(route('admin.user-onboardings.sections.review', [$this->onboarding, $orphan]), ['status' => 'completed'])
            ->assertNotFound();

        $this->assertSame(0, OnboardingSectionReview::count());
    }

    public function test_an_analyst_cannot_review_a_company_not_assigned_to_them(): void
    {
        $analyst = $this->admin(AdminRole::Analyst); // onboarding is unassigned

        $this->actingAs($analyst, 'admin')
            ->post(route('admin.user-onboardings.sections.review', [$this->onboarding, $this->companyGroup]), ['status' => 'completed'])
            ->assertForbidden();
    }

    public function test_progress_is_complete_only_when_every_section_is_reviewed(): void
    {
        $progress = $this->onboarding->fresh()->load('sectionReviews', 'answers.question.group')->sectionReviewProgress();
        $this->assertSame(['done' => 0, 'total' => 2, 'complete' => false], $progress);

        OnboardingSectionReview::create(['user_onboarding_id' => $this->onboarding->id, 'question_group_id' => $this->companyGroup->id, 'status' => 'completed']);
        OnboardingSectionReview::create(['user_onboarding_id' => $this->onboarding->id, 'question_group_id' => $this->docsGroup->id, 'status' => 'completed']);

        $progress = $this->onboarding->fresh()->load('sectionReviews', 'answers.question.group')->sectionReviewProgress();
        $this->assertSame(['done' => 2, 'total' => 2, 'complete' => true], $progress);
    }

    public function test_show_page_renders_the_review_progress_bar(): void
    {
        $manager = $this->admin(AdminRole::Manager);

        $this->actingAs($manager, 'admin')
            ->get(route('admin.user-onboardings.show', $this->onboarding))
            ->assertOk()
            ->assertSee('Review progress')
            ->assertSee('0 of 2 sections reviewed');
    }

    // --- per-document verdicts ----------------------------------------------

    public function test_verifying_a_document_records_the_decision(): void
    {
        $manager = $this->admin(AdminRole::Manager);
        $file = AnswerFile::firstOrFail();

        $this->actingAs($manager, 'admin')
            ->post(route('admin.user-onboardings.documents.review', [$this->onboarding, $file]), [
                'review_decision' => 'verified', 'review_note' => 'Clear scan.',
            ])
            ->assertRedirect();

        $file->refresh();
        $this->assertSame('verified', $file->review_decision);
        $this->assertSame('Clear scan.', $file->review_note);
        $this->assertSame($manager->id, $file->reviewed_by);
        $this->assertNotNull($file->reviewed_at);
    }

    public function test_a_document_from_another_application_cannot_be_reviewed_here(): void
    {
        $manager = $this->admin(AdminRole::Manager);
        $file = AnswerFile::firstOrFail();

        // A second, unrelated onboarding.
        $other = UserOnboarding::create([
            'user_id' => User::create(['email' => 'x@test.com', 'name' => 'X', 'position' => 'CFO'])->id,
            'user_type_id' => $this->type->id, 'status' => 'completed', 'started_at' => now(),
        ]);

        $this->actingAs($manager, 'admin')
            ->post(route('admin.user-onboardings.documents.review', [$other, $file]), ['review_decision' => 'verified'])
            ->assertNotFound();
    }

    public function test_documents_card_lists_every_uploaded_file(): void
    {
        $manager = $this->admin(AdminRole::Manager);

        $this->actingAs($manager, 'admin')
            ->get(route('admin.user-onboardings.show', $this->onboarding))
            ->assertOk()
            ->assertSee('id="documents"', false)
            ->assertSee('certificate.pdf')
            ->assertSee('0/1 verified');
    }

    public function test_documents_card_surfaces_files_from_admin_follow_up_questions(): void
    {
        $manager = $this->admin(AdminRole::Manager);
        $user = $this->onboarding->user;

        // An admin asks a follow-up file question; the client answers with an upload.
        $aq = \App\Models\AdminQuestion::create([
            'user_id' => $user->id, 'admin_id' => $manager->id,
            'label' => 'Latest bank statement', 'type' => 'file', 'is_required' => true,
        ]);
        $ans = \App\Models\AdminQuestionAnswer::create(['admin_question_id' => $aq->id, 'user_id' => $user->id, 'value' => 'statement.pdf']);
        \App\Models\AdminQuestionAnswerFile::create([
            'admin_question_answer_id' => $ans->id, 'original_filename' => 'bank-statement-june.pdf',
            's3_path' => 'u/bank.pdf', 'mime_type' => 'application/pdf', 'file_size' => 4096, 'disk' => 'local',
        ]);

        $this->actingAs($manager, 'admin')
            ->get(route('admin.user-onboardings.show', $this->onboarding))
            ->assertOk()
            ->assertSee('Other uploads')
            ->assertSee('bank-statement-june.pdf')
            ->assertSee('Latest bank statement');
    }

    // --- global document queue "all" toggle ----------------------------------

    public function test_document_queue_all_toggle_includes_passed_documents(): void
    {
        $compliance = $this->admin(AdminRole::Compliance);

        // Default queue hides passed docs...
        $this->actingAs($compliance, 'admin')
            ->get(route('admin.document-reviews.index'))
            ->assertOk()
            ->assertDontSee('certificate.pdf');

        // ...but "All documents" surfaces them.
        $this->actingAs($compliance, 'admin')
            ->get(route('admin.document-reviews.index', ['all' => 1]))
            ->assertOk()
            ->assertSee('certificate.pdf');
    }
}
