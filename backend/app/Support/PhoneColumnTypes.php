<?php

namespace App\Support;

use App\Models\Question;

/**
 * Retype phone columns inside tables from free text to `phone`.
 *
 * The standalone phone question has had a country dial-code dropdown for a
 * while, but table columns never did — so on Authorized Signatories, directors
 * and the ownership structure a phone number was a plain text box and the
 * client had to type "+65-9856545" by hand (retest items 28 and 31).
 *
 * Typing the column `phone` gets both the dropdown and the country-aware
 * validation added for EOP-34, since validateTableCell routes on column type.
 */
class PhoneColumnTypes
{
    /** Columns whose key or label marks them as a phone number. */
    private const NEEDLE = '/\b(phone|mobile|telephone|contact\s*(number|no))\b/i';

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
                // Only free text is converted: a column deliberately set to
                // something else is left as configured.
                if (($column['type'] ?? 'text') !== 'text') {
                    continue;
                }

                $haystack = ($column['key'] ?? '').' '.($column['label'] ?? '');

                if (! preg_match(self::NEEDLE, $haystack)) {
                    continue;
                }

                $columns[$i]['type'] = 'phone';
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
