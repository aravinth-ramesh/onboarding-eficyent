<?php

namespace App\Support;

use App\Models\Question;

/**
 * Give the AML/CFT "Describe Control Measures" fields room for a real answer.
 *
 * They were capped at 200 characters, which is not enough to describe the
 * controls applied to crypto custody, gambling, sanctioned jurisdictions and
 * the rest — a reviewer needs the detail (retest item 30).
 *
 * This deliberately RAISES an existing cap rather than filling a missing one.
 * FieldValidationRules merges with `$existing + $new`, which never overwrites a
 * configured value, so a column already seeded at 200 would keep it. Matching
 * on the label catches every variant of the field across the module.
 */
class ControlMeasureFields
{
    public const MAX_LENGTH = 500;

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
                if (! preg_match('/describe\s+control\s+measures/i', (string) ($column['label'] ?? ''))) {
                    continue;
                }

                $validation = is_array($column['validation'] ?? null) ? $column['validation'] : [];
                $current = $validation['max_length'] ?? null;

                if ($current !== null && (int) $current >= self::MAX_LENGTH) {
                    continue;
                }

                $validation['max_length'] = self::MAX_LENGTH;
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
