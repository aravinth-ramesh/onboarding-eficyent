<?php

namespace App\Support;

use App\Models\Question;

/**
 * Retire the free-text registration identifiers duplicated in the question
 * template (EOP-10).
 *
 * The Registration step already captures these per country, with the required
 * fields, format pattern, check digit and uniqueness for that jurisdiction.
 * The legacy template asked for the same identifiers again as plain text with
 * no rules at all, so a client could type "asdf" as their registration number
 * — which is what "not enforced based on the selected country" describes.
 *
 * Country of Incorporation itself is left active: it is a constrained
 * single-country dropdown (EOP-46) and is not an unvalidated identifier.
 *
 * Deactivated rather than deleted, so existing answers stay visible to
 * reviewers and the change is reversible.
 */
class DuplicateRegistrationQuestions
{
    /**
     * Label substrings (lower-case) captured properly by the Registration step.
     */
    private const LABEL_NEEDLES = [
        'company registration number',
        'company tax identification number',
        'tax identification number(s)',
    ];

    /**
     * @return int number of questions retired
     */
    public static function apply(): int
    {
        $retired = 0;

        foreach (Question::where('is_active', true)->get() as $question) {
            $label = strtolower((string) $question->label);

            foreach (self::LABEL_NEEDLES as $needle) {
                if (str_contains($label, $needle)) {
                    $question->update(['is_active' => false]);
                    $retired++;

                    continue 2;
                }
            }
        }

        return $retired;
    }
}
