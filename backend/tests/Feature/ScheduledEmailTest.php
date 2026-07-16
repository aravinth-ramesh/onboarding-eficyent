<?php

namespace Tests\Feature;

use App\Mail\AdminNotificationMail;
use App\Models\Admin;
use App\Models\ScheduledEmail;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ScheduledEmailTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private UserOnboarding $a;
    private UserOnboarding $b;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->admin = Admin::create(['name' => 'Sender', 'email' => 'admin@test.com', 'password' => 'x', 'is_active' => true]);
        $type = UserType::create(['name' => 'Corporate', 'slug' => 'corporate', 'order' => 1, 'is_active' => true]);

        $alice = User::create(['email' => 'alice@test.com', 'name' => 'Alice', 'position' => 'CFO']);
        $this->a = UserOnboarding::create(['user_id' => $alice->id, 'user_type_id' => $type->id, 'status' => 'approved', 'started_at' => now()]);

        $bob = User::create(['email' => 'bob@test.com', 'name' => 'Bob', 'position' => 'CEO']);
        $this->b = UserOnboarding::create(['user_id' => $bob->id, 'user_type_id' => $type->id, 'status' => 'in_progress', 'started_at' => now()]);
    }

    public function test_scheduling_stores_a_pending_email_and_sends_nothing_yet(): void
    {
        $sendAt = now()->addHours(3);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.user-onboardings.bulk-email'), [
                'ids' => [$this->a->id, $this->b->id],
                'subject' => 'Reminder for {{name}}',
                'body' => 'Hello {{name}}, about {{reference}}.',
                'send_at' => $sendAt->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('admin.user-onboardings.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('scheduled_emails', [
            'status' => 'pending',
            'subject' => 'Reminder for {{name}}',
            'sent_count' => null,
        ]);
        Mail::assertNothingQueued();
    }

    public function test_the_command_sends_due_emails_with_per_recipient_placeholders(): void
    {
        $scheduled = ScheduledEmail::create([
            'admin_id' => $this->admin->id,
            'subject' => 'Notice for {{name}}',
            'body' => 'Hello {{name}}, ref {{reference}}.',
            'onboarding_ids' => [$this->a->id, $this->b->id],
            'send_at' => now()->addHour(),
            'status' => 'pending',
        ]);

        // Not due yet.
        $this->artisan('emails:process-scheduled')->assertOk();
        Mail::assertNothingQueued();

        // Time passes.
        $this->travelTo(now()->addHours(2));
        $this->artisan('emails:process-scheduled')->assertOk();

        Mail::assertQueued(AdminNotificationMail::class, fn ($m) => $m->hasTo('alice@test.com') && $m->emailSubject === 'Notice for Alice');
        Mail::assertQueued(AdminNotificationMail::class, fn ($m) => $m->hasTo('bob@test.com') && $m->emailSubject === 'Notice for Bob');

        $scheduled->refresh();
        $this->assertSame('sent', $scheduled->status);
        $this->assertSame(2, $scheduled->sent_count);
        $this->assertNotNull($scheduled->processed_at);
    }

    public function test_a_second_run_does_not_resend(): void
    {
        ScheduledEmail::create([
            'admin_id' => $this->admin->id, 'subject' => 'S', 'body' => 'B',
            'onboarding_ids' => [$this->a->id], 'send_at' => now()->subMinute(), 'status' => 'pending',
        ]);

        $this->artisan('emails:process-scheduled');
        Mail::assertQueued(AdminNotificationMail::class, 1);

        $this->artisan('emails:process-scheduled');
        Mail::assertQueued(AdminNotificationMail::class, 1); // still one, not two
    }

    public function test_cancelled_emails_are_never_sent(): void
    {
        $scheduled = ScheduledEmail::create([
            'admin_id' => $this->admin->id, 'subject' => 'S', 'body' => 'B',
            'onboarding_ids' => [$this->a->id], 'send_at' => now()->addHour(), 'status' => 'pending',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.scheduled-emails.cancel', $scheduled))
            ->assertRedirect();
        $this->assertSame('cancelled', $scheduled->fresh()->status);

        $this->travelTo(now()->addHours(2));
        $this->artisan('emails:process-scheduled');
        Mail::assertNothingQueued();
    }

    public function test_a_past_send_at_is_rejected(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.user-onboardings.index'))
            ->post(route('admin.user-onboardings.bulk-email'), [
                'ids' => [$this->a->id], 'subject' => 'S', 'body' => 'B',
                'send_at' => now()->subHour()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasErrors('send_at');
    }

    public function test_management_page_lists_and_cancels(): void
    {
        ScheduledEmail::create([
            'admin_id' => $this->admin->id, 'subject' => 'Quarterly notice', 'body' => 'B',
            'onboarding_ids' => [$this->a->id], 'send_at' => now()->addDay(), 'status' => 'pending',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.scheduled-emails.index'))
            ->assertOk()
            ->assertSee('Quarterly notice')
            ->assertSee('Pending');
    }
}
