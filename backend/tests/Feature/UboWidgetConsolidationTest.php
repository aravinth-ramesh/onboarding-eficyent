<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use App\Models\UserType;
use App\Support\UboTableColumns;
use App\Support\UboWidgetConsolidation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The duplicate `ubo` widget is retired onto the owner table, carrying its
 * answers across and losing no captured field (EOP-49).
 */
class UboWidgetConsolidationTest extends TestCase
{
    use RefreshDatabase;

    private QuestionGroup $group;

    private Question $table;

    private Question $ubo;

    private UserOnboarding $onboarding;

    protected function setUp(): void
    {
        parent::setUp();

        $type = UserType::create(['name' => 'Corp', 'slug' => 'corp', 'order' => 1, 'is_active' => true]);
        $user = User::create(['email' => 'c@t.com', 'name' => 'Co', 'position' => 'CFO']);
        $this->onboarding = UserOnboarding::create(['user_id' => $user->id, 'user_type_id' => $type->id, 'status' => 'in_progress', 'started_at' => now()]);

        $this->group = QuestionGroup::create(['name' => 'Ownership', 'slug' => 'ownership', 'order' => 1, 'is_active' => true]);

        $this->table = Question::create([
            'question_group_id' => $this->group->id, 'label' => 'Ultimate Beneficial Owners List',
            'type' => 'table', 'is_required' => false, 'order' => 1, 'is_active' => true,
            'options' => ['columns' => [
                ['key' => 'full_legal_name', 'label' => 'Full Legal Name', 'type' => 'text'],
                ['key' => '%_ownership', 'label' => '% Ownership', 'type' => 'text'],
                ['key' => 'nationality', 'label' => 'Nationality', 'type' => 'text'],
                ['key' => 'residential_address', 'label' => 'Residential Address', 'type' => 'text'],
                ['key' => 'passport_front_page_pdf', 'label' => 'Passport', 'type' => 'file'],
            ]],
        ]);

        $this->ubo = Question::create([
            'question_group_id' => $this->group->id, 'label' => 'Ultimate Beneficial Owners',
            'type' => 'ubo', 'is_required' => false, 'order' => 2, 'is_active' => true,
        ]);
    }

    private function uboAnswer(array $owners): UserAnswer
    {
        return UserAnswer::create([
            'user_id' => $this->onboarding->user_id, 'question_id' => $this->ubo->id,
            'user_onboarding_id' => $this->onboarding->id, 'value' => json_encode($owners),
        ]);
    }

    private function tableRows(): array
    {
        $answer = UserAnswer::where('question_id', $this->table->id)
            ->where('user_onboarding_id', $this->onboarding->id)
            ->first();

        return $answer ? json_decode($answer->value, true) : [];
    }

    public function test_the_table_gains_every_field_the_widget_captured(): void
    {
        UboTableColumns::apply();

        $columns = collect($this->table->fresh()->options['columns'])->keyBy('key');

        // PEP screening and ID details would otherwise be lost with the widget.
        $this->assertArrayHasKey('id_type', $columns);
        $this->assertArrayHasKey('id_number', $columns);
        $this->assertArrayHasKey('is_pep', $columns);
        // And the table's own fields survive.
        $this->assertArrayHasKey('residential_address', $columns);
        $this->assertArrayHasKey('passport_front_page_pdf', $columns);
    }

    public function test_nationality_uses_iso_codes_so_widget_answers_map_across(): void
    {
        UboTableColumns::apply();

        $nationality = collect($this->table->fresh()->options['columns'])->firstWhere('key', 'nationality');

        $this->assertSame('select', $nationality['type']);
        $this->assertContains(['label' => 'India', 'value' => 'IN'], $nationality['options']);
    }

    public function test_existing_owner_answers_are_carried_into_the_table(): void
    {
        UboTableColumns::apply();
        $this->uboAnswer([
            ['full_name' => 'Jane Doe', 'ownership_percent' => '40', 'nationality' => 'IN',
                'date_of_birth' => '1985-04-12', 'id_type' => 'passport', 'id_number' => 'P1234567', 'is_pep' => 'no'],
            ['full_name' => 'John Roe', 'ownership_percent' => '35', 'nationality' => 'GB', 'is_pep' => 'yes'],
        ]);

        $result = UboWidgetConsolidation::apply();

        $this->assertSame(1, $result['answers_migrated']);
        $rows = $this->tableRows();
        $this->assertCount(2, $rows);
        $this->assertSame('Jane Doe', $rows[0]['full_legal_name']);
        $this->assertSame('40', $rows[0]['%_ownership']);
        $this->assertSame('IN', $rows[0]['nationality']);
        $this->assertSame('P1234567', $rows[0]['id_number']);
        $this->assertSame('no', $rows[0]['is_pep']);
        $this->assertSame('yes', $rows[1]['is_pep'], 'the PEP flag must survive the move');
    }

    public function test_owners_already_captured_in_the_table_are_never_overwritten(): void
    {
        UboTableColumns::apply();
        UserAnswer::create([
            'user_id' => $this->onboarding->user_id, 'question_id' => $this->table->id,
            'user_onboarding_id' => $this->onboarding->id,
            'value' => json_encode([['full_legal_name' => 'Client Entered', '%_ownership' => '100']]),
        ]);
        $this->uboAnswer([['full_name' => 'From Widget', 'ownership_percent' => '50']]);

        UboWidgetConsolidation::apply();

        $rows = $this->tableRows();
        $this->assertCount(1, $rows);
        $this->assertSame('Client Entered', $rows[0]['full_legal_name']);
    }

    public function test_the_widget_is_deactivated_but_its_answers_are_kept(): void
    {
        UboTableColumns::apply();
        $answer = $this->uboAnswer([['full_name' => 'Jane Doe']]);

        $result = UboWidgetConsolidation::apply();

        $this->assertSame(1, $result['widgets_retired']);
        $this->assertFalse($this->ubo->fresh()->is_active, 'the duplicate must stop rendering for clients');
        // Kept so reviewers can still see what a decided application was
        // assessed on.
        $this->assertDatabaseHas('user_answers', ['id' => $answer->id]);
    }

    public function test_running_it_again_migrates_nothing_further(): void
    {
        UboTableColumns::apply();
        $this->uboAnswer([['full_name' => 'Jane Doe', 'ownership_percent' => '40']]);

        UboWidgetConsolidation::apply();
        $second = UboWidgetConsolidation::apply();

        $this->assertSame(0, $second['answers_migrated']);
        $this->assertSame(0, $second['widgets_retired']);
        $this->assertCount(1, $this->tableRows());
    }

    public function test_an_ambiguous_group_is_left_for_a_human(): void
    {
        // Two owner tables in one group — we can't know which is canonical, so
        // no answer is moved (the widget is still retired).
        Question::create([
            'question_group_id' => $this->group->id, 'label' => 'Another Owner Table',
            'type' => 'table', 'is_required' => false, 'order' => 3, 'is_active' => true,
            'options' => ['columns' => [['key' => 'nationality', 'label' => 'Nationality', 'type' => 'text']]],
        ]);
        UboTableColumns::apply();
        $this->uboAnswer([['full_name' => 'Jane Doe']]);

        $result = UboWidgetConsolidation::apply();

        $this->assertSame(0, $result['answers_migrated']);
        $this->assertSame([], $this->tableRows());
    }
}
