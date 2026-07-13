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
use Tests\Support\MakesPdfs;
use Tests\TestCase;

/**
 * End-to-end coverage of the non-AI 'rules' driver: real (minimal) PDFs go
 * through the upload endpoint, local text extraction, phrase classification,
 * labeled-date checks, and MRZ parsing.
 */
class RulesDriverValidationTest extends TestCase
{
    use MakesPdfs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function uploadPdf(Question $question, string $filename, array $lines, ?string $justification = null)
    {
        $payload = [
            'question_id' => $question->id,
            'files' => [UploadedFile::fake()->createWithContent($filename, $this->makePdf($lines))],
        ];
        if ($justification !== null) {
            $payload['justification'] = $justification;
        }

        return $this->post('/api/onboarding/answers/upload', $payload);
    }

    public function test_real_certificate_of_incorporation_passes(): void
    {
        $question = $this->makeQuestion(['expected_document' => 'certificate_of_incorporation']);

        $this->uploadPdf($question, 'certificate.pdf', [
            'CERTIFICATE OF INCORPORATION',
            'The Registrar of Companies hereby certify that ACME HOLDINGS LTD',
            'is incorporated under the Companies Act 2006.',
            'Company Number 1234567',
            'Dated this 14 June 2026',
        ])->assertOk();

        $this->assertDatabaseHas('answer_files', [
            'validation_status' => 'passed',
            'detected_type' => 'certificate_of_incorporation',
            'issue_date' => '2026-06-14',
        ]);
    }

    public function test_articles_uploaded_as_certificate_is_rejected(): void
    {
        $question = $this->makeQuestion(['expected_document' => 'certificate_of_incorporation']);

        $this->uploadPdf($question, 'articles.pdf', [
            'ARTICLES OF ASSOCIATION',
            'MEMORANDUM OF ASSOCIATION of ACME HOLDINGS LTD',
            'The share capital of the company is divided into 1,000 ordinary shares.',
            'The objects of the company are unrestricted.',
        ])->assertStatus(422)
            ->assertJsonPath("document_validation.{$question->id}.0.reason", 'type_mismatch');
    }

    public function test_stale_utility_bill_is_rejected_and_recent_one_passes(): void
    {
        $question = $this->makeQuestion(['expected_document' => ['proof_of_address', 'bank_statement'], 'max_age_months' => 3]);

        $bill = fn (string $date) => [
            'CITY POWER — ELECTRICITY UTILITY',
            'Billing Address: 1 Main Street, Springfield',
            'Account holder: ACME HOLDINGS LTD',
            'Meter reading: 04512 kWh',
            'Amount Due: 120.00',
            "Bill date: {$date}",
        ];

        $this->uploadPdf($question, 'old-bill.pdf', $bill('01/03/2026'))
            ->assertStatus(422)
            ->assertJsonPath("document_validation.{$question->id}.0.reason", 'stale');

        $this->uploadPdf($question, 'recent-bill.pdf', $bill('01/07/2026'))->assertOk();
        $this->assertDatabaseHas('answer_files', [
            'validation_status' => 'passed',
            'detected_type' => 'proof_of_address',
        ]);
    }

    public function test_expired_license_is_rejected_then_accepted_with_justification(): void
    {
        $question = $this->makeQuestion(['expected_document' => 'license']);

        $license = [
            'FINANCIAL SERVICES LICENCE',
            'Licence Number FS-2211',
            'ACME HOLDINGS LTD is authorised and regulated by the Financial Conduct Authority',
            'with permission to carry on regulated activities.',
            'Valid until 31 December 2024',
        ];

        $this->uploadPdf($question, 'license.pdf', $license)
            ->assertStatus(422)
            ->assertJsonPath("document_validation.{$question->id}.0.reason", 'expired');

        $this->uploadPdf($question, 'license.pdf', $license, 'Renewal confirmation from the regulator attached under AML documents.')
            ->assertOk();
        $this->assertDatabaseHas('answer_files', [
            'validation_status' => 'expired',
            'expiry_date' => '2024-12-31',
        ]);
    }

    public function test_expired_passport_detected_via_mrz(): void
    {
        $question = $this->makeQuestion(['expected_document' => 'identity_document']);

        $this->uploadPdf($question, 'passport.pdf', [
            'REPUBLIC OF UTOPIA — PASSPORT',
            'Surname: ERIKSSON  Given names: ANNA MARIA',
            'P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<',
            'L898902C36UTO7408122F1204159ZE184226B<<<<<10',
        ])->assertStatus(422)
            ->assertJsonPath("document_validation.{$question->id}.0.reason", 'expired');
    }

    /**
     * Fixture corpus for the remaining configured document types: each is
     * classified correctly against its own policy AND rejected when uploaded
     * where a certificate is expected.
     */
    public function test_remaining_document_types_classify_correctly(): void
    {
        $fixtures = [
            'register_extract' => [
                'REGISTER OF MEMBERS AND REGISTER OF DIRECTORS',
                'ACME HOLDINGS LTD — statutory registers extract',
                'Shareholder: Jane Doe, shareholding 60% of ordinary shares',
                'Shareholder: John Roe, shareholding 40%, beneficial owner',
            ],
            'tax_certificate' => [
                'TAX RESIDENCY CERTIFICATE',
                'Issued by the National Tax Authority under the tax registration scheme',
                'Taxpayer Identification Number: 99-8877665',
                'VAT registration confirmed for ACME HOLDINGS LTD',
            ],
            'financial_statements' => [
                'ACME HOLDINGS LTD — ANNUAL REPORT',
                'Statement of Financial Position (Balance Sheet)',
                'Income Statement and Cash Flow Statement for the year',
                "Independent Auditor's Report: retained earnings of 1.2m",
            ],
            'policy_document' => [
                'ANTI-MONEY LAUNDERING AND COUNTER-TERRORIST FINANCING POLICY',
                'This policy sets out the procedure for customer due diligence.',
                'The compliance officer performs an annual risk assessment.',
            ],
        ];

        foreach ($fixtures as $type => $lines) {
            $question = $this->makeQuestion(['expected_document' => $type]);
            $this->uploadPdf($question, "{$type}.pdf", $lines)->assertOk();
            $this->assertDatabaseHas('answer_files', [
                'original_filename' => "{$type}.pdf",
                'validation_status' => 'passed',
                'detected_type' => $type,
            ]);

            // The same document against a certificate question must mismatch.
            $certQuestion = $this->makeQuestion(['expected_document' => 'certificate_of_incorporation']);
            $this->uploadPdf($certQuestion, "wrong-{$type}.pdf", $lines)
                ->assertStatus(422)
                ->assertJsonPath("document_validation.{$certQuestion->id}.0.reason", 'type_mismatch');
        }
    }

    public function test_unreadable_document_falls_open_to_needs_review(): void
    {
        $question = $this->makeQuestion(['expected_document' => 'certificate_of_incorporation']);

        // Real PDF but with too little text to analyze (like a scan).
        $this->uploadPdf($question, 'scan.pdf', ['x'])->assertOk();

        $this->assertDatabaseHas('answer_files', ['validation_status' => 'needs_review']);
    }

    public function test_unrelated_document_goes_to_review_not_hard_reject(): void
    {
        $question = $this->makeQuestion(['expected_document' => 'certificate_of_incorporation']);

        $this->uploadPdf($question, 'letter.pdf', [
            'Dear team, please find attached the notes from our offsite.',
            'We discussed roadmaps, hiring plans and the summer party.',
            'Regards, The Management. This letter is only filler text.',
        ])->assertOk();

        $this->assertDatabaseHas('answer_files', ['validation_status' => 'needs_review']);
    }
}
