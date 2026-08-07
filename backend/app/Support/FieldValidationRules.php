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

    /** Table columns: column key => rules (text columns only). */
    private const COLUMN_RULES = [
        'full_name' => ['format' => 'alpha'],
        'full_legal_name' => ['format' => 'alpha'],
        // "Position" accepted digits-only before requires_letter (EOP-37).
        'position' => ['format' => 'alphanumeric', 'requires_letter' => true],
        'title' => ['format' => 'alphanumeric', 'requires_letter' => true],
        'email' => ['format' => 'email'],
        'phone' => ['format' => 'phone'],
        // A one-character ID was accepted before min_length (EOP-39).
        'id_number' => ['min_length' => 4, 'max_length' => 30],
        'passport_number' => ['min_length' => 4, 'max_length' => 30],
        'license_number' => ['min_length' => 3, 'max_length' => 50],
    ];

    /**
     * Columns that must be real dates, not free text — a text column can never
     * reach validateDate, so a future date of birth was accepted (EOP-32).
     *
     * column key => rules
     */
    private const DATE_COLUMNS = [
        'date_of_birth' => ['allow_future' => false],
        'dob' => ['allow_future' => false],
    ];

    public static function apply(): void
    {
        self::applyToQuestions();
        self::applyToTableColumns();
    }

    private static function applyToQuestions(): void
    {
        Question::where('type', 'text')->get()->each(function (Question $question) {
            if (! self::isUnset($question->validation_rules)) {
                return;
            }

            $label = strtolower((string) $question->label);
            foreach (self::QUESTION_RULES as $needle => $rules) {
                if (str_contains($label, $needle)) {
                    $question->update(['validation_rules' => $rules]);

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
                    if (empty($column['validation'])) {
                        $columns[$i]['validation'] = self::DATE_COLUMNS[$key];
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
