<?php

namespace App\Support;

use App\Models\Question;

/**
 * Turn the free-text "list all countries" questions into multi-selects over
 * the country catalogue (EOP-20, EOP-21).
 *
 * As free text they accepted invented country names, and nothing stopped the
 * same country being listed twice. A multi-select makes both structurally
 * impossible — there is no rule to write and nothing to keep in sync.
 */
class CountryListQuestions
{
    /**
     * Label substrings (lower-case) identifying a country list. Matched
     * loosely because the seeded labels carry trailing punctuation.
     */
    private const LABEL_NEEDLES = [
        'inbound countries',
        'outbound destination countries',
        'outbound countries',
        'countries of tax residency',
        'jurisdictions where financial services are offered',
    ];

    /**
     * @return int number of questions converted
     */
    public static function apply(): int
    {
        $options = self::countryOptions();
        if ($options === []) {
            return 0;
        }

        $converted = 0;

        // Only free text is converted; a question an admin already configured
        // keeps its own type and options.
        foreach (Question::where('type', 'text')->get() as $question) {
            $label = strtolower((string) $question->label);

            foreach (self::LABEL_NEEDLES as $needle) {
                if (str_contains($label, $needle)) {
                    $question->update([
                        'type' => 'multi_select',
                        'options' => $options,
                        // A picker can't hold free text, so any format rule is moot.
                        'validation_rules' => null,
                    ]);
                    $converted++;

                    continue 2;
                }
            }
        }

        return $converted;
    }

    /** @return array<int, array{label: string, value: string}> */
    private static function countryOptions(): array
    {
        $countries = config('country_registrations.countries', []);
        asort($countries);

        $options = [];
        foreach ($countries as $code => $name) {
            $options[] = ['label' => $name, 'value' => $code];
        }

        return $options;
    }
}
