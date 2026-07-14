<?php

namespace Tests\Feature;

use App\Mail\NewMessageMail;
use App\Mail\OnboardingDecisionMail;
use App\Mail\OnboardingSubmittedClientMail;
use App\Models\Admin;
use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
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
        $onboarding = $this->service->initializeForUser($this->user->fresh());
        foreach ($onboarding->steps as $step) {
            $this->service->completeStep($onboarding->fresh(), $step);
        }

        return $onboarding->fresh();
    }

    public function test_defaults_are_all_enabled(): void
    {
        Sanctum::actingAs($this->user);
        $prefs = $this->getJson('/api/onboarding/notification-preferences')->assertOk()->json('data');

        $this->assertCount(4, $prefs);
        $this->assertTrue(collect($prefs)->every(fn ($p) => $p['enabled'] === true));
    }

    public function test_muted_categories_suppress_only_their_emails(): void
    {
        $this->user->update(['notification_preferences' => [
            'submission' => false,
            'messages' => false,
        ]]);

        $onboarding = $this->submit();

        // Submission confirmation muted; decision email still allowed.
        Mail::assertNotQueued(OnboardingSubmittedClientMail::class);
        $this->service->approve($onboarding, $this->admin);
        Mail::assertQueued(OnboardingDecisionMail::class, fn ($m) => $m->hasTo('client@test.com'));

        // Admin reply email muted, but admins still get client-message mail.
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.user-onboardings.messages.reply', $onboarding), ['body' => 'Hello']);
        Mail::assertNotQueued(NewMessageMail::class, fn ($m) => $m->hasTo('client@test.com'));

        Sanctum::actingAs($this->user);
        $this->postJson('/api/onboarding/messages', ['body' => 'Hi']);
        Mail::assertQueued(NewMessageMail::class, fn ($m) => $m->hasTo('admin@test.com'));
    }

    public function test_muted_decisions_suppress_decision_email(): void
    {
        $this->user->update(['notification_preferences' => ['decisions' => false]]);

        $onboarding = $this->submit();
        Mail::assertQueued(OnboardingSubmittedClientMail::class); // submission still on

        $this->service->reject($onboarding, $this->admin, 'Nope.');
        Mail::assertNotQueued(OnboardingDecisionMail::class);
    }

    public function test_preferences_update_and_validation(): void
    {
        Sanctum::actingAs($this->user);

        $this->putJson('/api/onboarding/notification-preferences', [
            'preferences' => [
                ['key' => 'messages', 'enabled' => false],
                ['key' => 'submission', 'enabled' => true],
            ],
        ])->assertOk();

        $this->assertFalse($this->user->fresh()->wantsEmail('messages'));
        $this->assertTrue($this->user->fresh()->wantsEmail('submission'));
        $this->assertTrue($this->user->fresh()->wantsEmail('decisions')); // untouched default

        $this->putJson('/api/onboarding/notification-preferences', [
            'preferences' => [['key' => 'not_a_category', 'enabled' => false]],
        ])->assertStatus(422);
    }
}
