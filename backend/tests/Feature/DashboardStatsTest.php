<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
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

    private function submitFor(string $email)
    {
        $user = User::create(['email' => $email, 'name' => 'Client ' . $email, 'position' => 'CFO']);
        $onboarding = $this->service->initializeForUser($user);
        foreach ($onboarding->steps as $step) {
            $this->service->completeStep($onboarding->fresh(), $step);
        }

        return $onboarding->fresh();
    }

    public function test_dashboard_shows_decision_pipeline_and_activity(): void
    {
        $approved = $this->submitFor('a@test.com');
        $this->service->approve($approved, $this->admin, 'Looks good.');

        $rejected = $this->submitFor('b@test.com');
        $this->service->reject($rejected, $this->admin, 'Missing documents.');

        $this->submitFor('c@test.com'); // awaiting review

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Awaiting Review')
            ->assertSee('Approvals (30 days)')
            ->assertSee('Rejections (30 days)')
            ->assertSee('Avg. Time to Decision')
            ->assertSee('Recent Decisions')
            ->assertSee('Looks good.')
            ->assertSee('Missing documents.');

        $this->assertSame(1, $response->viewData('stats')['onboardings_approved']);
        $this->assertSame(1, $response->viewData('stats')['onboardings_rejected']);
        $this->assertSame(1, $response->viewData('stats')['onboardings_completed']);
        $this->assertSame(1, $response->viewData('decisionStats')['approved_30d']);
        $this->assertSame(1, $response->viewData('decisionStats')['rejected_30d']);
        $this->assertNotNull($response->viewData('decisionStats')['avg_decision_hours']);
    }

    public function test_dashboard_shows_per_admin_workload(): void
    {
        $bob = Admin::create(['name' => 'Bob Admin', 'email' => 'bob@test.com', 'password' => 'x', 'is_active' => true]);
        Admin::create(['name' => 'Inactive', 'email' => 'gone@test.com', 'password' => 'x', 'is_active' => false]);

        // Reviewer decides one; Bob holds one open; one open is unassigned.
        config(['onboarding.auto_assign_submissions' => false]);
        $decided = $this->submitFor('d@test.com');
        $this->service->approve($decided, $this->admin, 'ok');

        $this->submitFor('open@test.com')->update(['assigned_to' => $bob->id]);
        $this->submitFor('nobody@test.com');

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Team Workload')
            ->assertSee('1 awaiting review unassigned')
            ->assertDontSee('Inactive');

        $workload = $response->viewData('workload')->keyBy(fn ($r) => $r->admin->id);
        $this->assertSame(1, $workload[$bob->id]->open);
        $this->assertSame(1, $workload[$this->admin->id]->approved_30d);
        $this->assertSame(0, $workload[$this->admin->id]->open);
    }

    public function test_dashboard_renders_cleanly_with_no_decisions(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('No decisions yet.');
    }
}
