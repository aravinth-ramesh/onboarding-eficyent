<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\QuestionTypeMapping;
use App\Models\User;
use App\Models\UserType;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Client questions endpoint hardening (bug report EOP-60 / EOP-61).
 */
class OnboardingQuestionsEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UserType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['email' => 'client@t.com', 'name' => 'Client', 'position' => 'CFO']);
        app(OnboardingService::class)->initializeForUser($this->user);
        $this->type = UserType::create(['name' => 'Corporate', 'slug' => 'corporate', 'order' => 1, 'is_active' => true]);
        app(OnboardingService::class)->setUserType($this->user->onboarding, $this->type->id, null);
    }

    private function mapQuestion(QuestionGroup $group): Question
    {
        $q = Question::create(['question_group_id' => $group->id, 'label' => 'Q '.$group->slug, 'type' => 'text', 'is_required' => true, 'order' => 1, 'is_active' => true]);
        QuestionTypeMapping::create(['question_id' => $q->id, 'user_type_id' => $this->type->id, 'is_required' => true, 'order' => 1, 'is_active' => true]);

        return $q;
    }

    public function test_inactive_question_group_is_hidden_from_the_client(): void
    {
        $active = QuestionGroup::create(['name' => 'Active Group', 'slug' => 'active', 'order' => 1, 'is_active' => true]);
        $inactive = QuestionGroup::create(['name' => 'Inactive Group', 'slug' => 'inactive', 'order' => 2, 'is_active' => false]);
        $this->mapQuestion($active);
        $this->mapQuestion($inactive);

        Sanctum::actingAs($this->user);
        $response = $this->getJson('/api/onboarding/questions')->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Active Group'));
        $this->assertFalse($names->contains('Inactive Group'), 'inactive groups must not reach the client');
    }

    public function test_deleting_a_group_does_not_500_the_questions_endpoint(): void
    {
        $group = QuestionGroup::create(['name' => 'Docs', 'slug' => 'docs', 'order' => 1, 'is_active' => true]);
        $this->mapQuestion($group);
        // Group removed out from under an active mapping.
        $group->delete();

        Sanctum::actingAs($this->user);
        $this->getJson('/api/onboarding/questions')->assertOk();
    }
}
