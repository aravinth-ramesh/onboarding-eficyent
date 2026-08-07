<?php

namespace App\Support;

use App\Models\Question;

/**
 * Make UBO/beneficial-owner capture consistent (bug report EOP-49).
 *
 * The ownership section carries two overlapping widgets: the legacy `table`
 * question and the newer `ubo` question rendered by UboField. UboField shows
 * Nationality and ID Type as dropdowns; the legacy table had Nationality as
 * free text and no ID Type at all, so identical-looking owner entries offered
 * different controls and accepted inconsistent values.
 *
 * Bring the table's columns in line with UboField: Nationality becomes a
 * country dropdown and an ID Type dropdown is added, using the same option
 * lists. Additive and idempotent — existing rows simply have no value for a
 * newly added column.
 */
class UboTableColumns
{
    /** Mirrors ID_TYPES in frontend/src/components/onboarding/UboField.js. */
    private const ID_TYPES = [
        ['value' => 'passport', 'label' => 'Passport'],
        ['value' => 'national_id', 'label' => 'National ID'],
        ['value' => 'drivers_license', 'label' => "Driver's License"],
        ['value' => 'other', 'label' => 'Other'],
    ];

    /**
     * @return int number of table questions changed
     */
    public static function apply(): int
    {
        $changedQuestions = 0;

        foreach (Question::where('type', 'table')->get() as $question) {
            $options = $question->options ?? [];
            $columns = $options['columns'] ?? [];
            if ($columns === []) {
                continue;
            }

            $keys = array_column($columns, 'key');
            if (! in_array('nationality', $keys, true)) {
                continue; // not an owner/director table
            }

            $changed = false;

            foreach ($columns as $i => $column) {
                if (($column['key'] ?? '') !== 'nationality' || ($column['type'] ?? 'text') !== 'text') {
                    continue;
                }
                $columns[$i]['type'] = 'select';
                $columns[$i]['options'] = self::countryOptions();
                // A dropdown can't hold free text, so any format rule is moot.
                unset($columns[$i]['validation']);
                $changed = true;
            }

            if (! in_array('id_type', $keys, true)) {
                $columns[] = [
                    'key' => 'id_type',
                    'label' => 'ID Type',
                    'type' => 'select',
                    'required' => false,
                    'options' => self::ID_TYPES,
                ];
                $changed = true;
            }

            if ($changed) {
                $options['columns'] = array_values($columns);
                $question->update(['options' => $options]);
                $changedQuestions++;
            }
        }

        return $changedQuestions;
    }

    /** @return array<int, array{label: string, value: string}> */
    private static function countryOptions(): array
    {
        $names = array_values(config('country_registrations.countries', []));
        sort($names);

        return array_map(fn ($name) => ['label' => $name, 'value' => $name], $names);
    }
}
