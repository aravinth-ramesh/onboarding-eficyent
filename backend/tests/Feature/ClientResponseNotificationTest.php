<?php

namespace Tests\Feature;

use App\Mail\ClientRespondedMail;
use App\Models\Admin;
use App\Models\OnboardingStep;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Services\NotificationService;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientResponseNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Admin $requester;
    private Admin $other;
    private $onboarding;
    private UserAnswer $answer;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');
        config(['onboarding_uploads.disk' => 'local', 'document_validation.enabled' => false]);

        OnboardingStep::query()->delete();
        OnboardingStep::create(['name' => 'Review', 'slug' => 'review', 'component_key' => 'review', 'order' => 1, 'is_active' => true]);

        $this->user = User::create(['email' => 'client@test.com', 'name' => 'Test Client', 'position' => 'CFO']);
        $this->requester = Admin::create(['name' => 'Requester', 'email' => 'requester@test.com', 'password' => 'x', 'is_active' => true]);
        $this->other = Admin::create(['name' => 'Other', 'email' => 'other@test.com', 'password' => 'x', 'is_active' => true]);

        $this->onboarding = app(OnboardingService::class)->initializeForUser($this->user);

        $group = QuestionGroup::create(['name' => 'Docs', 'slug' => 'docs', 'order' => 1, 'is_active' => true]);
        $question = Question::create([
            'question_group_id' => $group->id, 'label' => 'VAT Number',
            'type' => 'text', 'is_required' => true, 'order' => 1, 'is_active' => true,
        ]);
        $this->answer = UserAnswer::create([
            'user_id' => $this->user->id, 'question_id' => $question->id,
            'user_onboarding_id' => $this->onboarding->id, 'value' => 'WRONG-123',
        ]);
    }

    public function test_client_response_notifies_all_active_admins_when_unassigned(): void
    {
        $service = app(NotificationService::class);
        $notification = $service->createChangeRequest($this->requester, $this->answer, 'Please correct the VAT number.');

        Mail::fake(); // ignore the change-request mail itself

        Sanctum::actingAs($this->user);
        $this->postJson("/api/notifications/{$notification->id}/resolve", ['value' => 'GB-123456789'])
            ->assertOk();

        Mail::assertQueued(ClientRespondedMail::class, fn ($m) => $m->hasTo('requester@test.com'));
        Mail::assertQueued(ClientRespondedMail::class, fn ($m) => $m->hasTo('other@test.com'));
    }

    public function test_response_goes_only_to_the_assigned_reviewer(): void
    {
        $this->onboarding->update(['assigned_to' => $this->other->id]);

        $service = app(NotificationService::class);
        $notification = $service->createChangeRequest($this->requester, $this->answer, 'Fix it.');

        Mail::fake();

        Sanctum::actingAs($this->user);
        $this->postJson("/api/notifications/{$notification->id}/resolve", ['value' => 'GB-999'])->assertOk();

        Mail::assertQueued(ClientRespondedMail::class, fn ($m) => $m->hasTo('other@test.com'));
        Mail::assertNotQueued(ClientRespondedMail::class, fn ($m) => $m->hasTo('requester@test.com'));
    }

    public function test_file_response_notifies_with_filenames(): void
    {
        $service = app(NotificationService::class);
        $notification = $service->createChangeRequest($this->requester, $this->answer, 'Send the certificate.');

        Mail::fake();

        Sanctum::actingAs($this->user);
        $this->post("/api/notifications/{$notification->id}/resolve-upload", [
            'files' => [UploadedFile::fake()->create('vat-certificate.pdf', 40, 'application/pdf')],
        ])->assertOk();

        Mail::assertQueued(ClientRespondedMail::class, function ($mail) {
            return str_contains($mail->summary, 'vat-certificate.pdf');
        });
    }

    public function test_response_email_renders_with_review_link(): void
    {
        $service = app(NotificationService::class);
        $notification = $service->createChangeRequest($this->requester, $this->answer, 'Fix.');
        $notification->update(['status' => 'resolved']);

        $html = (new ClientRespondedMail($notification->fresh(), 'GB-123456789'))->render();

        $this->assertStringContainsString('Test Client', $html);
        $this->assertStringContainsString('VAT Number', $html);
        $this->assertStringContainsString('GB-123456789', $html);
        $this->assertStringContainsString(route('admin.user-onboardings.show', $this->onboarding), $html);
    }

    public function test_dashboard_surfaces_client_responses(): void
    {
        $service = app(NotificationService::class);
        $notification = $service->createChangeRequest($this->requester, $this->answer, 'Fix.');

        Sanctum::actingAs($this->user);
        $this->postJson("/api/notifications/{$notification->id}/resolve", ['value' => 'GB-123456789']);

        $this->actingAs($this->requester, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Client Responses Awaiting Check')
            ->assertSee('VAT Number');
    }

    public function test_checking_a_response_clears_it_from_the_queue(): void
    {
        $service = app(NotificationService::class);
        $notification = $service->createChangeRequest($this->requester, $this->answer, 'Fix.');

        Sanctum::actingAs($this->user);
        $this->postJson("/api/notifications/{$notification->id}/resolve", ['value' => 'GB-123456789']);

        // Before checking: in the queue.
        $this->actingAs($this->requester, 'admin')
            ->get(route('admin.dashboard'))
            ->assertSee('Client Responses Awaiting Check');

        $this->actingAs($this->requester, 'admin')
            ->post(route('admin.notifications.check', $notification))
            ->assertRedirect(route('admin.dashboard'));

        $notification->refresh();
        $this->assertNotNull($notification->checked_at);
        $this->assertSame($this->requester->id, $notification->checked_by);

        // After checking: queue is empty and the card disappears.
        $this->actingAs($this->requester, 'admin')
            ->get(route('admin.dashboard'))
            ->assertDontSee('Client Responses Awaiting Check');
    }

    public function test_unanswered_requests_cannot_be_checked(): void
    {
        $service = app(NotificationService::class);
        $notification = $service->createChangeRequest($this->requester, $this->answer, 'Fix.');

        $this->actingAs($this->requester, 'admin')
            ->from(route('admin.dashboard'))
            ->post(route('admin.notifications.check', $notification))
            ->assertSessionHas('error');

        $this->assertNull($notification->fresh()->checked_at);
    }

    public function test_review_page_shows_needs_check_then_checked(): void
    {
        $service = app(NotificationService::class);
        $notification = $service->createChangeRequest($this->requester, $this->answer, 'Fix.');

        Sanctum::actingAs($this->user);
        $this->postJson("/api/notifications/{$notification->id}/resolve", ['value' => 'GB-123456789']);

        $this->actingAs($this->requester, 'admin')
            ->get(route('admin.user-onboardings.show', $this->onboarding))
            ->assertSee('Needs check');

        $notification->update(['checked_at' => now(), 'checked_by' => $this->requester->id]);

        $this->actingAs($this->requester, 'admin')
            ->get(route('admin.user-onboardings.show', $this->onboarding))
            ->assertSee('Checked')
            ->assertDontSee('Needs check');
    }
}
