<?php

namespace Tests\Feature;

use App\Mail\OnboardingDecisionMail;
use App\Models\Admin;
use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BulkDecisionTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private OnboardingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->admin = Admin::create(['name' => 'Reviewer', 'email' => 'admin@test.com', 'password' => 'x', 'is_active' => true]);
        $this->service = app(OnboardingService::class);

        OnboardingStep::query()->delete();
        OnboardingStep::create(['name' => 'Review', 'slug' => 'review', 'component_key' => 'review', 'order' => 1, 'is_active' => true]);
    }

    private function make(string $email, bool $submit = true)
    {
        $user = User::create(['email' => $email, 'name' => 'Client ' . $email, 'position' => 'CFO']);
        $onboarding = $this->service->initializeForUser($user);
        if ($submit) {
            foreach ($onboarding->steps as $step) {
                $this->service->completeStep($onboarding->fresh(), $step);
            }
        }

        return $onboarding->fresh();
    }

    public function test_bulk_approve_decides_eligible_and_skips_the_rest(): void
    {
        $a = $this->make('a@test.com');
        $b = $this->make('b@test.com');
        $inProgress = $this->make('c@test.com', submit: false);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.user-onboardings.bulk-decision'), [
                'decision' => 'approve',
                'ids' => [$a->id, $b->id, $inProgress->id],
            ])
            ->assertRedirect(route('admin.user-onboardings.index'))
            ->assertSessionHas('success', '2 application(s) approved. 1 skipped (not awaiting review).');

        $this->assertSame('approved', $a->fresh()->status);
        $this->assertSame('approved', $b->fresh()->status);
        $this->assertSame('pending', $inProgress->fresh()->status);

        // Each decided client gets their own decision email.
        Mail::assertQueued(OnboardingDecisionMail::class, fn ($m) => $m->hasTo('a@test.com'));
        Mail::assertQueued(OnboardingDecisionMail::class, fn ($m) => $m->hasTo('b@test.com'));
        Mail::assertNotQueued(OnboardingDecisionMail::class, fn ($m) => $m->hasTo('c@test.com'));

        // And each decision lands in the review log.
        $this->assertSame(1, $a->reviewLogs()->where('event', 'approved')->count());
    }

    public function test_bulk_reject_requires_a_comment_and_applies_it_to_all(): void
    {
        $a = $this->make('a@test.com');
        $b = $this->make('b@test.com');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.user-onboardings.bulk-decision'), [
                'decision' => 'reject',
                'ids' => [$a->id, $b->id],
            ])
            ->assertSessionHasErrors('comment');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.user-onboardings.bulk-decision'), [
                'decision' => 'reject',
                'ids' => [$a->id, $b->id],
                'comment' => 'Sanctions screening flagged the jurisdiction.',
            ])
            ->assertSessionHas('success');

        $this->assertSame('rejected', $a->fresh()->status);
        $this->assertSame('Sanctions screening flagged the jurisdiction.', $b->fresh()->decision_comment);
    }

    public function test_bulk_decision_with_no_eligible_rows_reports_an_error(): void
    {
        $inProgress = $this->make('c@test.com', submit: false);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.user-onboardings.bulk-decision'), [
                'decision' => 'approve',
                'ids' => [$inProgress->id],
            ])
            ->assertSessionHas('error', '0 application(s) approved. 1 skipped (not awaiting review).');
    }
}
