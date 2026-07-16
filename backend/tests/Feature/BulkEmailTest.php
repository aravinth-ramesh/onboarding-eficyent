<?php

namespace Tests\Feature;

use App\Mail\AdminNotificationMail;
use App\Models\Admin;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BulkEmailTest extends TestCase
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

    public function test_sends_a_personalized_email_to_each_selected_client(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.user-onboardings.bulk-email'), [
                'ids' => [$this->a->id, $this->b->id],
                'subject' => 'Update for {{name}}',
                'body' => "Hello {{name}}, regarding {{reference}}.",
            ])
            ->assertRedirect(route('admin.user-onboardings.index'))
            ->assertSessionHas('success', 'Email queued to 2 client(s).');

        // Placeholders resolved per recipient.
        Mail::assertQueued(AdminNotificationMail::class, function ($mail) {
            return $mail->hasTo('alice@test.com')
                && $mail->emailSubject === 'Update for Alice'
                && str_contains($mail->emailBody, $this->a->reference);
        });
        Mail::assertQueued(AdminNotificationMail::class, fn ($m) => $m->hasTo('bob@test.com'));

        // Each send is logged.
        $this->assertDatabaseHas('admin_email_logs', ['user_id' => $this->a->user_id, 'subject' => 'Update for Alice']);
    }

    public function test_onboardings_without_a_reachable_client_are_skipped(): void
    {
        // A soft-deleted user account: the onboarding lingers but the
        // belongsTo relation returns null, so there's no one to email.
        $ghostUser = User::create(['email' => 'ghost@test.com', 'name' => 'Ghost', 'position' => 'X']);
        $ghost = UserOnboarding::create(['user_id' => $ghostUser->id, 'user_type_id' => $this->a->user_type_id, 'status' => 'pending', 'started_at' => now()]);
        $ghostUser->delete();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.user-onboardings.bulk-email'), [
                'ids' => [$this->a->id, $ghost->id],
                'subject' => 'Hi', 'body' => 'Body',
            ])
            ->assertSessionHas('success', 'Email queued to 1 client(s). 1 skipped (no email address or send failed).');

        Mail::assertQueued(AdminNotificationMail::class, 1);
    }

    public function test_subject_and_body_are_required(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.user-onboardings.index'))
            ->post(route('admin.user-onboardings.bulk-email'), ['ids' => [$this->a->id], 'subject' => '', 'body' => ''])
            ->assertSessionHasErrors(['subject', 'body']);

        Mail::assertNothingQueued();
    }

    public function test_at_least_one_recipient_is_required(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.user-onboardings.index'))
            ->post(route('admin.user-onboardings.bulk-email'), ['ids' => [], 'subject' => 'Hi', 'body' => 'Body'])
            ->assertSessionHasErrors('ids');
    }
}
