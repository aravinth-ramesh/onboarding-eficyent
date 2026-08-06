<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\AdminNotification;
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
 * Requesting a document resubmission notifies the client (bug report EOP-99)
 * and the verdict is exposed to the client so it isn't shown as approved
 * (EOP-98).
 */
class DocumentResubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_resubmission_raises_a_change_request_for_the_client(): void
    {
        Mail::fake();
        $type = UserType::create(['name' => 'Corp', 'slug' => 'corp', 'order' => 1, 'is_active' => true]);
        $user = User::create(['email' => 'c@t.com', 'name' => 'Co', 'position' => 'CFO']);
        $onb = UserOnboarding::create(['user_id' => $user->id, 'user_type_id' => $type->id, 'status' => 'completed', 'started_at' => now(), 'completed_at' => now()]);
        $group = QuestionGroup::create(['name' => 'Docs', 'slug' => 'docs', 'order' => 1, 'is_active' => true]);
        $q = Question::create(['question_group_id' => $group->id, 'label' => 'Certificate of Incorporation', 'type' => 'file', 'is_required' => true, 'order' => 1, 'is_active' => true]);
        $answer = UserAnswer::create(['user_id' => $user->id, 'question_id' => $q->id, 'user_onboarding_id' => $onb->id, 'value' => 'cert.pdf']);
        $file = AnswerFile::create(['user_answer_id' => $answer->id, 'original_filename' => 'cert.pdf', 's3_path' => 'u/c.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100, 'disk' => 'local', 'validation_status' => 'passed']);

        $admin = Admin::create(['name' => 'M', 'email' => 'm@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Manager]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.user-onboardings.documents.review', [$onb, $file]), [
                'review_decision' => 'resubmit_requested', 'review_note' => 'Scan is unreadable.',
            ])
            ->assertRedirect();

        $this->assertSame('resubmit_requested', $file->refresh()->review_decision);
        $this->assertDatabaseHas('admin_notifications', [
            'user_answer_id' => $answer->id, 'type' => 'change_request', 'status' => 'pending',
        ]);
    }

    public function test_verifying_a_document_does_not_raise_a_change_request(): void
    {
        Mail::fake();
        $type = UserType::create(['name' => 'Corp', 'slug' => 'corp', 'order' => 1, 'is_active' => true]);
        $user = User::create(['email' => 'c@t.com', 'name' => 'Co', 'position' => 'CFO']);
        $onb = UserOnboarding::create(['user_id' => $user->id, 'user_type_id' => $type->id, 'status' => 'completed', 'started_at' => now(), 'completed_at' => now()]);
        $group = QuestionGroup::create(['name' => 'Docs', 'slug' => 'docs', 'order' => 1, 'is_active' => true]);
        $q = Question::create(['question_group_id' => $group->id, 'label' => 'Certificate', 'type' => 'file', 'is_required' => true, 'order' => 1, 'is_active' => true]);
        $answer = UserAnswer::create(['user_id' => $user->id, 'question_id' => $q->id, 'user_onboarding_id' => $onb->id, 'value' => 'cert.pdf']);
        $file = AnswerFile::create(['user_answer_id' => $answer->id, 'original_filename' => 'cert.pdf', 's3_path' => 'u/c.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100, 'disk' => 'local']);
        $admin = Admin::create(['name' => 'M', 'email' => 'm@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Manager]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.user-onboardings.documents.review', [$onb, $file]), ['review_decision' => 'verified']);

        $this->assertSame(0, AdminNotification::where('type', 'change_request')->count());
    }
}
