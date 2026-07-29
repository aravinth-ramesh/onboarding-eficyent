<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Mail\OnboardingDecisionMail;
use App\Models\Admin;
use App\Models\AnswerFile;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * End-to-end walk of the whole review pipeline through real HTTP routes,
 * composing every phase: a manager assigns an analyst (Phase 2), the analyst —
 * who alone can see the company (Phase 1) — reviews each section and verifies a
 * document (Phase 3), hands it off, and a different manager approves under the
 * four-eyes gate (Phase 4), with the reassignment recorded (Phase 5).
 */
class ReviewLifecycleE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_a_company_travels_assign_review_handoff_approve(): void
    {
        Mail::fake();

        // --- fixtures --------------------------------------------------------
        $type = UserType::create(['name' => 'Corporate', 'slug' => 'corporate', 'order' => 1, 'is_active' => true]);
        $client = User::create(['email' => 'client@acme.com', 'name' => 'Acme Ltd', 'position' => 'CFO']);
        $onboarding = UserOnboarding::create([
            'user_id' => $client->id, 'user_type_id' => $type->id,
            'status' => 'completed', 'started_at' => now()->subDays(2), 'completed_at' => now()->subDay(),
        ]);

        $companyGroup = QuestionGroup::create(['name' => 'Company', 'slug' => 'company', 'order' => 1, 'is_active' => true]);
        $docsGroup = QuestionGroup::create(['name' => 'Documents', 'slug' => 'docs', 'order' => 2, 'is_active' => true]);
        $nameQ = Question::create(['question_group_id' => $companyGroup->id, 'label' => 'Legal name', 'type' => 'text', 'is_required' => true, 'order' => 1, 'is_active' => true]);
        UserAnswer::create(['user_id' => $client->id, 'question_id' => $nameQ->id, 'user_onboarding_id' => $onboarding->id, 'value' => 'Acme Ltd']);
        $docQ = Question::create(['question_group_id' => $docsGroup->id, 'label' => 'Certificate', 'type' => 'file', 'is_required' => true, 'order' => 1, 'is_active' => true]);
        $docA = UserAnswer::create(['user_id' => $client->id, 'question_id' => $docQ->id, 'user_onboarding_id' => $onboarding->id, 'value' => 'cert.pdf']);
        $file = AnswerFile::create(['user_answer_id' => $docA->id, 'original_filename' => 'cert.pdf', 's3_path' => 'u/c.pdf', 'mime_type' => 'application/pdf', 'file_size' => 2048, 'disk' => 'local', 'validation_status' => 'passed']);

        $manager = Admin::create(['name' => 'Morgan', 'email' => 'morgan@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Manager]);
        $analyst = Admin::create(['name' => 'Alex', 'email' => 'alex@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Analyst]);
        $otherAnalyst = Admin::create(['name' => 'Sam', 'email' => 'sam@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Analyst]);

        // --- Phase 2: manager assigns the analyst ---------------------------
        $this->actingAs($manager, 'admin')
            ->post(route('admin.user-onboardings.assign', $onboarding), ['assigned_to' => $analyst->id])
            ->assertRedirect();
        $this->assertSame($analyst->id, $onboarding->refresh()->assigned_to);
        // Phase 5: the reassignment is on the timeline.
        $this->assertDatabaseHas('onboarding_review_logs', ['user_onboarding_id' => $onboarding->id, 'event' => 'assigned']);

        // --- Phase 1: only the assigned analyst may open it -----------------
        $this->actingAs($otherAnalyst, 'admin')->get(route('admin.user-onboardings.show', $onboarding))->assertForbidden();
        $this->actingAs($analyst, 'admin')->get(route('admin.user-onboardings.show', $onboarding))->assertOk();

        // --- Phase 4 gate: can't hand off before sections are reviewed ------
        $this->actingAs($analyst, 'admin')
            ->post(route('admin.user-onboardings.submit-for-approval', $onboarding))
            ->assertRedirect();
        $this->assertNull($onboarding->refresh()->approval_state, 'hand-off must be blocked until sections are reviewed');

        // --- Phase 3: analyst reviews both sections + verifies the document -
        foreach ([$companyGroup, $docsGroup] as $group) {
            $this->actingAs($analyst, 'admin')
                ->post(route('admin.user-onboardings.sections.review', [$onboarding, $group]), ['status' => 'completed'])
                ->assertRedirect();
        }
        $this->assertTrue($onboarding->fresh()->load('sectionReviews', 'answers.question.group')->sectionReviewProgress()['complete']);

        $this->actingAs($analyst, 'admin')
            ->post(route('admin.user-onboardings.documents.review', [$onboarding, $file]), ['review_decision' => 'verified'])
            ->assertRedirect();
        $this->assertSame('verified', $file->refresh()->review_decision);

        // --- Phase 4: analyst hands off (maker) -----------------------------
        $this->actingAs($analyst, 'admin')
            ->post(route('admin.user-onboardings.submit-for-approval', $onboarding))
            ->assertRedirect();
        $this->assertSame('pending_approval', $onboarding->refresh()->approval_state);
        $this->assertSame($analyst->id, $onboarding->submitted_for_approval_by);

        // Analysts cannot decide at all — the RBAC gate holds (Phase 1).
        $this->actingAs($analyst, 'admin')
            ->post(route('admin.user-onboardings.approve', $onboarding), ['comment' => 'me'])
            ->assertForbidden();

        // --- Phase 4: a different reviewer (the manager) approves -----------
        $this->actingAs($manager, 'admin')
            ->post(route('admin.user-onboardings.approve', $onboarding), ['comment' => 'All checks pass.'])
            ->assertRedirect();

        $onboarding->refresh();
        $this->assertSame('approved', $onboarding->status);
        $this->assertSame($manager->id, $onboarding->decided_by);
        $this->assertNull($onboarding->approval_state);
        Mail::assertQueued(OnboardingDecisionMail::class);
    }
}
