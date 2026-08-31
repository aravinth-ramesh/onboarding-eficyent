<?php

namespace App\Support;

use App\Models\Question;

/**
 * Field-validation rules for the standard onboarding template, so the
 * client-side engine (frontend/src/utils/validation.js) actually enforces them
 * (bug report EOP-32, EOP-36, EOP-37, EOP-38, EOP-39, EOP-42, EOP-54).
 *
 * Applied from BOTH the data migration (existing databases) and the seeder
 * (fresh `migrate --seed`, where the migration would otherwise run before the
 * questions exist and any re-seed would wipe the rules).
 *
 * Conservative and idempotent: a rule is only filled where none exists, so
 * admin-configured rules are never overwritten.
 */
class FieldValidationRules
{
    /** Standalone text questions: label substring (lower-case) => rules. */
    private const QUESTION_RULES = [
        'website' => ['format' => 'url'],
        'url' => ['format' => 'url'],
        // One field holding both an email and a number: require both parts
        // rather than leaving it unvalidated (EOP-42).
        'contact email & number' => ['format' => 'contact'],
        'contact email and number' => ['format' => 'contact'],
    ];

    /**
     * Questions to retype, keyed by label substring. A count captured as free
     * text accepted "12.5" and "abc" (EOP-18); a free-text explanation was
     * typed as a number (EOP-19).
     *
     * label needle => [type, rules]
     */
    private const QUESTION_TYPES = [
        'number of transactions per month' => ['number', ['min' => 0, 'integer' => true]],
        'source of funds' => ['textarea', ['min_length' => 20, 'max_length' => 1000, 'requires_letter' => true]],
    ];

    /** Table columns: column key => rules (text columns only). */
    private const COLUMN_RULES = [
        'full_name' => ['format' => 'alpha'],
        'full_legal_name' => ['format' => 'alpha'],
        // "Position" accepted digits-only before requires_letter (EOP-37).
        'position' => ['format' => 'alphanumeric', 'requires_letter' => true],
        'title' => ['format' => 'alphanumeric', 'requires_letter' => true],
        'email' => ['format' => 'email'],
        'phone' => ['format' => 'phone'],
        // A one-character ID was accepted before min_length (EOP-39). The
        // id_document format allows the - and / that real document numbers
        // carry, which the alphanumeric format rejected (retest item 31).
        'id_number' => ['format' => 'id_document', 'min_length' => 4, 'max_length' => 30],
        'passport_number' => ['format' => 'id_document', 'min_length' => 4, 'max_length' => 30],
        'license_number' => ['format' => 'id_document', 'min_length' => 3, 'max_length' => 50],

        // A single character was accepted as a postal code (retest item 21).
        'postal_code' => ['format' => 'postal_code', 'min_length' => 3, 'max_length' => 12],
        'postal' => ['format' => 'postal_code', 'min_length' => 3, 'max_length' => 12],
        'zip' => ['format' => 'postal_code', 'min_length' => 3, 'max_length' => 12],
        'zip_code' => ['format' => 'postal_code', 'min_length' => 3, 'max_length' => 12],

        // Primary bank account. The seeded column keys carry the legacy
        // punctuation, which is why none of them matched before and every bank
        // field accepted anything at all (EOP-23, EOP-24).
        'bank_name_:' => ['format' => 'alphanumeric', 'requires_letter' => true, 'min_length' => 2, 'max_length' => 100],
        'branch_name_/_location_:' => ['requires_letter' => true, 'min_length' => 2, 'max_length' => 120],
        'account_holder_name_:' => ['format' => 'alpha', 'min_length' => 2, 'max_length' => 120],
        'bank_address_:' => ['requires_letter' => true, 'min_length' => 5, 'max_length' => 200],
        // One field holds either form, so requiring an IBAN rejected every
        // valid domestic account number (retest item 5).
        'account_number_/_iban_:' => ['format' => 'account_or_iban'],
        'swift_/_bic_code_:' => ['format' => 'swift'],
    ];

    /**
     * Free-text follow-ups that accepted a single character. They explain a
     * regulatory action, so they need real prose (EOP-29).
     *
     * @var array<int, string>
     */
    private const EXPLANATION_NEEDLES = [
        'provide the reason, regulator, date, and final outcome',
        'provide dates, regulators, reasons, outcomes',
    ];

    /**
     * Columns that must be real dates, not free text — a text column can never
     * reach validateDate, so a future date of birth was accepted (EOP-32).
     *
     * column key => rules
     */
    private const DATE_COLUMNS = [
        // A beneficial owner or director must be an adult, so a future date
        // isn't the only invalid one — under-18 and a stray year like 1832 are
        // too (EOP-32).
        'date_of_birth' => ['allow_future' => false, 'min_age' => 18],
        'dob' => ['allow_future' => false, 'min_age' => 18],
    ];

    public static function apply(): void
    {
        self::applyToQuestions();
        self::applyToTableColumns();
    }

    private static function applyToQuestions(): void
    {
        // `textarea` is included so a retyped question can still be matched on
        // a later run.
        Question::whereIn('type', ['text', 'textarea'])->get()->each(function (Question $question) {
            $label = strtolower((string) $question->label);

            // Retype first: a count captured as text, or an explanation
            // captured as a number, can never be validated correctly.
            foreach (self::QUESTION_TYPES as $needle => [$type, $rules]) {
                if (str_contains($label, $needle)) {
                    $question->update([
                        'type' => $type,
                        'validation_rules' => self::isUnset($question->validation_rules)
                            ? $rules
                            : $question->validation_rules,
                    ]);

                    return;
                }
            }

            if (! self::isUnset($question->validation_rules)) {
                return;
            }

            foreach (self::QUESTION_RULES as $needle => $rules) {
                if (str_contains($label, $needle)) {
                    $question->update(['validation_rules' => $rules]);

                    return;
                }
            }

            foreach (self::EXPLANATION_NEEDLES as $needle) {
                if (str_contains($label, $needle)) {
                    $question->update(['validation_rules' => [
                        'min_length' => 20, 'max_length' => 1000, 'requires_letter' => true,
                    ]]);

                    return;
                }
            }
        });
    }

    private static function applyToTableColumns(): void
    {
        Question::where('type', 'table')->get()->each(function (Question $question) {
            $options = $question->options ?? [];
            $columns = $options['columns'] ?? [];
            if (empty($columns)) {
                return;
            }

            $changed = false;
            foreach ($columns as $i => $column) {
                $key = $column['key'] ?? '';
                $type = $column['type'] ?? 'text';

                // Date columns: retype from text so date rules can apply.
                if (isset(self::DATE_COLUMNS[$key]) && in_array($type, ['text', 'date'], true)) {
                    if ($type !== 'date') {
                        $columns[$i]['type'] = 'date';
                        $changed = true;
                    }
                    // Fill missing keys rather than only writing when empty, so
                    // a rule added later (min_age) reaches columns that an
                    // earlier release already seeded with allow_future alone.
                    $existing = is_array($column['validation'] ?? null) ? $column['validation'] : [];
                    $merged = $existing + self::DATE_COLUMNS[$key];
                    if ($merged != $existing) {
                        $columns[$i]['validation'] = $merged;
                        $changed = true;
                    }

                    continue;
                }

                if ($type !== 'text' || ! isset(self::COLUMN_RULES[$key])) {
                    continue;
                }

                // Fill only the keys that aren't set yet, so a rule added in a
                // later release lands on columns seeded by an earlier one
                // without ever overwriting a configured value.
                $existing = is_array($column['validation'] ?? null) ? $column['validation'] : [];
                $merged = $existing + self::COLUMN_RULES[$key];
                if ($merged != $existing) {
                    $columns[$i]['validation'] = $merged;
                    $changed = true;
                }
            }

            if ($changed) {
                $options['columns'] = $columns;
                $question->update(['options' => $options]);
            }
        });
    }

    /**
     * Legacy imported questions carry a `{"fields": [...]}` meta blob in
     * validation_rules that describes the old form layout — it holds no real
     * rules, so it must not block seeding (this is why the MLRO contact field
     * stayed unvalidated — EOP-42).
     */
    private static function isUnset(mixed $rules): bool
    {
        if (empty($rules) || ! is_array($rules)) {
            return true;
        }

        return array_keys($rules) === ['fields'];
    }
}
