<?php

namespace Tests\Feature;

use App\Models\OnboardingStep;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Smalot\PdfParser\Parser as PdfParser;
use Tests\TestCase;

class ApplicationPdfTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private OnboardingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->user = User::create(['email' => 'client@test.com', 'name' => 'Test Client', 'position' => 'CFO']);
        $this->service = app(OnboardingService::class);

        OnboardingStep::query()->delete();
        OnboardingStep::create(['name' => 'Review', 'slug' => 'review', 'component_key' => 'review', 'order' => 1, 'is_active' => true]);
    }

    private function seedAnswers($onboarding): void
    {
        $group = QuestionGroup::create(['name' => 'Company Information', 'slug' => 'company-info', 'order' => 1, 'is_active' => true]);

        $name = Question::create([
            'question_group_id' => $group->id, 'label' => 'Full Legal Entity Name',
            'type' => 'text', 'is_required' => true, 'order' => 1, 'is_active' => true,
        ]);
        $ubo = Question::create([
            'question_group_id' => $group->id, 'label' => 'Ultimate Beneficial Owners',
            'type' => 'ubo', 'is_required' => true, 'order' => 2, 'is_active' => true,
        ]);

        UserAnswer::create([
            'user_id' => $this->user->id, 'question_id' => $name->id,
            'user_onboarding_id' => $onboarding->id, 'value' => 'Acme Holdings Ltd',
        ]);
        UserAnswer::create([
            'user_id' => $this->user->id, 'question_id' => $ubo->id,
            'user_onboarding_id' => $onboarding->id,
            'value' => json_encode([
                ['full_name' => 'Jane Doe', 'ownership_percent' => 60, 'nationality' => 'GB', 'is_pep' => false],
                ['full_name' => 'John Roe', 'ownership_percent' => 40, 'nationality' => 'US', 'is_pep' => true],
            ]),
        ]);
    }

    public function test_pdf_download_requires_a_submitted_application(): void
    {
        $this->service->initializeForUser($this->user);

        Sanctum::actingAs($this->user);
        $this->get('/api/onboarding/download-pdf')->assertStatus(403);
    }

    public function test_submitted_application_downloads_as_pdf_with_content(): void
    {
        $onboarding = $this->service->initializeForUser($this->user);
        $this->seedAnswers($onboarding);
        foreach ($onboarding->steps as $step) {
            $this->service->completeStep($onboarding->fresh(), $step);
        }

        Sanctum::actingAs($this->user);
        $response = $this->get('/api/onboarding/download-pdf')->assertOk();

        $onboarding->refresh();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString(
            "application-{$onboarding->reference}.pdf",
            $response->headers->get('content-disposition'),
        );

        $text = (new PdfParser())->parseContent($response->getContent())->getText();

        $this->assertStringContainsString($onboarding->reference, $text);
        $this->assertStringContainsString('Test Client', $text);
        $this->assertStringContainsString('Company Information', $text);
        $this->assertStringContainsString('Acme Holdings Ltd', $text);
        // UBO JSON is rendered as readable lines, not raw JSON.
        $this->assertStringContainsString('Jane Doe', $text);
        $this->assertStringContainsString('60% ownership', $text);
        $this->assertStringContainsString('PEP', $text);
        $this->assertStringNotContainsString('full_name', $text);
    }

    public function test_decided_application_pdf_includes_the_decision(): void
    {
        $onboarding = $this->service->initializeForUser($this->user);
        foreach ($onboarding->steps as $step) {
            $this->service->completeStep($onboarding->fresh(), $step);
        }
        $admin = \App\Models\Admin::create(['name' => 'Reviewer', 'email' => 'a@test.com', 'password' => 'x', 'is_active' => true]);
        $this->service->approve($onboarding->fresh(), $admin, 'All checks passed.');

        Sanctum::actingAs($this->user);
        $response = $this->get('/api/onboarding/download-pdf')->assertOk();

        $text = (new PdfParser())->parseContent($response->getContent())->getText();
        $this->assertStringContainsString('Approved', $text);
        $this->assertStringContainsString('All checks passed.', $text);
    }
}
