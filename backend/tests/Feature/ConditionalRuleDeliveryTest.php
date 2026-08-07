<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\ConditionalRule;
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
 * A conditional rule created in the admin panel reaches the client on the very
 * next questions fetch — no cache, no restart (bug report EOP-96).
 */
class ConditionalRuleDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private UserType $type;
    private Question $parent;
    private Question $child;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = UserType::create(['name' => 'Corp', 'slug' => 'corp', 'order' => 1, 'is_active' => true]);
        $group = QuestionGroup::create(['name' => 'Company', 'slug' => 'company', 'order' => 1, 'is_active' => true]);

        $this->parent = Question::create(['question_group_id' => $group->id, 'label' => 'Are you regulated?', 'type' => 'select', 'is_required' => true, 'order' => 1, 'is_active' => true]);
        $this->child = Question::create(['question_group_id' => $group->id, 'label' => 'Regulator name', 'type' => 'text', 'is_required' => false, 'order' => 2, 'is_active' => true]);

        foreach ([$this->parent, $this->child] as $question) {
            QuestionTypeMapping::create(['question_id' => $question->id, 'user_type_id' => $this->type->id, 'is_active' => true]);
        }
    }

    private function client(): User
    {
        $user = User::create(['email' => 'c@t.com', 'name' => 'Co', 'position' => 'CFO']);
        app(OnboardingService::class)->initializeForUser($user)->update(['user_type_id' => $this->type->id]);

        return $user;
    }

    /** @return array<string, mixed>|null the child question as the client sees it */
    private function fetchChild(User $user): ?array
    {
        Sanctum::actingAs($user);
        $payload = $this->getJson('/api/onboarding/questions')->assertOk()->json('data');

        foreach ($payload as $group) {
            foreach ($group['questions'] ?? [] as $question) {
                if ($question['id'] === $this->child->id) {
                    return $question;
                }
            }
        }

        return null;
    }

    public function test_a_rule_created_in_the_admin_panel_reaches_the_client_immediately(): void
    {
        $user = $this->client();

        $this->assertSame([], $this->fetchChild($user)['conditional_rules'], 'no rules to begin with');

        $admin = Admin::create(['name' => 'S', 'email' => 's@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::SuperAdmin]);
        $this->actingAs($admin, 'admin')->post(route('admin.conditional-rules.store'), [
            'question_id' => $this->child->id,
            'parent_question_id' => (string) $this->parent->id,
            'comparison_type' => 'equals',
            'trigger_value' => 'yes',
            'action' => 'show',
            'logical_operator' => 'and',
            'is_active' => '1',
        ])->assertRedirect();

        $rules = $this->fetchChild($user)['conditional_rules'];

        $this->assertCount(1, $rules, 'the new rule must be served without any cache clear');
        $this->assertSame($this->parent->id, $rules[0]['parent_question_id']);
        $this->assertSame('equals', $rules[0]['comparison_type']);
        $this->assertSame('yes', $rules[0]['trigger_value']);
        $this->assertSame('show', $rules[0]['action']);
    }

    public function test_an_updated_rule_is_reflected_on_the_next_fetch(): void
    {
        $user = $this->client();
        $rule = ConditionalRule::create([
            'question_id' => $this->child->id, 'parent_question_id' => $this->parent->id,
            'comparison_type' => 'equals', 'trigger_value' => 'yes',
            'action' => 'show', 'logical_operator' => 'and', 'is_active' => true,
        ]);

        $admin = Admin::create(['name' => 'S', 'email' => 's@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::SuperAdmin]);
        $this->actingAs($admin, 'admin')->put(route('admin.conditional-rules.update', $rule), [
            'question_id' => $this->child->id,
            'parent_question_id' => (string) $this->parent->id,
            'comparison_type' => 'not_equals',
            'trigger_value' => 'no',
            'action' => 'hide',
            'logical_operator' => 'and',
            'is_active' => '1',
        ])->assertRedirect();

        $rules = $this->fetchChild($user)['conditional_rules'];

        $this->assertSame('not_equals', $rules[0]['comparison_type']);
        $this->assertSame('no', $rules[0]['trigger_value']);
        $this->assertSame('hide', $rules[0]['action']);
    }

    public function test_a_deactivated_rule_stops_being_served(): void
    {
        $user = $this->client();
        ConditionalRule::create([
            'question_id' => $this->child->id, 'parent_question_id' => $this->parent->id,
            'comparison_type' => 'equals', 'trigger_value' => 'yes',
            'action' => 'show', 'logical_operator' => 'and', 'is_active' => false,
        ]);

        $this->assertSame([], $this->fetchChild($user)['conditional_rules']);
    }
}
