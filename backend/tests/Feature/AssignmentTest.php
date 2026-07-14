<?php

namespace Tests\Feature;

use App\Mail\OnboardingAssignedMail;
use App\Models\Admin;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Admin $alice;
    private Admin $bob;
    private UserOnboarding $onboarding;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->alice = Admin::create(['name' => 'Alice Admin', 'email' => 'alice@test.com', 'password' => 'x', 'is_active' => true]);
        $this->bob = Admin::create(['name' => 'Bob Admin', 'email' => 'bob@test.com', 'password' => 'x', 'is_active' => true]);

        $user = User::create(['email' => 'client@test.com', 'name' => 'Test Client', 'position' => 'CFO']);
        $type = UserType::create(['name' => 'Corporate', 'slug' => 'corporate', 'order' => 1, 'is_active' => true]);
        $this->onboarding = UserOnboarding::create([
            'user_id' => $user->id,
            'user_type_id' => $type->id,
            'status' => 'completed',
            'started_at' => now(),
        ]);
    }

    public function test_assigning_notifies_the_assignee_by_email(): void
    {
        $this->actingAs($this->alice, 'admin')
            ->post(route('admin.user-onboardings.assign', $this->onboarding), ['assigned_to' => $this->bob->id])
            ->assertRedirect();

        $this->assertSame($this->bob->id, $this->onboarding->fresh()->assigned_to);
        Mail::assertQueued(OnboardingAssignedMail::class, fn ($m) => $m->hasTo('bob@test.com'));
    }

    public function test_self_assignment_sends_no_email(): void
    {
        $this->actingAs($this->alice, 'admin')
            ->post(route('admin.user-onboardings.assign', $this->onboarding), ['assigned_to' => $this->alice->id])
            ->assertRedirect();

        $this->assertSame($this->alice->id, $this->onboarding->fresh()->assigned_to);
        Mail::assertNothingQueued();
    }

    public function test_assignment_can_be_cleared(): void
    {
        $this->onboarding->update(['assigned_to' => $this->bob->id]);

        $this->actingAs($this->alice, 'admin')
            ->post(route('admin.user-onboardings.assign', $this->onboarding), ['assigned_to' => ''])
            ->assertRedirect();

        $this->assertNull($this->onboarding->fresh()->assigned_to);
    }

    public function test_list_filters_by_assignee(): void
    {
        $this->onboarding->update(['assigned_to' => $this->bob->id]);

        $other = User::create(['email' => 'other@test.com', 'name' => 'Other Client', 'position' => 'CEO']);
        UserOnboarding::create([
            'user_id' => $other->id,
            'user_type_id' => $this->onboarding->user_type_id,
            'status' => 'completed',
            'started_at' => now(),
        ]);

        $this->actingAs($this->bob, 'admin')
            ->get(route('admin.user-onboardings.index', ['assigned' => 'me']))
            ->assertSee('Test Client')
            ->assertDontSee('Other Client');

        $this->actingAs($this->bob, 'admin')
            ->get(route('admin.user-onboardings.index', ['assigned' => 'unassigned']))
            ->assertSee('Other Client')
            ->assertDontSee('Test Client');
    }

    public function test_assignment_email_renders_with_review_link(): void
    {
        $this->onboarding->load(['user', 'userType']);
        $html = (new OnboardingAssignedMail($this->onboarding, $this->alice))->render();

        $this->assertStringContainsString('Alice Admin assigned you', $html);
        $this->assertStringContainsString($this->onboarding->reference, $html);
        $this->assertStringContainsString(route('admin.user-onboardings.show', $this->onboarding), $html);
    }

    public function test_csv_export_includes_assignee(): void
    {
        $this->onboarding->update(['assigned_to' => $this->bob->id]);

        $csv = $this->actingAs($this->alice, 'admin')
            ->get(route('admin.user-onboardings.export-csv'))
            ->streamedContent();

        $this->assertStringContainsString('Assigned To', $csv);
        $this->assertStringContainsString('Bob Admin', $csv);
    }
}
