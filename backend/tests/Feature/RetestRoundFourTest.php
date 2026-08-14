<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\OnboardingCollaborator;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Support\OwnershipTotal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Retest round four: ownership cannot exceed the whole company (29), compliance
 * officers can be assigned work they are allowed to do (35), and the dashboard
 * counts the same clients the Onboardings module lists (37).
 */
class RetestRoundFourTest extends TestCase
{
    use RefreshDatabase;

    private function ownershipQuestion(): Question
    {
        $group = QuestionGroup::create(['name' => 'UBO', 'slug' => 'ubo-'.uniqid(), 'order' => 1, 'is_active' => true]);

        return Question::create([
            'question_group_id' => $group->id, 'label' => 'Ownership Structure', 'type' => 'table',
            'is_required' => false, 'order' => 1, 'is_active' => true,
            'options' => ['columns' => [
                ['key' => 'full_legal_name', 'label' => 'Full Legal Name', 'type' => 'text'],
                ['key' => '%_ownership', 'label' => '% Ownership', 'type' => 'number'],
            ]],
        ]);
    }

    public function test_two_owners_cannot_each_hold_the_whole_company(): void
    {
        $question = $this->ownershipQuestion();
        $key = OwnershipTotal::columnKey($question);

        $this->assertSame('%_ownership', $key);
        $this->assertSame(200.0, OwnershipTotal::of(
            json_encode([['%_ownership' => 100], ['%_ownership' => 100]]),
            $key,
        ));
    }

    public function test_ownership_totalling_exactly_one_hundred_is_allowed(): void
    {
        $question = $this->ownershipQuestion();

        $this->assertSame(100.0, OwnershipTotal::of(
            json_encode([['%_ownership' => 60], ['%_ownership' => 40]]),
            OwnershipTotal::columnKey($question),
        ));
    }

    public function test_the_ownership_column_is_found_by_label_when_renamed(): void
    {
        $group = QuestionGroup::create(['name' => 'G', 'slug' => 'g-'.uniqid(), 'order' => 1, 'is_active' => true]);
        $question = Question::create([
            'question_group_id' => $group->id, 'label' => 'Q', 'type' => 'table',
            'is_required' => false, 'order' => 1, 'is_active' => true,
            'options' => ['columns' => [['key' => 'stake', 'label' => 'Ownership Share', 'type' => 'number']]],
        ]);

        $this->assertSame('stake', OwnershipTotal::columnKey($question));
    }

    public function test_a_table_that_does_not_track_ownership_is_left_alone(): void
    {
        $group = QuestionGroup::create(['name' => 'G', 'slug' => 'g-'.uniqid(), 'order' => 1, 'is_active' => true]);
        $question = Question::create([
            'question_group_id' => $group->id, 'label' => 'Q', 'type' => 'table',
            'is_required' => false, 'order' => 1, 'is_active' => true,
            'options' => ['columns' => [['key' => 'bank_name', 'label' => 'Bank Name', 'type' => 'text']]],
        ]);

        $this->assertNull(OwnershipTotal::columnKey($question));
    }

    public function test_the_api_refuses_ownership_over_one_hundred_percent(): void
    {
        $question = $this->ownershipQuestion();
        $user = User::create(['email' => 'client@test.com', 'name' => 'Client', 'position' => 'CFO']);
        UserOnboarding::create([
            'user_id' => $user->id, 'reference' => 'REF-OWN', 'status' => 'in_progress', 'started_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/onboarding/answers', ['answers' => [[
                'question_id' => $question->id,
                'value' => json_encode([['%_ownership' => 100], ['%_ownership' => 100]]),
            ]]]);

        $response
            ->assertStatus(422);

        $this->assertSame(
            'Total ownership is 200%, which cannot exceed 100%.',
            $response->json('errors')['answers.0.value'][0],
        );
    }

    public function test_a_compliance_officer_can_be_assigned_work(): void
    {
        // Compliance holds REVIEW/APPROVE/REJECT but was missing from every
        // Assign To dropdown (retest item 35).
        $compliance = Admin::create([
            'name' => 'Casey', 'email' => 'compliance@test.com', 'password' => 'x',
            'is_active' => true, 'role' => AdminRole::Compliance,
        ]);

        $this->assertTrue(
            Admin::reviewers()->pluck('id')->contains($compliance->id),
            'a role that can review must be assignable',
        );
    }

    public function test_analysts_and_managers_are_still_assignable(): void
    {
        $analyst = Admin::create([
            'name' => 'Ana', 'email' => 'analyst@test.com', 'password' => 'x',
            'is_active' => true, 'role' => AdminRole::Analyst,
        ]);
        $manager = Admin::create([
            'name' => 'Morgan', 'email' => 'manager@test.com', 'password' => 'x',
            'is_active' => true, 'role' => AdminRole::Manager,
        ]);

        $ids = Admin::reviewers()->pluck('id');
        $this->assertTrue($ids->contains($analyst->id));
        $this->assertTrue($ids->contains($manager->id));
    }

    public function test_a_deactivated_reviewer_is_not_assignable(): void
    {
        $retired = Admin::create([
            'name' => 'Rex', 'email' => 'retired@test.com', 'password' => 'x',
            'is_active' => false, 'role' => AdminRole::Manager,
        ]);

        $this->assertFalse(Admin::reviewers()->pluck('id')->contains($retired->id));
    }

    public function test_the_dashboard_counts_the_same_clients_the_onboardings_list_shows(): void
    {
        $owner = User::create(['email' => 'owner@test.com', 'name' => 'Owner', 'position' => 'CEO']);
        $onboarding = UserOnboarding::create([
            'user_id' => $owner->id, 'reference' => 'REF-1', 'status' => 'in_progress', 'started_at' => now(),
        ]);

        // A collaborator and an invitation-only account both inflated the old
        // User::count() without adding an application (retest item 37).
        $colleague = User::create(['email' => 'colleague@test.com', 'name' => 'Colleague', 'position' => 'CFO']);
        OnboardingCollaborator::create([
            'user_onboarding_id' => $onboarding->id, 'user_id' => $colleague->id,
            'invited_by' => $owner->id, 'invite_token' => 'tok-'.uniqid(),
        ]);
        User::create(['email' => 'invited@test.com', 'invited_at' => now()]);

        $this->assertSame(3, User::count(), 'three user rows exist');
        $this->assertSame(
            UserOnboarding::count(),
            User::whereHas('onboarding')->count(),
            'the dashboard total must match the Onboardings total',
        );
        $this->assertSame(1, User::whereHas('onboarding')->count());
    }
}
