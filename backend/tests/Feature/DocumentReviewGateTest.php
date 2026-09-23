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
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Approval gated on section sign-off alone, so an application could be approved
 * with every uploaded document unreviewed — the evidence a KYB decision rests
 * on counted for nothing, and the progress figure said complete (item 7).
 * Refusals now name the rule that stopped them rather than falling back to a
 * generic message (item 4).
 */
class DocumentReviewGateTest extends TestCase
{
    use RefreshDatabase;

    private UserOnboarding $onboarding;

    private Admin $reviewer;

    private AnswerFile $file;

    protected function setUp(): void
    {
        parent::setUp();

        $group = QuestionGroup::create(['name' => 'Docs', 'slug' => 'docs', 'order' => 1, 'is_active' => true]);
        $question = Question::create([
            'question_group_id' => $group->id, 'label' => 'Certificate', 'type' => 'file',
            'is_required' => false, 'order' => 1, 'is_active' => true,
        ]);

        $user = User::create(['email' => 'client@test.com', 'name' => 'Client', 'position' => 'CFO']);
        $this->onboarding = UserOnboarding::create([
            'user_id' => $user->id, 'reference' => 'REF-DG', 'status' => 'completed',
            'started_at' => now(), 'completed_at' => now(),
        ]);

        $answer = UserAnswer::create([
            'user_onboarding_id' => $this->onboarding->id, 'user_id' => $user->id,
            'question_id' => $question->id, 'value' => json_encode(['uploads/cert.pdf']),
        ]);

        $this->file = AnswerFile::create([
            'user_answer_id' => $answer->id, 'original_filename' => 'certificate.pdf',
            's3_path' => 'uploads/cert.pdf', 'mime_type' => 'application/pdf',
            'file_size' => 100, 'disk' => 'local', 'validation_status' => 'skipped',
        ]);

        // Every section signed off, so only the documents can block approval.
        OnboardingSectionReview::create([
            'user_onboarding_id' => $this->onboarding->id, 'question_group_id' => $group->id,
            'status' => 'completed', 'reviewed_at' => now(),
        ]);

        $this->reviewer = Admin::create([
            'name' => 'Ops', 'email' => 'ops@test.com', 'password' => 'x',
            'is_active' => true, 'role' => AdminRole::SuperAdmin,
        ]);
    }

    public function test_approval_is_refused_while_a_document_has_no_verdict(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('still need a verdict');

        app(OnboardingService::class)->approve($this->onboarding, $this->reviewer);
    }

    public function test_approval_succeeds_once_every_document_is_judged(): void
    {
        $this->file->update(['review_decision' => 'verified', 'reviewed_at' => now()]);

        app(OnboardingService::class)->approve($this->onboarding->fresh(), $this->reviewer);

        $this->assertSame('approved', $this->onboarding->fresh()->status);
    }

    public function test_a_rejected_document_still_counts_as_reviewed(): void
    {
        // The gate is "looked at", not "approved" — rejecting a document is a
        // verdict, and the application can still be approved on the rest.
        $this->file->update(['review_decision' => 'rejected', 'reviewed_at' => now()]);

        app(OnboardingService::class)->approve($this->onboarding->fresh(), $this->reviewer);

        $this->assertSame('approved', $this->onboarding->fresh()->status);
    }

    public function test_rejection_is_never_blocked_by_unreviewed_documents(): void
    {
        app(OnboardingService::class)->reject($this->onboarding, $this->reviewer, 'Incomplete.');

        $this->assertSame('rejected', $this->onboarding->fresh()->status);
    }

    public function test_an_application_with_no_documents_is_unaffected(): void
    {
        $this->file->delete();

        app(OnboardingService::class)->approve($this->onboarding->fresh(), $this->reviewer);

        $this->assertSame('approved', $this->onboarding->fresh()->status);
    }

    public function test_the_progress_helper_reports_outstanding_documents(): void
    {
        $progress = $this->onboarding->documentReviewProgress();

        $this->assertSame(0, $progress['done']);
        $this->assertSame(1, $progress['total']);
        $this->assertFalse($progress['complete']);
    }

    public function test_reviewing_before_submission_says_why(): void
    {
        $this->onboarding->update(['status' => 'in_progress', 'completed_at' => null]);
        $group = QuestionGroup::first();

        $this->actingAs($this->reviewer, 'admin')
            ->postJson(route('admin.user-onboardings.sections.review', [$this->onboarding, $group]), ['status' => 'completed'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Application review is available only after the user submits the application.');
    }

    public function test_reviewing_someone_elses_application_says_who_holds_it(): void
    {
        $holder = Admin::create([
            'name' => 'Dana', 'email' => 'dana@test.com', 'password' => 'x',
            'is_active' => true, 'role' => AdminRole::Analyst,
        ]);
        $this->onboarding->update(['assigned_to' => $holder->id]);

        $manager = Admin::create([
            'name' => 'Morgan', 'email' => 'morgan@test.com', 'password' => 'x',
            'is_active' => true, 'role' => AdminRole::Manager,
        ]);

        $this->actingAs($manager, 'admin')
            ->postJson(route('admin.user-onboardings.sections.review', [$this->onboarding, QuestionGroup::first()]), ['status' => 'completed'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'This application is being reviewed by Dana. Reassign it to yourself to take over the review.');
    }
}
