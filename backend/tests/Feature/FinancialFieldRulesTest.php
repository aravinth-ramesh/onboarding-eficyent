<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionGroup;
use App\Support\DuplicateRegistrationQuestions;
use App\Support\FieldValidationRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Financial-module fields carry real rules, and the registration identifiers
 * duplicated as unvalidated free text are retired in favour of the
 * Registration step (bug report EOP-10, EOP-18, EOP-19, EOP-23, EOP-24, EOP-29).
 */
class FinancialFieldRulesTest extends TestCase
{
    use RefreshDatabase;

    private function question(string $label, string $type = 'text', ?array $options = null): Question
    {
        $group = QuestionGroup::create(['name' => 'G', 'slug' => 'g-'.uniqid(), 'order' => 1, 'is_active' => true]);

        return Question::create([
            'question_group_id' => $group->id, 'label' => $label, 'type' => $type,
            'is_required' => true, 'order' => 1, 'is_active' => true, 'options' => $options,
        ]);
    }

    public function test_a_transaction_count_becomes_a_whole_number_field(): void
    {
        $q = $this->question('Expected Number of Transactions per Month:');

        FieldValidationRules::apply();

        $fresh = $q->fresh();
        $this->assertSame('number', $fresh->type, 'a count captured as text accepted "12.5" and "abc"');
        $this->assertTrue($fresh->validation_rules['integer']);
        $this->assertSame(0, $fresh->validation_rules['min']);
    }

    public function test_source_of_funds_becomes_a_free_text_explanation(): void
    {
        $q = $this->question('Source of Funds: (explain origin of money inflows)');

        FieldValidationRules::apply();

        $fresh = $q->fresh();
        $this->assertSame('textarea', $fresh->type);
        $this->assertSame(20, $fresh->validation_rules['min_length']);
        $this->assertTrue($fresh->validation_rules['requires_letter']);
    }

    public function test_bank_account_columns_gain_iban_and_swift_rules(): void
    {
        $q = $this->question('Bank account', 'table', ['columns' => [
            ['key' => 'bank_name_:', 'label' => 'Bank Name', 'type' => 'text'],
            ['key' => 'account_number_/_iban_:', 'label' => 'Account / IBAN', 'type' => 'text'],
            ['key' => 'swift_/_bic_code_:', 'label' => 'SWIFT / BIC', 'type' => 'text'],
        ]]);

        FieldValidationRules::apply();

        $columns = collect($q->fresh()->options['columns'])->keyBy('key');
        // One field holds either form. Requiring an IBAN here rejected every
        // valid domestic account number (report item 5).
        $this->assertSame('account_or_iban', $columns['account_number_/_iban_:']['validation']['format']);
        $this->assertSame('swift', $columns['swift_/_bic_code_:']['validation']['format']);
        $this->assertTrue($columns['bank_name_:']['validation']['requires_letter']);
    }

    public function test_regulatory_explanations_need_real_prose(): void
    {
        $q = $this->question('If yes, provide the reason, regulator, date, and final outcome.');

        FieldValidationRules::apply();

        $this->assertSame(20, $q->fresh()->validation_rules['min_length']);
    }

    public function test_unvalidated_registration_duplicates_are_retired(): void
    {
        // The Registration step captures these per country with a pattern,
        // check digit and uniqueness; the template asked again as free text.
        $duplicate = $this->question('Company Registration Number');
        $keep = $this->question('Describe your business model');

        $this->assertSame(1, DuplicateRegistrationQuestions::apply());

        $this->assertFalse($duplicate->fresh()->is_active);
        $this->assertTrue($keep->fresh()->is_active);
    }

    public function test_applying_the_rules_twice_is_stable(): void
    {
        $q = $this->question('Expected Number of Transactions per Month:');

        FieldValidationRules::apply();
        $after = $q->fresh()->only(['type', 'validation_rules']);
        FieldValidationRules::apply();

        $this->assertSame($after, $q->fresh()->only(['type', 'validation_rules']));
    }
}
