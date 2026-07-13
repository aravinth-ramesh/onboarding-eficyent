<?php

namespace Tests\Feature;

use App\Models\AnswerFile;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\MakesPdfs;
use Tests\TestCase;

/**
 * Phase 2 coverage: scanned PDFs (image-only pages) and uploaded images go
 * through pdftoppm + Tesseract instead of falling straight to human review.
 */
class OcrValidationTest extends TestCase
{
    use MakesPdfs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->ocrToolchainAvailable()) {
            $this->markTestSkipped('pdftoppm/tesseract not installed');
        }

        config([
            'document_validation.enabled' => true,
            'document_validation.driver' => 'rules',
            'onboarding_uploads.disk' => 'local',
        ]);
        Storage::fake('local');

        $user = User::create(['email' => 'client@test.com', 'name' => 'Test Client', 'position' => 'CFO']);
        app(OnboardingService::class)->initializeForUser($user);
        Sanctum::actingAs($user);
    }

    private function makeQuestion(array $rules): Question
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

    private const CERT_LINES = [
        'CERTIFICATE OF INCORPORATION',
        'The Registrar of Companies hereby certify that ACME HOLDINGS LTD',
        'is incorporated under the Companies Act 2006.',
        'Company Number 1234567',
        'Dated this 14 June 2026',
    ];

    public function test_scanned_pdf_certificate_is_read_via_ocr_and_passes(): void
    {
        $question = $this->makeQuestion(['expected_document' => 'certificate_of_incorporation']);

        $this->post('/api/onboarding/answers/upload', [
            'question_id' => $question->id,
            'files' => [UploadedFile::fake()->createWithContent('scanned-cert.pdf', $this->makeScannedPdf(self::CERT_LINES))],
        ])->assertOk();

        $file = AnswerFile::latest('id')->first();
        $this->assertSame('passed', $file->validation_status);
        $this->assertSame('certificate_of_incorporation', $file->detected_type);
        $this->assertStringContainsString('OCR', $file->validation_summary);
    }

    public function test_photo_of_wrong_document_is_caught_by_ocr(): void
    {
        $question = $this->makeQuestion(['expected_document' => 'certificate_of_incorporation']);

        $png = $this->rasterizePdf($this->makePdf([
            'ARTICLES OF ASSOCIATION',
            'MEMORANDUM OF ASSOCIATION of ACME HOLDINGS LTD',
            'The share capital of the company is divided into ordinary shares.',
            'The objects of the company are unrestricted.',
        ]));

        $this->post('/api/onboarding/answers/upload', [
            'question_id' => $question->id,
            'files' => [UploadedFile::fake()->createWithContent('photo.png', $png)],
        ])->assertStatus(422)
            ->assertJsonPath("document_validation.{$question->id}.0.reason", 'type_mismatch');
    }

    public function test_expired_date_survives_the_ocr_round_trip(): void
    {
        $question = $this->makeQuestion(['expected_document' => 'license']);

        $this->post('/api/onboarding/answers/upload', [
            'question_id' => $question->id,
            'files' => [UploadedFile::fake()->createWithContent('scanned-license.pdf', $this->makeScannedPdf([
                'FINANCIAL SERVICES LICENCE',
                'Licence Number FS-2211',
                'ACME HOLDINGS LTD is authorised and regulated by the Financial Conduct Authority',
                'with permission to carry on regulated activities.',
                'Valid until 31 December 2024',
            ]))],
        ])->assertStatus(422)
            ->assertJsonPath("document_validation.{$question->id}.0.reason", 'expired');
    }

    public function test_unreadable_noise_image_falls_open_to_needs_review(): void
    {
        $question = $this->makeQuestion(['expected_document' => 'certificate_of_incorporation']);

        // Random noise: OCR finds nothing trustworthy.
        $img = imagecreatetruecolor(400, 300);
        for ($i = 0; $i < 3000; $i++) {
            $color = imagecolorallocate($img, rand(0, 255), rand(0, 255), rand(0, 255));
            imagesetpixel($img, rand(0, 399), rand(0, 299), $color);
        }
        ob_start();
        imagepng($img);
        $noise = ob_get_clean();
        imagedestroy($img);

        $this->post('/api/onboarding/answers/upload', [
            'question_id' => $question->id,
            'files' => [UploadedFile::fake()->createWithContent('blurry-photo.png', $noise)],
        ])->assertOk();

        $this->assertDatabaseHas('answer_files', ['validation_status' => 'needs_review']);
    }
}
