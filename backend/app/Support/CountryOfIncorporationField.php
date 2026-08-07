<?php

namespace App\Support;

use App\Models\Question;

/**
 * "Country of Incorporation" shipped as a free-text field, so a client could
 * type several countries for a company that has exactly one (bug report
 * EOP-46). Make it a single-country dropdown sourced from the same catalog the
 * Registration step uses.
 *
 * Lives here so the seeder (fresh installs) and the data migration (existing
 * databases) apply exactly the same shape and can't drift.
 */
class CountryOfIncorporationField
{
    private const LABEL = 'Country of Incorporation';

    /**
     * @return int number of questions converted
     */
    public static function apply(): int
    {
        $options = self::countryOptions();
        if ($options === []) {
            return 0;
        }

        // Only free-text questions are converted: a question an admin has
        // already configured as a select keeps its own option list.
        return Question::where('label', self::LABEL)
            ->where('type', 'text')
            ->get()
            ->each(fn (Question $question) => $question->update([
                'type' => 'select',
                'options' => $options,
            ]))
            ->count();
    }

    /** @return array<int, array{label: string, value: string}> */
    private static function countryOptions(): array
    {
        $names = array_values(config('country_registrations.countries', []));
        sort($names);

        return array_map(fn ($name) => ['label' => $name, 'value' => $name], $names);
    }
}
