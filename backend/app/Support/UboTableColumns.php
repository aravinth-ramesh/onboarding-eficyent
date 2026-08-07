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
                if (($column['key'] ?? '') !== 'nationality') {
                    continue;
                }

                $type = $column['type'] ?? 'text';
                // Free text -> dropdown, and an earlier name-valued dropdown ->
                // ISO-valued, so the table shares the `ubo` widget's vocabulary.
                if ($type === 'text' || ($type === 'select' && self::isNameValued($column['options'] ?? []))) {
                    $columns[$i]['type'] = 'select';
                    $columns[$i]['options'] = self::countryOptions();
                    // A dropdown can't hold free text, so any format rule is moot.
                    unset($columns[$i]['validation']);
                    $changed = true;
                }
            }

            // Fields the retired `ubo` widget captured that the table did not,
            // so the table can carry the whole owner record on its own.
            foreach (self::supplementaryColumns() as $column) {
                if (! in_array($column['key'], $keys, true)) {
                    $columns[] = $column;
                    $changed = true;
                }
            }

            if ($changed) {
                $options['columns'] = array_values($columns);
                $question->update(['options' => $options]);
                $changedQuestions++;
            }
        }

        return $changedQuestions;
    }

    /**
     * Our first pass shipped country options whose value was the country name.
     * Detect that shape so it can be upgraded to ISO codes without touching a
     * genuinely custom option list an admin may have configured.
     *
     * @param  array<int, array<string, mixed>>  $options
     */
    private static function isNameValued(array $options): bool
    {
        if ($options === []) {
            return true;
        }

        foreach ($options as $option) {
            if (($option['value'] ?? null) !== ($option['label'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * ID Type, ID Number and the PEP flag came from the `ubo` widget; PEP
     * screening in particular is compliance-critical, so the table must carry
     * it once that widget is retired.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function supplementaryColumns(): array
    {
        return [
            ['key' => 'id_type', 'label' => 'ID Type', 'type' => 'select', 'required' => false, 'options' => self::ID_TYPES],
            ['key' => 'id_number', 'label' => 'ID / Passport Number', 'type' => 'text', 'required' => false,
                'validation' => ['min_length' => 4, 'max_length' => 30]],
            ['key' => 'is_pep', 'label' => 'Politically Exposed Person (PEP)?', 'type' => 'select', 'required' => false,
                'options' => [['value' => 'no', 'label' => 'No'], ['value' => 'yes', 'label' => 'Yes']]],
        ];
    }

    /**
     * Country options keyed by ISO code — the same vocabulary the `ubo` widget
     * used, so its answers migrate into the table without remapping.
     *
     * @return array<int, array{label: string, value: string}>
     */
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
