<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

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
        Sanctum::actingAs($this->user);
    }

    private function makeQuestion(?array $rules): Question
    {
        $group = QuestionGroup::firstOrCreate(
            ['slug' => 'docs'],
            ['name' => 'Docs', 'order' => 1, 'is_active' => true],
        );

        return Question::create([
            'question_group_id' => $group->id,
            'label' => 'Upload document',
            'type' => 'file',
            'is_required' => true,
            'order' => 1,
            'is_active' => true,
            'validation_rules' => $rules,
        ]);
    }

    private function upload(Question $question, string $filename, ?string $justification = null)
    {
        $payload = [
            'question_id' => $question->id,
            'files' => [UploadedFile::fake()->create($filename, 100, 'application/pdf')],
        ];
        if ($justification !== null) {
            $payload['justification'] = $justification;
        }

        return $this->post('/api/onboarding/answers/upload', $payload);
    }

    public function test_matching_document_passes(): void
    {
        $question = $this->makeQuestion(['expected_document' => 'certificate_of_incorporation']);

        $response = $this->upload($question, 'certificate-of-incorporation.pdf');

        $response->assertOk();
        $this->assertDatabaseHas('answer_files', [
            'original_filename' => 'certificate-of-incorporation.pdf',
            'validation_status' => 'passed',
            'detected_type' => 'certificate_of_incorporation',
        ]);
    }

    public function test_wrong_document_type_is_rejected(): void
    {
        $question = $this->makeQuestion(['expected_document' => 'certificate_of_incorporation']);

        $response = $this->upload($question, 'articles-of-association.pdf');

        $response->assertStatus(422)
            ->assertJsonPath('code', 'document_validation_failed')
            ->assertJsonPath("document_validation.{$question->id}.0.reason", 'type_mismatch')
            ->assertJsonPath("document_validation.{$question->id}.0.can_justify", true);
        $this->assertDatabaseCount('answer_files', 0);
    }

    public function test_wrong_type_accepted_with_justification(): void
    {
        $question = $this->makeQuestion(['expected_document' => 'certificate_of_incorporation']);

        $response = $this->upload($question, 'articles-of-association.pdf', 'Registrar bundles both documents in one certificate.');

        $response->assertOk();
        $this->assertDatabaseHas('answer_files', [
            'validation_status' => 'type_mismatch',
            'justification' => 'Registrar bundles both documents in one certificate.',
        ]);
    }

    public function test_expired_document_is_rejected_then_accepted_with_justification(): void
    {
        $question = $this->makeQuestion(['expected_document' => 'identity_document']);

        $this->upload($question, 'expired-passport.pdf')
            ->assertStatus(422)
            ->assertJsonPath("document_validation.{$question->id}.0.reason", 'expired');

        $this->upload($question, 'expired-passport.pdf', 'Renewal is in progress; receipt attached separately.')
            ->assertOk();
        $this->assertDatabaseHas('answer_files', ['validation_status' => 'expired']);
    }

    public function test_stale_proof_of_address_is_rejected(): void
    {
        $question = $this->makeQuestion(['expected_document' => 'proof_of_address', 'max_age_months' => 3]);

        $this->upload($question, 'stale-proof-of-address.pdf')
            ->assertStatus(422)
            ->assertJsonPath("document_validation.{$question->id}.0.reason", 'stale');
    }

    public function test_unreadable_document_fails_open_to_needs_review(): void
    {
        $question = $this->makeQuestion(['expected_document' => 'certificate_of_incorporation']);

        $this->upload($question, 'unreadable-scan.pdf')->assertOk();
        $this->assertDatabaseHas('answer_files', ['validation_status' => 'needs_review']);
    }

    public function test_question_without_policy_is_skipped(): void
    {
        $question = $this->makeQuestion(null);

        $this->upload($question, 'anything.pdf')->assertOk();
        $this->assertDatabaseHas('answer_files', ['validation_status' => 'skipped']);
    }

    public function test_disabled_validation_skips_analysis(): void
    {
        config(['document_validation.enabled' => false]);
        $question = $this->makeQuestion(['expected_document' => 'certificate_of_incorporation']);

        $this->upload($question, 'articles-of-association.pdf')->assertOk();
        $this->assertDatabaseHas('answer_files', ['validation_status' => 'skipped']);
    }

    public function test_bulk_save_answers_endpoint_also_validates(): void
    {
        $question = $this->makeQuestion(['expected_document' => 'certificate_of_incorporation']);

        $response = $this->post('/api/onboarding/answers', [
            'file_answers' => [[
                'question_id' => (string) $question->id,
                'file' => UploadedFile::fake()->create('articles-of-association.pdf', 100, 'application/pdf'),
            ]],
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'document_validation_failed');

        $this->post('/api/onboarding/answers', [
            'file_answers' => [[
                'question_id' => (string) $question->id,
                'file' => UploadedFile::fake()->create('articles-of-association.pdf', 100, 'application/pdf'),
            ]],
            'file_justifications' => [$question->id => 'Combined document from our registrar.'],
        ])->assertOk();

        $this->assertDatabaseHas('answer_files', ['validation_status' => 'type_mismatch']);
    }
}
