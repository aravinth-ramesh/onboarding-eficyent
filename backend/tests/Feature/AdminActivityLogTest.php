<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->admin = Admin::create(['name' => 'Reviewer', 'email' => 'admin@test.com', 'password' => 'x', 'is_active' => true]);
    }

    private function submittedOnboarding()
    {
        OnboardingStep::query()->delete();
        OnboardingStep::create(['name' => 'Review', 'slug' => 'review', 'component_key' => 'review', 'order' => 1, 'is_active' => true]);

        $user = User::create(['email' => 'client@test.com', 'name' => 'Test Client', 'position' => 'CFO']);
        $service = app(OnboardingService::class);
        $onboarding = $service->initializeForUser($user);
        foreach ($onboarding->steps as $step) {
            $service->completeStep($onboarding->fresh(), $step);
        }

        return $onboarding->fresh();
    }

    public function test_state_changing_admin_actions_are_logged_with_subject_and_payload(): void
    {
        $onboarding = $this->submittedOnboarding();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.user-onboardings.notes.store', $onboarding), ['note' => 'Checking registries.'])
            ->assertRedirect();

        $log = AdminActivityLog::where('action', 'admin.user-onboardings.notes.store')->first();
        $this->assertNotNull($log);
        $this->assertSame($this->admin->id, $log->admin_id);
        $this->assertSame('UserOnboarding', $log->subject_type);
        $this->assertSame($onboarding->id, $log->subject_id);
        $this->assertSame('Checking registries.', $log->payload['note']);
        $this->assertSame(302, $log->status);
        $this->assertArrayNotHasKey('_token', $log->payload);
    }

    public function test_get_requests_are_not_logged(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->assertSame(0, AdminActivityLog::count());
    }

    public function test_decisions_and_bulk_actions_appear_in_the_trail(): void
    {
        $onboarding = $this->submittedOnboarding();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.user-onboardings.approve', $onboarding), ['comment' => 'Fine.']);

        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'admin.user-onboardings.approve',
            'subject_type' => 'UserOnboarding',
            'subject_id' => $onboarding->id,
        ]);
    }

    public function test_activity_page_renders_with_filters(): void
    {
        $onboarding = $this->submittedOnboarding();
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.user-onboardings.notes.store', $onboarding), ['note' => 'A note.']);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.admin-activity.index', ['action' => 'notes.store']))
            ->assertOk()
            ->assertSee('admin.user-onboardings.notes.store')
            ->assertSee('Reviewer')
            ->assertSee('UserOnboarding #' . $onboarding->id);

        // Filter that matches nothing.
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.admin-activity.index', ['action' => 'no-such-action']))
            ->assertOk()
            ->assertSee('No admin activity recorded yet.');
    }
}
