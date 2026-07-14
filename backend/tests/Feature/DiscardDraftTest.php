<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\OnboardingStep;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiscardDraftTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private $onboarding;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        OnboardingStep::query()->delete();
        OnboardingStep::create(['name' => 'Questions', 'slug' => 'questions', 'component_key' => 'questions', 'order' => 1, 'is_active' => true]);
        OnboardingStep::create(['name' => 'Review', 'slug' => 'review', 'component_key' => 'review', 'order' => 2, 'is_active' => true]);

        $this->owner = User::create(['email' => 'owner@test.com', 'name' => 'Owner', 'position' => 'CFO']);
        $this->onboarding = app(OnboardingService::class)->initializeForUser($this->owner);
    }

    public function test_owner_discards_draft_and_gets_a_fresh_application(): void
    {
        $group = QuestionGroup::create(['name' => 'Docs', 'slug' => 'docs', 'order' => 1, 'is_active' => true]);
        $question = Question::create([
            'question_group_id' => $group->id, 'label' => 'Entity name',
            'type' => 'text', 'is_required' => true, 'order' => 1, 'is_active' => true,
        ]);
        UserAnswer::create([
            'user_id' => $this->owner->id, 'question_id' => $question->id,
            'user_onboarding_id' => $this->onboarding->id, 'value' => 'Acme Ltd',
        ]);
        $oldId = $this->onboarding->id;

        Sanctum::actingAs($this->owner);
        $response = $this->deleteJson('/api/onboarding/draft')->assertOk();

        $this->assertNotSame($oldId, $response->json('data.id'));
        $this->assertSame('pending', $response->json('data.status'));
        $this->assertDatabaseMissing('user_onboardings', ['id' => $oldId]);
        $this->assertDatabaseMissing('user_answers', ['user_onboarding_id' => $oldId]);
    }

    public function test_submitted_applications_cannot_be_discarded(): void
    {
        $service = app(OnboardingService::class);
        Admin::create(['name' => 'A', 'email' => 'a@test.com', 'password' => 'x', 'is_active' => true]);
        foreach ($this->onboarding->steps as $step) {
            $service->completeStep($this->onboarding->fresh(), $step);
        }

        Sanctum::actingAs($this->owner);
        $this->deleteJson('/api/onboarding/draft')->assertStatus(403);
        $this->assertDatabaseHas('user_onboardings', ['id' => $this->onboarding->id]);
    }

    public function test_collaborators_cannot_discard_the_shared_draft(): void
    {
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/onboarding/team/invite', ['email' => 'colleague@test.com'])->assertStatus(201);
        $colleague = User::where('email', 'colleague@test.com')->first();

        Sanctum::actingAs($colleague);
        $this->deleteJson('/api/onboarding/draft')->assertStatus(403);
        $this->assertDatabaseHas('user_onboardings', ['id' => $this->onboarding->id]);
    }

    public function test_discard_detaches_collaborators_with_the_draft(): void
    {
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/onboarding/team/invite', ['email' => 'colleague@test.com']);
        $colleague = User::where('email', 'colleague@test.com')->first();

        $this->deleteJson('/api/onboarding/draft')->assertOk();

        $this->assertNull($colleague->fresh()->activeOnboarding());
        $this->assertDatabaseMissing('onboarding_collaborators', ['user_id' => $colleague->id]);
    }
}
