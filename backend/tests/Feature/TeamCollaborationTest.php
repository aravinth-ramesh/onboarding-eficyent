<?php

namespace Tests\Feature;

use App\Mail\TeamInviteMail;
use App\Models\OnboardingStep;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamCollaborationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private $onboarding;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        OnboardingStep::query()->delete();
        OnboardingStep::create(['name' => 'Review', 'slug' => 'review', 'component_key' => 'review', 'order' => 1, 'is_active' => true]);

        $this->owner = User::create(['email' => 'owner@test.com', 'name' => 'Owner', 'position' => 'CFO']);
        $this->onboarding = app(OnboardingService::class)->initializeForUser($this->owner);
    }

    private function inviteColleague(string $email = 'colleague@test.com'): User
    {
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/onboarding/team/invite', ['email' => $email])->assertStatus(201);

        return User::where('email', $email)->firstOrFail();
    }

    public function test_owner_invites_a_colleague_who_lands_on_the_shared_application(): void
    {
        $colleague = $this->inviteColleague();

        Mail::assertQueued(TeamInviteMail::class, fn ($m) => $m->hasTo('colleague@test.com'));

        // The invitee resolves to the owner's onboarding, not a fresh one.
        Sanctum::actingAs($colleague);
        $this->getJson('/api/onboarding/status')
            ->assertOk()
            ->assertJsonPath('data.id', $this->onboarding->id);

        $this->assertNull($colleague->fresh()->onboarding);
    }

    public function test_collaborators_edit_shared_answers_with_editor_audit(): void
    {
        $group = QuestionGroup::create(['name' => 'Docs', 'slug' => 'docs', 'order' => 1, 'is_active' => true]);
        $question = Question::create([
            'question_group_id' => $group->id, 'label' => 'Entity name',
            'type' => 'text', 'is_required' => true, 'order' => 1, 'is_active' => true,
        ]);

        Sanctum::actingAs($this->owner);
        $this->postJson('/api/onboarding/answers', [
            'answers' => [['question_id' => $question->id, 'value' => 'Acme Ltd']],
        ])->assertOk();

        $colleague = $this->inviteColleague();
        $colleague->update(['name' => 'Colleague', 'position' => 'COO']);

        Sanctum::actingAs($colleague);
        $this->postJson('/api/onboarding/answers', [
            'answers' => [['question_id' => $question->id, 'value' => 'Acme Holdings Ltd']],
        ])->assertOk();

        // One shared answer — not a duplicate per user.
        $answers = UserAnswer::where('question_id', $question->id)->get();
        $this->assertCount(1, $answers);
        $this->assertSame('Acme Holdings Ltd', $answers->first()->value);
        $this->assertSame($this->owner->id, $answers->first()->user_id);

        // The audit names the colleague as the editor.
        $log = $answers->first()->auditLogs()->latest('edited_at')->first();
        $this->assertSame($colleague->id, $log->edited_by);
    }

    public function test_collaborators_see_the_owners_answers_when_reading_questions(): void
    {
        $type = \App\Models\UserType::create(['name' => 'Corporate', 'slug' => 'corporate', 'order' => 1, 'is_active' => true]);
        $group = QuestionGroup::create(['name' => 'Docs', 'slug' => 'docs', 'order' => 1, 'is_active' => true]);
        $question = Question::create([
            'question_group_id' => $group->id, 'label' => 'Entity name',
            'type' => 'text', 'is_required' => true, 'order' => 1, 'is_active' => true,
        ]);
        \App\Models\QuestionTypeMapping::create([
            'question_id' => $question->id, 'user_type_id' => $type->id, 'is_required' => true, 'order' => 1,
        ]);
        app(OnboardingService::class)->setUserType($this->onboarding, $type->id, null);

        Sanctum::actingAs($this->owner);
        $this->postJson('/api/onboarding/answers', [
            'answers' => [['question_id' => $question->id, 'value' => 'Acme Ltd']],
        ])->assertOk();

        $colleague = $this->inviteColleague();

        // The read path must serve the shared application's answers even
        // though they were authored by the owner.
        Sanctum::actingAs($colleague);
        $groups = $this->getJson('/api/onboarding/questions')->assertOk()->json('data');
        $answer = collect($groups)->flatMap(fn ($g) => $g['questions'])
            ->firstWhere('id', $question->id)['answer'];

        $this->assertSame('Acme Ltd', $answer);
    }

    public function test_invite_guards(): void
    {
        Sanctum::actingAs($this->owner);

        // Own email.
        $this->postJson('/api/onboarding/team/invite', ['email' => 'owner@test.com'])->assertStatus(422);

        // Someone with their own application.
        $independent = User::create(['email' => 'indie@test.com', 'name' => 'Indie', 'position' => 'CEO']);
        app(OnboardingService::class)->initializeForUser($independent);
        $this->postJson('/api/onboarding/team/invite', ['email' => 'indie@test.com'])->assertStatus(422);

        // Already on a team.
        $this->inviteColleague('shared@test.com');
        $this->postJson('/api/onboarding/team/invite', ['email' => 'shared@test.com'])->assertStatus(422);

        // Collaborators cannot invite.
        $colleague = User::where('email', 'shared@test.com')->first();
        Sanctum::actingAs($colleague);
        $this->postJson('/api/onboarding/team/invite', ['email' => 'more@test.com'])->assertStatus(403);
    }

    public function test_owner_can_remove_a_collaborator(): void
    {
        $colleague = $this->inviteColleague();
        $collaboratorId = $colleague->collaboration->id;

        // Collaborator cannot remove themselves via the owner-only endpoint.
        Sanctum::actingAs($colleague);
        $this->deleteJson("/api/onboarding/team/{$collaboratorId}")->assertStatus(403);

        Sanctum::actingAs($this->owner);
        $this->deleteJson("/api/onboarding/team/{$collaboratorId}")->assertOk();

        // Removed colleague no longer resolves to the shared application.
        $this->assertNull($colleague->fresh()->activeOnboarding());
    }

    public function test_team_listing_shows_owner_and_members(): void
    {
        $this->inviteColleague();

        Sanctum::actingAs($this->owner);
        $this->getJson('/api/onboarding/team')
            ->assertOk()
            ->assertJsonPath('data.is_owner', true)
            ->assertJsonPath('data.owner.email', 'owner@test.com')
            ->assertJsonPath('data.members.0.email', 'colleague@test.com');
    }
}
