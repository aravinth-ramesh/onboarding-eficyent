<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionGroup;
use App\Support\BankAccountFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The combined "Account Number / IBAN" column required an IBAN, so every valid
 * domestic account number was rejected by a field offering both (item 5).
 */
class BankAccountFormatTest extends TestCase
{
    use RefreshDatabase;

    private function table(array $columns): Question
    {
        $group = QuestionGroup::create(['name' => 'Bank', 'slug' => 'b-'.uniqid(), 'order' => 1, 'is_active' => true]);

        return Question::create([
            'question_group_id' => $group->id, 'label' => 'Primary Bank Account', 'type' => 'table',
            'is_required' => false, 'order' => 1, 'is_active' => true,
            'options' => ['columns' => $columns],
        ]);
    }

    public function test_the_combined_column_accepts_either_form(): void
    {
        $question = $this->table([
            ['key' => 'account_number_/_iban_:', 'label' => 'Account Number / IBAN', 'type' => 'text',
                'validation' => ['format' => 'iban']],
        ]);

        BankAccountFormat::apply();

        $this->assertSame(
            'account_or_iban',
            $question->fresh()->options['columns'][0]['validation']['format'],
        );
    }

    public function test_it_overwrites_a_value_the_fill_only_merge_would_have_kept(): void
    {
        // FieldValidationRules merges with $existing + $new and never
        // overwrites, so this needs its own pass to actually take effect.
        $question = $this->table([
            ['key' => 'account_number_/_iban_:', 'label' => 'Account / IBAN', 'type' => 'text',
                'validation' => ['format' => 'iban', 'min_length' => 5]],
        ]);

        BankAccountFormat::apply();

        $validation = $question->fresh()->options['columns'][0]['validation'];
        $this->assertSame('account_or_iban', $validation['format']);
        $this->assertSame(5, $validation['min_length'], 'other rules survive');
    }

    public function test_an_iban_only_column_keeps_the_stricter_rule(): void
    {
        $question = $this->table([
            ['key' => 'iban', 'label' => 'IBAN', 'type' => 'text', 'validation' => ['format' => 'iban']],
        ]);

        BankAccountFormat::apply();

        $this->assertSame('iban', $question->fresh()->options['columns'][0]['validation']['format']);
    }

    public function test_running_twice_changes_nothing_further(): void
    {
        $this->table([
            ['key' => 'account_number_/_iban_:', 'label' => 'Account / IBAN', 'type' => 'text',
                'validation' => ['format' => 'iban']],
        ]);

        $this->assertSame(1, BankAccountFormat::apply());
        $this->assertSame(0, BankAccountFormat::apply(), 'the applier is idempotent');
    }
}
