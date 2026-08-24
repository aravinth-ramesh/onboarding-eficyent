<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use App\Models\UserType;
use App\Services\AnswerService;
use App\Support\CompanyName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin lists identify an application by the company/entity name, not the
 * person who registered the account.
 */
class CompanyNameTest extends TestCase
{
    use RefreshDatabase;

    private UserType $type;

    private QuestionGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->type = UserType::create(['name' => 'Corporate', 'slug' => 'corporate', 'order' => 1, 'is_active' => true]);
        $this->group = QuestionGroup::create(['name' => 'Company', 'slug' => 'company', 'order' => 1, 'is_active' => true]);
    }

    private function onboarding(string $userName = 'Jane Contact'): UserOnboarding
    {
        $user = User::create(['email' => 'jane@t.com', 'name' => $userName, 'position' => 'CFO']);

        return UserOnboarding::create([
            'user_id' => $user->id, 'user_type_id' => $this->type->id,
            'status' => 'in_progress', 'started_at' => now(),
        ]);
    }

    private function question(string $label, string $type = 'text', int $order = 1): Question
    {
        return Question::create(['question_group_id' => $this->group->id, 'label' => $label, 'type' => $type, 'is_required' => true, 'order' => $order, 'is_active' => true]);
    }

    private function answer(UserOnboarding $o, Question $q, string $value): void
    {
        UserAnswer::create(['user_id' => $o->user_id, 'question_id' => $q->id, 'user_onboarding_id' => $o->id, 'value' => $value]);
    }

    public function test_resolve_picks_the_legal_entity_name_over_other_fields(): void
    {
        $o = $this->onboarding();
        $this->answer($o, $this->question('Trading Name (if different)', 'text', 2), 'Acme Trading');
        $this->answer($o, $this->question('Full Legal Entity Name', 'text', 1), 'Acme Holdings Ltd');

        $this->assertSame('Acme Holdings Ltd', CompanyName::resolve($o));
    }

    public function test_saving_answers_denormalises_the_company_name(): void
    {
        $o = $this->onboarding();
        $q = $this->question('Full Legal Entity Name');

        app(AnswerService::class)->saveBulkAnswers($o->user, $o, [
            ['question_id' => $q->id, 'value' => 'Globex Corporation'],
        ]);

        $this->assertSame('Globex Corporation', $o->refresh()->company_name);
    }

    public function test_display_name_falls_back_to_the_contact_when_no_company(): void
    {
        $o = $this->onboarding('Jane Contact');
        $this->assertNull($o->company_name);
        $this->assertSame('Jane Contact', $o->displayName);
    }

    public function test_the_list_shows_the_company_name_and_search_matches_it(): void
    {
        $manager = Admin::create(['name' => 'M', 'email' => 'm@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Manager]);
        $o = $this->onboarding('Jane Contact');
        $o->update(['company_name' => 'Umbrella Corp', 'status' => 'completed']);

        // Listed under the company name, with the contact as secondary.
        $this->actingAs($manager, 'admin')
            ->get(route('admin.user-onboardings.index'))
            ->assertOk()
            ->assertSee('Umbrella Corp')
            ->assertSee('>Company<', false);

        // Search by the company name finds it.
        $this->actingAs($manager, 'admin')
            ->get(route('admin.user-onboardings.index', ['search' => 'Umbrella']))
            ->assertOk()
            ->assertSee('Umbrella Corp');
    }
}
