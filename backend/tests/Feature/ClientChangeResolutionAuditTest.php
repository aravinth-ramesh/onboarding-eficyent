<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\AnswerAuditLog;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * When a client resolves an admin change request, the updated answer must
 * surface on the admin "Client Changes" (post-submission changes) page so the
 * reviewer can see what the client changed (bug report EOP-82). This exercises
 * the real resolve endpoint end-to-end, not just the AnswerService.
 */
class ClientChangeResolutionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolving_a_change_request_appears_on_the_client_changes_page(): void
    {
        Mail::fake();

        $type = UserType::create(['name' => 'Corp', 'slug' => 'corp', 'order' => 1, 'is_active' => true]);
        $client = User::create(['email' => 'client@t.com', 'name' => 'Acme', 'position' => 'CFO']);
        $onb = UserOnboarding::create(['user_id' => $client->id, 'user_type_id' => $type->id, 'status' => 'completed', 'started_at' => now(), 'completed_at' => now()]);
        $group = QuestionGroup::create(['name' => 'Company', 'slug' => 'company', 'order' => 1, 'is_active' => true]);
        $q = Question::create(['question_group_id' => $group->id, 'label' => 'Legal name', 'type' => 'text', 'is_required' => true, 'order' => 1, 'is_active' => true]);
        $answer = UserAnswer::create(['user_id' => $client->id, 'question_id' => $q->id, 'user_onboarding_id' => $onb->id, 'value' => 'Acme Old Ltd']);
        $admin = Admin::create(['name' => 'M', 'email' => 'm@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Manager]);

        $notification = AdminNotification::create([
            'user_id' => $client->id, 'admin_id' => $admin->id, 'type' => 'change_request',
            'user_answer_id' => $answer->id, 'message' => 'Please correct the legal name.', 'status' => 'pending',
        ]);

        // Client resolves the change request through the portal API.
        Sanctum::actingAs($client);
        $this->postJson("/api/notifications/{$notification->id}/resolve", ['value' => 'Acme Corrected Ltd'])
            ->assertOk();

        $this->assertSame('Acme Corrected Ltd', $answer->fresh()->value);
        $this->assertSame(1, AnswerAuditLog::count(), 'the resolved change must be audit-logged');

        // The reviewer sees the change on the Client Changes page.
        $compliance = Admin::create(['name' => 'C', 'email' => 'c@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Compliance]);
        $this->actingAs($compliance, 'admin')
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee($onb->reference)
            ->assertSee('Acme Old Ltd')
            ->assertSee('Acme Corrected Ltd');
    }
}
