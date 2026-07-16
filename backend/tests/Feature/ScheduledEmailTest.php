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

    public function test_duplicating_creates_a_new_pending_copy_at_a_new_time(): void
    {
        $original = ScheduledEmail::create([
            'admin_id' => $this->admin->id,
            'subject' => 'Quarterly notice',
            'body' => 'Hello {{name}}, ref {{reference}}.',
            'onboarding_ids' => [$this->a->id, $this->b->id],
            'send_at' => now()->subDay(), // even a past/sent one can be duplicated
            'status' => 'sent',
        ]);

        $newTime = now()->addWeek();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.scheduled-emails.duplicate', $original), [
                'send_at' => $newTime->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('admin.scheduled-emails.index'))
            ->assertSessionHas('success');

        $this->assertSame(2, ScheduledEmail::count());
        $copy = ScheduledEmail::latest('id')->first();
        $this->assertSame('pending', $copy->status);
        $this->assertSame('Quarterly notice', $copy->subject);
        $this->assertSame($original->body, $copy->body);
        $this->assertEqualsCanonicalizing([$this->a->id, $this->b->id], $copy->onboarding_ids);
        $this->assertNull($copy->sent_count);
        $this->assertTrue($copy->send_at->equalTo($newTime->startOfSecond()));
    }

    public function test_duplicate_requires_a_future_time(): void
    {
        $original = ScheduledEmail::create([
            'admin_id' => $this->admin->id, 'subject' => 'S', 'body' => 'B',
            'onboarding_ids' => [$this->a->id], 'send_at' => now()->addDay(), 'status' => 'pending',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.scheduled-emails.index'))
            ->post(route('admin.scheduled-emails.duplicate', $original), [
                'send_at' => now()->subHour()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasErrors('send_at');

        $this->assertSame(1, ScheduledEmail::count());
    }

    public function test_preview_renders_the_email_with_the_first_recipient_substituted(): void
    {
        $scheduled = ScheduledEmail::create([
            'admin_id' => $this->admin->id,
            'subject' => 'Notice for {{name}}',
            'body' => 'Hello {{name}}, about {{reference}}. Warm regards.',
            'onboarding_ids' => [$this->a->id, $this->b->id],
            'send_at' => now()->addDay(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.scheduled-emails.preview', $scheduled))
            ->assertOk();

        $html = $response->getContent();
        // Real branded mailable, placeholders filled from the first recipient.
        $this->assertStringContainsString('Hello Alice, about ' . $this->a->reference, $html);
        $this->assertStringContainsString('Eficyent', $html);
        $this->assertStringNotContainsString('{{name}}', $html);
        $this->assertStringNotContainsString('{{reference}}', $html);
    }

    public function test_preview_falls_back_to_sample_data_when_no_recipient_is_reachable(): void
    {
        // Only recipient is a soft-deleted user → no reachable client.
        $ghostUser = User::create(['email' => 'ghost@test.com', 'name' => 'Ghost', 'position' => 'X']);
        $ghost = UserOnboarding::create(['user_id' => $ghostUser->id, 'user_type_id' => $this->a->user_type_id, 'status' => 'pending', 'started_at' => now()]);
        $ghostUser->delete();

        $scheduled = ScheduledEmail::create([
            'admin_id' => $this->admin->id, 'subject' => 'Hi {{name}}', 'body' => 'Hello {{name}}, ref {{reference}}.',
            'onboarding_ids' => [$ghost->id], 'send_at' => now()->addDay(), 'status' => 'pending',
        ]);

        $html = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.scheduled-emails.preview', $scheduled))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Jane Doe', $html);
        $this->assertStringNotContainsString('{{name}}', $html);
    }

    public function test_list_filters_by_status(): void
    {
        ScheduledEmail::create([
            'admin_id' => $this->admin->id, 'subject' => 'Pending blast', 'body' => 'B',
            'onboarding_ids' => [$this->a->id], 'send_at' => now()->addDay(), 'status' => 'pending',
        ]);
        ScheduledEmail::create([
            'admin_id' => $this->admin->id, 'subject' => 'Sent blast', 'body' => 'B',
            'onboarding_ids' => [$this->a->id], 'send_at' => now()->subDay(), 'status' => 'sent', 'sent_count' => 1,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.scheduled-emails.index', ['status' => 'pending']))
            ->assertSee('Pending blast')
            ->assertDontSee('Sent blast');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.scheduled-emails.index', ['status' => 'sent']))
            ->assertSee('Sent blast')
            ->assertDontSee('Pending blast');
    }

    public function test_search_matches_the_subject_and_combines_with_status(): void
    {
        ScheduledEmail::create([
            'admin_id' => $this->admin->id, 'subject' => 'Quarterly newsletter', 'body' => 'B',
            'onboarding_ids' => [$this->a->id], 'send_at' => now()->addDay(), 'status' => 'pending',
        ]);
        ScheduledEmail::create([
            'admin_id' => $this->admin->id, 'subject' => 'Maintenance window', 'body' => 'B',
            'onboarding_ids' => [$this->a->id], 'send_at' => now()->addDay(), 'status' => 'pending',
        ]);
        ScheduledEmail::create([
            'admin_id' => $this->admin->id, 'subject' => 'Quarterly recap', 'body' => 'B',
            'onboarding_ids' => [$this->a->id], 'send_at' => now()->subDay(), 'status' => 'sent', 'sent_count' => 1,
        ]);

        // Subject search across statuses.
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.scheduled-emails.index', ['search' => 'quarterly']))
            ->assertSee('Quarterly newsletter')
            ->assertSee('Quarterly recap')
            ->assertDontSee('Maintenance window');

        // Combined with the status filter.
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.scheduled-emails.index', ['search' => 'quarterly', 'status' => 'pending']))
            ->assertSee('Quarterly newsletter')
            ->assertDontSee('Quarterly recap')
            ->assertDontSee('Maintenance window');
    }

    public function test_csv_export_follows_the_subject_search(): void
    {
        ScheduledEmail::create([
            'admin_id' => $this->admin->id, 'subject' => 'Quarterly newsletter', 'body' => 'B',
            'onboarding_ids' => [$this->a->id], 'send_at' => now()->addDay(), 'status' => 'pending',
        ]);
        ScheduledEmail::create([
            'admin_id' => $this->admin->id, 'subject' => 'Maintenance window', 'body' => 'B',
            'onboarding_ids' => [$this->a->id], 'send_at' => now()->addDay(), 'status' => 'pending',
        ]);

        $csv = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.scheduled-emails.export-csv', ['search' => 'quarterly']))
            ->streamedContent();

        $this->assertStringContainsString('Quarterly newsletter', $csv);
        $this->assertStringNotContainsString('Maintenance window', $csv);
    }

    public function test_csv_export_follows_the_status_filter(): void
    {
        ScheduledEmail::create([
            'admin_id' => $this->admin->id, 'subject' => 'Pending blast', 'body' => 'B',
            'onboarding_ids' => [$this->a->id], 'send_at' => now()->addDay(), 'status' => 'pending',
        ]);
        ScheduledEmail::create([
            'admin_id' => $this->admin->id, 'subject' => 'Sent blast', 'body' => 'B',
            'onboarding_ids' => [$this->a->id], 'send_at' => now()->subDay(), 'status' => 'sent', 'sent_count' => 1,
        ]);

        $csv = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.scheduled-emails.export-csv', ['status' => 'pending']))
            ->streamedContent();

        $this->assertStringContainsString('Pending blast', $csv);
        $this->assertStringNotContainsString('Sent blast', $csv);
    }

    public function test_csv_export_lists_all_scheduled_emails(): void
    {
        ScheduledEmail::create([
            'admin_id' => $this->admin->id, 'subject' => 'Quarterly notice', 'body' => 'B',
            'onboarding_ids' => [$this->a->id, $this->b->id], 'send_at' => now()->addDay(), 'status' => 'pending',
        ]);
        ScheduledEmail::create([
            'admin_id' => $this->admin->id, 'subject' => 'Old blast', 'body' => 'B',
            'onboarding_ids' => [$this->a->id], 'send_at' => now()->subDay(), 'status' => 'sent', 'sent_count' => 1,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.scheduled-emails.export-csv'))
            ->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('scheduled-emails-', $response->headers->get('content-disposition'));

        $csv = $response->streamedContent();
        // fputcsv quotes fields containing spaces/parentheses.
        $this->assertStringContainsString('"Send At (UTC)",Status,Subject,Recipients', $csv);
        $this->assertStringContainsString('pending,"Quarterly notice",2', $csv);
        $this->assertStringContainsString('sent,"Old blast",1,1', $csv);
        $this->assertStringContainsString('Sender', $csv); // scheduled-by name
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
