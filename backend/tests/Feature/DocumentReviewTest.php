<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AnswerFile;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'document_validation.enabled' => true,
            'document_validation.driver' => 'fake',
            'onboarding_uploads.disk' => 'local',
        ]);
        Storage::fake('local');

        $this->user = User::create(['email' => 'client@test.com', 'name' => 'Test Client', 'position' => 'CFO']);
        app(OnboardingService::class)->initializeForUser($this->user);

        $this->admin = Admin::create([
            'name' => 'Reviewer',
            'email' => 'reviewer@test.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);
    }

    private function makeFlaggedFile(): AnswerFile
    {
        $group = QuestionGroup::create(['name' => 'Docs', 'slug' => 'docs', 'order' => 1, 'is_active' => true]);
        $question = Question::create([
            'question_group_id' => $group->id,
            'label' => 'Certificate of Incorporation',
            'type' => 'file',
            'is_required' => true,
            'order' => 1,
            'is_active' => true,
            'validation_rules' => ['expected_document' => 'certificate_of_incorporation'],
        ]);

        Sanctum::actingAs($this->user);
        $this->post('/api/onboarding/answers/upload', [
            'question_id' => $question->id,
            'files' => [UploadedFile::fake()->create('unreadable-scan.pdf', 100, 'application/pdf')],
        ])->assertOk();

        return AnswerFile::latest('id')->firstOrFail();
    }

    public function test_review_queue_lists_flagged_documents_with_details(): void
    {
        $file = $this->makeFlaggedFile();
        $this->assertSame('needs_review', $file->validation_status);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.document-reviews.index'))
            ->assertOk()
            ->assertSee('unreadable-scan.pdf')
            ->assertSee('Certificate of Incorporation')
            ->assertSee('Auto-pass rate');
    }

    public function test_approve_marks_file_reviewed_and_removes_it_from_queue(): void
    {
        $file = $this->makeFlaggedFile();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.document-reviews.approve', $file))
            ->assertRedirect();

        $file->refresh();
        $this->assertNotNull($file->reviewed_at);
        $this->assertSame($this->admin->id, $file->reviewed_by);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.document-reviews.index'))
            ->assertDontSee('unreadable-scan.pdf');
    }

    public function test_review_queue_requires_admin_auth(): void
    {
        $this->get(route('admin.document-reviews.index'))->assertRedirect(route('admin.login'));
    }

    public function test_client_payload_reflects_reviewer_approval(): void
    {
        $file = $this->makeFlaggedFile();
        $file->update(['reviewed_at' => now(), 'reviewed_by' => $this->admin->id]);

        // The questions endpoint serves questions mapped to the user's type.
        $type = \App\Models\UserType::create(['name' => 'Corporate', 'slug' => 'corporate', 'order' => 1, 'is_active' => true]);
        \App\Models\QuestionTypeMapping::create([
            'question_id' => $file->answer->question_id,
            'user_type_id' => $type->id,
            'is_required' => true,
            'order' => 1,
        ]);
        app(OnboardingService::class)->setUserType($this->user->onboarding, $type->id, null);

        Sanctum::actingAs($this->user);
        $response = $this->get('/api/onboarding/questions')->assertOk();

        $fileData = collect($response->json('data'))
            ->flatMap(fn ($g) => $g['questions'])
            ->firstWhere('type', 'file')['files'][0];

        $this->assertTrue($fileData['reviewed']);
        $this->assertSame('needs_review', $fileData['validation_status']);
    }

    public function test_rules_driver_stores_extracted_excerpt(): void
    {
        config(['document_validation.driver' => 'rules']);

        $group = QuestionGroup::create(['name' => 'Docs', 'slug' => 'docs2', 'order' => 1, 'is_active' => true]);
        $question = Question::create([
            'question_group_id' => $group->id,
            'label' => 'Board resolution',
            'type' => 'file',
            'is_required' => true,
            'order' => 1,
            'is_active' => true,
            'validation_rules' => ['expected_document' => 'board_resolution'],
        ]);

        $pdf = (new class { use \Tests\Support\MakesPdfs { makePdf as public; } })->makePdf([
            'BOARD RESOLUTION of ACME HOLDINGS LTD',
            'At a duly convened meeting of the Board of Directors it was resolved that',
            'the company shall open a corporate account with the provider.',
        ]);

        Sanctum::actingAs($this->user);
        $this->post('/api/onboarding/answers/upload', [
            'question_id' => $question->id,
            'files' => [UploadedFile::fake()->createWithContent('resolution.pdf', $pdf)],
        ])->assertOk();

        $file = AnswerFile::latest('id')->firstOrFail();
        $this->assertSame('passed', $file->validation_status);
        $this->assertStringContainsString('duly convened', $file->extracted_excerpt);
    }
}
