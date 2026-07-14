<?php

namespace Tests\Feature;

use App\Mail\NewMessageMail;
use App\Models\Admin;
use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Admin $admin;
    private $onboarding;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->user = User::create(['email' => 'client@test.com', 'name' => 'Test Client', 'position' => 'CFO']);
        $this->admin = Admin::create(['name' => 'Reviewer', 'email' => 'admin@test.com', 'password' => 'x', 'is_active' => true]);
        Admin::create(['name' => 'Inactive', 'email' => 'gone@test.com', 'password' => 'x', 'is_active' => false]);

        OnboardingStep::query()->delete();
        OnboardingStep::create(['name' => 'Review', 'slug' => 'review', 'component_key' => 'review', 'order' => 1, 'is_active' => true]);

        $this->onboarding = app(OnboardingService::class)->initializeForUser($this->user);
    }

    public function test_client_message_notifies_active_admins_and_shows_in_the_panel(): void
    {
        Sanctum::actingAs($this->user);
        $this->postJson('/api/onboarding/messages', ['body' => 'Which documents do you still need from us?'])
            ->assertStatus(201);

        Mail::assertQueued(NewMessageMail::class, fn ($m) => $m->hasTo('admin@test.com'));
        Mail::assertNotQueued(NewMessageMail::class, fn ($m) => $m->hasTo('gone@test.com'));

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.user-onboardings.show', $this->onboarding))
            ->assertOk()
            ->assertSee('Messages with Client')
            ->assertSee('Which documents do you still need from us?');
    }

    public function test_admin_reply_reaches_the_client_with_email_and_unread_count(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.user-onboardings.messages.reply', $this->onboarding), [
                'body' => 'We still need the certified register extract.',
            ])
            ->assertRedirect();

        Mail::assertQueued(NewMessageMail::class, fn ($m) => $m->hasTo('client@test.com'));

        Sanctum::actingAs($this->user);
        $this->getJson('/api/onboarding/messages/unread-count')->assertJson(['count' => 1]);

        $messages = $this->getJson('/api/onboarding/messages')->assertOk()->json('data');
        $this->assertCount(1, $messages);
        $this->assertSame('admin', $messages[0]['sender_type']);
        $this->assertSame('Reviewer', $messages[0]['sender_name']);

        $this->postJson('/api/onboarding/messages/read')->assertOk();
        $this->getJson('/api/onboarding/messages/unread-count')->assertJson(['count' => 0]);
    }

    public function test_viewing_the_admin_page_marks_client_messages_read(): void
    {
        Sanctum::actingAs($this->user);
        $this->postJson('/api/onboarding/messages', ['body' => 'Hello?']);

        $this->assertSame(1, $this->onboarding->messages()->whereNull('read_at')->count());

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.user-onboardings.show', $this->onboarding));

        $this->assertSame(0, $this->onboarding->messages()->whereNull('read_at')->count());
    }

    public function test_message_emails_render_in_both_directions(): void
    {
        config(['app.frontend_url' => 'https://portal.example.com']);

        $clientMessage = $this->onboarding->messages()->create([
            'sender_type' => 'client', 'user_id' => $this->user->id, 'body' => 'A question about fees.',
        ]);
        $adminReply = $this->onboarding->messages()->create([
            'sender_type' => 'admin', 'admin_id' => $this->admin->id, 'body' => 'Fees are on the schedule.',
        ]);

        // Mail::fake() never renders views, so render() here catches template
        // errors (e.g. colliding with Laravel's injected $message variable).
        $toAdmin = (new NewMessageMail($clientMessage))->render();
        $this->assertStringContainsString('A question about fees.', $toAdmin);
        $this->assertStringContainsString(route('admin.user-onboardings.show', $this->onboarding), $toAdmin);

        $toClient = (new NewMessageMail($adminReply))->render();
        $this->assertStringContainsString('Fees are on the schedule.', $toClient);
        $this->assertStringContainsString('https://portal.example.com/home?messages=1', $toClient);
    }

    public function test_clients_only_see_their_own_thread(): void
    {
        Sanctum::actingAs($this->user);
        $this->postJson('/api/onboarding/messages', ['body' => 'Private question about Acme.']);

        $other = User::create(['email' => 'other@test.com', 'name' => 'Other Client', 'position' => 'CEO']);
        app(OnboardingService::class)->initializeForUser($other);

        Sanctum::actingAs($other);
        $this->getJson('/api/onboarding/messages')->assertOk()->assertJsonCount(0, 'data');
    }
}
