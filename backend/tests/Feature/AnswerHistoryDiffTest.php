<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionGroup;
use App\Support\AnswerValueFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Editing one cell of a UBO or bank table printed every column of both versions,
 * leaving the reviewer to find the change themselves. Only the cells that moved
 * are reported now (retest item 33).
 */
class AnswerHistoryDiffTest extends TestCase
{
    use RefreshDatabase;

    private function table(array $columns): Question
    {
        $group = QuestionGroup::create(['name' => 'G', 'slug' => 'g-'.uniqid(), 'order' => 1, 'is_active' => true]);

        return Question::create([
            'question_group_id' => $group->id, 'label' => 'Q', 'type' => 'table',
            'is_required' => false, 'order' => 1, 'is_active' => true,
            'options' => ['columns' => $columns],
        ]);
    }

    private function bankTable(): Question
    {
        return $this->table([
            ['key' => 'bank_name', 'label' => 'Bank Name', 'type' => 'text'],
            ['key' => 'phone', 'label' => 'Phone Number', 'type' => 'text'],
        ]);
    }

    public function test_only_the_changed_cell_is_reported(): void
    {
        $changed = AnswerValueFormatter::changedFields(
            json_encode([['bank_name' => 'Acme Bank', 'phone' => '78945678']]),
            json_encode([['bank_name' => 'Acme Bank', 'phone' => '98765432']]),
            $this->bankTable(),
        );

        $this->assertSame(
            [['label' => 'Phone Number', 'old' => '78945678', 'new' => '98765432']],
            $changed,
            'the unchanged Bank Name column must not appear',
        );
    }

    public function test_rows_are_numbered_when_there_is_more_than_one(): void
    {
        $changed = AnswerValueFormatter::changedFields(
            json_encode([['phone' => '111'], ['phone' => '222']]),
            json_encode([['phone' => '111'], ['phone' => '999']]),
            $this->bankTable(),
        );

        $this->assertSame(
            [['label' => '#2 Phone Number', 'old' => '222', 'new' => '999']],
            $changed,
        );
    }

    public function test_an_added_row_is_reported_against_a_dash(): void
    {
        $changed = AnswerValueFormatter::changedFields(
            json_encode([['phone' => '111']]),
            json_encode([['phone' => '111'], ['phone' => '222']]),
            $this->bankTable(),
        );

        $this->assertSame(
            [['label' => '#2 Phone Number', 'old' => '—', 'new' => '222']],
            $changed,
        );
    }

    public function test_a_select_cell_diffs_on_its_label_not_its_code(): void
    {
        $question = $this->table([
            ['key' => 'nationality', 'label' => 'Nationality', 'type' => 'select',
                'options' => [['value' => 'IN', 'label' => 'India'], ['value' => 'SG', 'label' => 'Singapore']]],
        ]);

        $changed = AnswerValueFormatter::changedFields(
            json_encode([['nationality' => 'IN']]),
            json_encode([['nationality' => 'SG']]),
            $question,
        );

        $this->assertSame(
            [['label' => 'Nationality', 'old' => 'India', 'new' => 'Singapore']],
            $changed,
        );
    }

    public function test_an_identical_table_reports_nothing(): void
    {
        $same = json_encode([['bank_name' => 'Acme Bank', 'phone' => '78945678']]);

        $this->assertSame([], AnswerValueFormatter::changedFields($same, $same, $this->bankTable()));
    }

    public function test_a_non_table_answer_falls_back_to_whole_value_rendering(): void
    {
        $group = QuestionGroup::create(['name' => 'T', 'slug' => 't-'.uniqid(), 'order' => 1, 'is_active' => true]);
        $text = Question::create([
            'question_group_id' => $group->id, 'label' => 'Company', 'type' => 'text',
            'is_required' => false, 'order' => 1, 'is_active' => true,
        ]);

        $this->assertNull(
            AnswerValueFormatter::changedFields('Acme Plc', 'Acme Ltd', $text),
            'null tells the caller to render the value itself',
        );
    }

    public function test_a_table_holding_unparseable_json_falls_back(): void
    {
        $this->assertNull(
            AnswerValueFormatter::changedFields('not json', '[]', $this->bankTable()),
        );
    }
}
