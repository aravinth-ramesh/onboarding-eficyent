<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\QuestionTypeMapping;
use App\Models\User;
use App\Models\UserType;
use App\Services\OnboardingService;
use App\Support\UboTableColumns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * UBO Nationality and ID Type are dropdowns everywhere they appear, not free
 * text in one widget and a dropdown in another (bug report EOP-49).
 */
class UboFieldConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function ownerTable(array $columns): Question
    {
        $group = QuestionGroup::create(['name' => 'Ownership', 'slug' => 'ownership', 'order' => 1, 'is_active' => true]);

        return Question::create([
            'question_group_id' => $group->id, 'label' => 'Ultimate Beneficial Owners List',
            'type' => 'table', 'is_required' => false, 'order' => 1, 'is_active' => true,
            'options' => ['columns' => $columns],
        ]);
    }

    public function test_free_text_nationality_becomes_a_country_dropdown_and_id_type_is_added(): void
    {
        $question = $this->ownerTable([
            ['key' => 'full_legal_name', 'label' => 'Full Legal Name', 'type' => 'text'],
            ['key' => 'nationality', 'label' => 'Nationality', 'type' => 'text'],
        ]);

        $this->assertSame(1, UboTableColumns::apply());

        $columns = collect($question->fresh()->options['columns'])->keyBy('key');

        $this->assertSame('select', $columns['nationality']['type'], 'nationality must be a dropdown');
        $this->assertGreaterThan(100, count($columns['nationality']['options']));

        $this->assertArrayHasKey('id_type', $columns, 'ID Type must exist, as it does in the ubo widget');
        $this->assertSame('select', $columns['id_type']['type']);
        $this->assertSame(
            ['passport', 'national_id', 'drivers_license', 'other'],
            array_column($columns['id_type']['options'], 'value'),
        );
    }

    public function test_applying_twice_changes_nothing_further(): void
    {
        $question = $this->ownerTable([
            ['key' => 'nationality', 'label' => 'Nationality', 'type' => 'text'],
        ]);

        UboTableColumns::apply();
        $after = $question->fresh()->options;

        $this->assertSame(0, UboTableColumns::apply());
        $this->assertSame($after, $question->fresh()->options);
    }

    public function test_a_table_without_owners_is_left_alone(): void
    {
        $question = $this->ownerTable([
            ['key' => 'bank_name', 'label' => 'Bank', 'type' => 'text'],
        ]);
        $before = $question->options;

        $this->assertSame(0, UboTableColumns::apply());
        $this->assertSame($before, $question->fresh()->options);
    }

    public function test_a_deactivated_question_is_no_longer_served_to_clients(): void
    {
        // Needed so one of the two overlapping UBO widgets can actually be
        // retired — previously only the group's is_active was honoured.
        $type = UserType::create(['name' => 'Corp', 'slug' => 'corp', 'order' => 1, 'is_active' => true]);
        $group = QuestionGroup::create(['name' => 'Ownership', 'slug' => 'ownership', 'order' => 1, 'is_active' => true]);
        $live = Question::create(['question_group_id' => $group->id, 'label' => 'Live question', 'type' => 'text', 'is_required' => false, 'order' => 1, 'is_active' => true]);
        $retired = Question::create(['question_group_id' => $group->id, 'label' => 'Retired question', 'type' => 'text', 'is_required' => false, 'order' => 2, 'is_active' => false]);

        foreach ([$live, $retired] as $question) {
            QuestionTypeMapping::create(['question_id' => $question->id, 'user_type_id' => $type->id, 'is_active' => true]);
        }

        $user = User::create(['email' => 'c@t.com', 'name' => 'Co', 'position' => 'CFO']);
        $onboarding = app(OnboardingService::class)->initializeForUser($user);
        $onboarding->update(['user_type_id' => $type->id]);

        Sanctum::actingAs($user);
        $this->getJson('/api/onboarding/questions')
            ->assertOk()
            ->assertSee('Live question')
            ->assertDontSee('Retired question');
    }
}
