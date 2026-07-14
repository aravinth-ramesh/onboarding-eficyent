<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientTimelineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Admin $admin;
    private OnboardingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->user = User::create(['email' => 'client@test.com', 'name' => 'Test Client', 'position' => 'CFO']);
        $this->admin = Admin::create(['name' => 'Reviewer', 'email' => 'admin@test.com', 'password' => 'x', 'is_active' => true]);
        $this->service = app(OnboardingService::class);

        OnboardingStep::query()->delete();
        OnboardingStep::create(['name' => 'Review', 'slug' => 'review', 'component_key' => 'review', 'order' => 1, 'is_active' => true]);
    }

    private function submit()
    {
        $onboarding = $this->service->initializeForUser($this->user);
        foreach ($onboarding->steps as $step) {
            $this->service->completeStep($onboarding->fresh(), $step);
        }

        return $onboarding->fresh();
    }

    public function test_awaiting_review_timeline_shows_submission_and_current_stage(): void
    {
        $this->submit();

        Sanctum::actingAs($this->user);
        $events = $this->getJson('/api/onboarding/timeline')->assertOk()->json('data');

        $this->assertSame(['submitted', 'under_review'], array_column($events, 'event'));
        $this->assertTrue(end($events)['current']);
        $this->assertSame('Under review by our team', end($events)['label']);
    }

    public function test_full_cycle_timeline_is_client_safe(): void
    {
        $onboarding = $this->submit();
        $this->service->reject($onboarding, $this->admin, 'UBO section incomplete.');
        $this->service->reopen($onboarding->fresh());
        $reviewStep = $onboarding->fresh()->steps()->where('component_key', 'review')->first();
        $this->service->completeStep($onboarding->fresh(), $reviewStep);
        $this->service->approve($onboarding->fresh(), $this->admin, 'All resolved.');

        Sanctum::actingAs($this->user);
        $response = $this->getJson('/api/onboarding/timeline')->assertOk();
        $events = $response->json('data');

        $this->assertSame(
            ['submitted', 'rejected', 'reopened', 'resubmitted', 'approved'],
            array_column($events, 'event'),
        );

        // No virtual under-review node once decided; decision comments
        // included; admin identity never exposed.
        $this->assertStringContainsString('UBO section incomplete.', json_encode($events));
        $this->assertStringNotContainsString('Reviewer', $response->getContent());
        $this->assertStringNotContainsString('admin', strtolower(json_encode(array_column($events, 'label'))));
    }

    public function test_timeline_is_empty_before_submission(): void
    {
        $this->service->initializeForUser($this->user);

        Sanctum::actingAs($this->user);
        $this->getJson('/api/onboarding/timeline')->assertOk()->assertJsonCount(0, 'data');
    }
}
