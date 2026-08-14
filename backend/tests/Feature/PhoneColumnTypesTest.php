<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionGroup;
use App\Support\PhoneColumnTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phone columns inside tables were plain text, so signatories and beneficial
 * owners typed a country code into the number box by hand and then failed the
 * country-aware validation (retest items 28 and 31).
 */
class PhoneColumnTypesTest extends TestCase
{
    use RefreshDatabase;

    private function table(array $columns, string $label = 'T'): Question
    {
        $group = QuestionGroup::create(['name' => 'G', 'slug' => 'g-'.uniqid(), 'order' => 1, 'is_active' => true]);

        return Question::create([
            'question_group_id' => $group->id, 'label' => $label, 'type' => 'table',
            'is_required' => false, 'order' => 1, 'is_active' => true,
            'options' => ['columns' => $columns],
        ]);
    }

    public function test_a_phone_column_is_retyped(): void
    {
        $question = $this->table([
            ['key' => 'full_name', 'label' => 'Full Name', 'type' => 'text'],
            ['key' => 'phone', 'label' => 'Phone', 'type' => 'text'],
        ], 'Authorized Signatories');

        PhoneColumnTypes::apply();

        $columns = $question->fresh()->options['columns'];
        $this->assertSame('text', $columns[0]['type'], 'unrelated columns stay as they were');
        $this->assertSame('phone', $columns[1]['type']);
    }

    public function test_mobile_and_contact_number_are_recognised(): void
    {
        $question = $this->table([
            ['key' => 'mobile_number', 'label' => 'Mobile Number', 'type' => 'text'],
            ['key' => 'contact_no', 'label' => 'Contact No', 'type' => 'text'],
            ['key' => 'telephone', 'label' => 'Telephone', 'type' => 'text'],
        ]);

        PhoneColumnTypes::apply();

        foreach ($question->fresh()->options['columns'] as $column) {
            $this->assertSame('phone', $column['type']);
        }
    }

    public function test_a_column_already_configured_otherwise_is_left_alone(): void
    {
        // Someone may have deliberately made this a select; do not stomp it.
        $question = $this->table([
            ['key' => 'phone', 'label' => 'Phone', 'type' => 'select', 'options' => []],
        ]);

        PhoneColumnTypes::apply();

        $this->assertSame('select', $question->fresh()->options['columns'][0]['type']);
    }

    public function test_a_lookalike_column_is_not_converted(): void
    {
        $question = $this->table([
            ['key' => 'headphone_brand', 'label' => 'Headphone Brand', 'type' => 'text'],
            ['key' => 'phonetic_name', 'label' => 'Phonetic Name', 'type' => 'text'],
        ]);

        PhoneColumnTypes::apply();

        foreach ($question->fresh()->options['columns'] as $column) {
            $this->assertSame('text', $column['type'], 'word-boundary matching keeps lookalikes out');
        }
    }

    public function test_running_twice_changes_nothing_further(): void
    {
        $this->table([['key' => 'phone', 'label' => 'Phone', 'type' => 'text']]);

        $this->assertSame(1, PhoneColumnTypes::apply());
        $this->assertSame(0, PhoneColumnTypes::apply(), 'the applier is idempotent');
    }
}
