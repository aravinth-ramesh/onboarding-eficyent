<?php

namespace App\Support;

use App\Models\Question;

/**
 * Reads the combined beneficial-ownership percentage out of a table answer.
 *
 * The 100% ceiling was enforced only by the browser's `ubo` branch. Consolidating
 * the two overlapping UBO widgets left the surviving question typed `table`,
 * which routes past that branch, so two owners could each hold 100% and the
 * total went unchecked on both sides (retest item 29).
 */
class OwnershipTotal
{
    /** Column keys that have carried the ownership percentage over time. */
    private const KEYS = ['%_ownership', 'ownership_percent', 'ownership', 'percentage_owned'];

    /**
     * The column holding the ownership percentage, or null if this question
     * does not track ownership at all.
     */
    public static function columnKey(?Question $question): ?string
    {
        $columns = $question?->options['columns'] ?? null;

        if (! is_array($columns)) {
            return null;
        }

        foreach ($columns as $column) {
            if (in_array(strtolower((string) ($column['key'] ?? '')), self::KEYS, true)) {
                return $column['key'];
            }
        }

        // Fall back to the label so a renamed column is still caught.
        foreach ($columns as $column) {
            if (preg_match('/ownership|owned/i', (string) ($column['label'] ?? ''))) {
                return $column['key'] ?? null;
            }
        }

        return null;
    }

    /**
     * The summed percentage across every row, or null when the value is not
     * table-shaped and there is nothing to total.
     */
    public static function of(mixed $value, string $columnKey): ?float
    {
        $rows = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $total = 0.0;

        foreach ($rows as $row) {
            if (is_array($row) && is_numeric($row[$columnKey] ?? null)) {
                $total += (float) $row[$columnKey];
            }
        }

        return round($total, 2);
    }
}
