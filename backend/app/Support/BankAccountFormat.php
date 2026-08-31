<?php

namespace App\Support;

use App\Models\Question;

/**
 * Let the combined "Account Number / IBAN" column accept either form.
 *
 * It carried `format: iban`, so every valid domestic account number was
 * rejected by a field whose own label offers both (report item 5).
 *
 * This replaces a configured value rather than filling a missing one, which is
 * why it cannot be left to FieldValidationRules: that merges with
 * `$existing + $new` and never overwrites, so a column already seeded as `iban`
 * would keep it and the change would silently do nothing.
 */
class BankAccountFormat
{
    private const FROM = 'iban';

    private const TO = 'account_or_iban';

    /** @return int number of questions changed */
    public static function apply(): int
    {
        $changed = 0;

        foreach (Question::where('type', 'table')->get() as $question) {
            $columns = $question->options['columns'] ?? null;

            if (! is_array($columns)) {
                continue;
            }

            $touched = false;

            foreach ($columns as $i => $column) {
                // Only the combined column: a field that is genuinely IBAN-only
                // should keep the stricter rule.
                if (! preg_match('/account.*iban|iban.*account/i', ($column['key'] ?? '').' '.($column['label'] ?? ''))) {
                    continue;
                }

                $validation = is_array($column['validation'] ?? null) ? $column['validation'] : [];

                if (($validation['format'] ?? null) !== self::FROM) {
                    continue;
                }

                $validation['format'] = self::TO;
                $columns[$i]['validation'] = $validation;
                $touched = true;
            }

            if ($touched) {
                $options = $question->options;
                $options['columns'] = $columns;
                $question->update(['options' => $options]);
                $changed++;
            }
        }

        return $changed;
    }
}
