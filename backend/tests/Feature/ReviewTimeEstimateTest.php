<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\OnboardingReviewLog;
use App\Models\OnboardingStep;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Models\UserType;
use App\Services\OnboardingService;
use App\Services\ReviewTimeEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewTimeEstimateTest extends TestCase
{
    use RefreshDatabase;

    private ReviewTimeEstimator $estimator;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->estimator = app(ReviewTimeEstimator::class);
    }

    /** Seed one submission→decision pair taking the given number of hours. */
    private function seedReview(int $hoursToDecision): void
    {
        static $i = 0;
        $i++;

        $user = User::create(['email' => "c{$i}@test.com", 'name' => "Client {$i}", 'position' => 'CFO']);
        $type = UserType::firstOrCreate(
            ['slug' => 'corporate'],
            ['name' => 'Corporate', 'order' => 1, 'is_active' => true],
        );
        $onboarding = UserOnboarding::create([
            'user_id' => $user->id, 'user_type_id' => $type->id,
            'status' => 'approved', 'started_at' => now()->subDays(5),
        ]);

        $submittedAt = now()->subDays(2);
        OnboardingReviewLog::forceCreate([
            'user_onboarding_id' => $onboarding->id, 'event' => 'submitted',
            'created_at' => $submittedAt, 'updated_at' => $submittedAt,
        ]);
        OnboardingReviewLog::forceCreate([
            'user_onboarding_id' => $onboarding->id, 'event' => 'approved',
            'created_at' => $submittedAt->copy()->addHours($hoursToDecision),
            'updated_at' => $submittedAt->copy()->addHours($hoursToDecision),
        ]);
    }

    public function test_falls_back_to_config_until_enough_samples(): void
    {
        config(['onboarding.review_estimate.fallback_hours' => 72]);

        $this->seedReview(2);
        $this->seedReview(4); // only 2 samples < min of 3

        $this->assertSame(72.0, $this->estimator->estimateHours());
        $this->assertSame('Typically decided within 3 days', $this->estimator->estimateLabel());
    }

    public function test_uses_the_median_of_recent_reviews(): void
    {
        $this->seedReview(10);
        $this->seedReview(20);
        $this->seedReview(200); // slow outlier must not dominate

        $this->assertSame(20.0, $this->estimator->estimateHours());
        $this->assertSame('Typically decided within a day', $this->estimator->estimateLabel());
    }

    public function test_timeline_under_review_node_carries_the_estimate(): void
    {
        config(['onboarding.review_estimate.fallback_hours' => 48]);

        $user = User::create(['email' => 'client@test.com', 'name' => 'Test Client', 'position' => 'CFO']);
        OnboardingStep::query()->delete();
        OnboardingStep::create(['name' => 'Review', 'slug' => 'review', 'component_key' => 'review', 'order' => 1, 'is_active' => true]);
        Admin::create(['name' => 'Reviewer', 'email' => 'admin@test.com', 'password' => 'x', 'is_active' => true]);

        $service = app(OnboardingService::class);
        $onboarding = $service->initializeForUser($user);
        foreach ($onboarding->steps as $step) {
            $service->completeStep($onboarding->fresh(), $step);
        }

        Sanctum::actingAs($user);
        $events = $this->getJson('/api/onboarding/timeline')->assertOk()->json('data');

        $underReview = collect($events)->firstWhere('event', 'under_review');
        $this->assertSame('Typically decided within 2 days', $underReview['estimate']);
    }
}
