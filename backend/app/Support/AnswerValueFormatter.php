<?php

namespace App\Support;

use App\Models\Question;
use Illuminate\Support\Str;

/**
 * Turns a stored answer value — which for files, multi-selects and tables is
 * raw JSON — into a short, plain-English string for the audit trail, so a
 * non-technical reviewer reads "certificate.pdf" instead of a JSON blob.
 */
class AnswerValueFormatter
{
    public static function readable(?string $raw, ?Question $question): string
    {
        if ($raw === null || trim($raw) === '') {
            return '—';
        }

        $type = $question?->type;
        $decoded = json_decode($raw, true);
        $isJsonArray = json_last_error() === JSON_ERROR_NONE && is_array($decoded);

        // Single-choice answers store the option value; show its label. `mcc`
        // stores an industry code, which read as a bare "5942" before its
        // options were seeded (retest item 38).
        if (in_array($type, ['radio', 'select', 'mcc'], true) && ! $isJsonArray) {
            return self::optionLabel($raw, $question);
        }

        if (! $isJsonArray) {
            return $raw; // plain text / number / date
        }

        if ($type === 'multi_select') {
            $labels = self::optionLabels($decoded, $question);

            return $labels === '' ? '—' : $labels;
        }

        if ($type === 'table') {
            return self::tableRows($decoded, $question);
        }

        // The `ubo` widget stores the same row shape as a table.
        if ($type === 'ubo') {
            return self::tableRows($decoded, $question);
        }

        if ($type === 'file' || self::looksLikeFiles($decoded)) {
            return self::files($decoded);
        }

        // Unknown array of scalars — just join it.
        $scalars = array_filter($decoded, 'is_scalar');
        if (count($scalars) === count($decoded)) {
            return implode(', ', $scalars);
        }

        return self::files($decoded);
    }

    /**
     * Render table/UBO rows as their actual field values.
     *
     * This used to return just "1 row", so the audit trail showed
     * "1 row → 1 row" and a reviewer could not see what the client had
     * actually changed in the UBO, directors or bank-account tables
     * (EOP-73). Column labels come from the question's own config, and
     * select columns are mapped back to their labels.
     *
     * @param  array<int, mixed>  $rows
     */
    private static function tableRows(array $rows, ?Question $question): string
    {
        if ($rows === []) {
            return '(no rows)';
        }

        $columns = collect($question?->options['columns'] ?? []);

        $rendered = [];
        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $cells = [];
            foreach ($row as $key => $value) {
                $column = $columns->firstWhere('key', $key);
                $label = $column['label'] ?? Str::headline((string) $key);
                $display = self::cell($value, $column);

                if ($display !== '') {
                    $cells[] = $label.': '.$display;
                }
            }

            if ($cells !== []) {
                $rendered[] = (count($rows) > 1 ? '#'.($index + 1).' ' : '').implode('; ', $cells);
            }
        }

        if ($rendered === []) {
            return count($rows).' '.Str::plural('row', count($rows)).' (empty)';
        }

        return implode(' | ', $rendered);
    }

    /**
     * @param  array<string, mixed>|null  $column
     */
    private static function cell(mixed $value, ?array $column): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // A file cell holds the upload's metadata, not a scalar.
        if (is_array($value)) {
            return $value['original_filename']
                ?? $value['filename']
                ?? basename((string) ($value['s3_path'] ?? $value['path'] ?? ''))
                ?: '(file)';
        }

        $string = trim((string) $value);
        if ($string === '') {
            return '';
        }

        // Show the option label rather than the stored code (e.g. "IN" -> "India").
        if (($column['type'] ?? null) === 'select') {
            foreach ($column['options'] ?? [] as $option) {
                if ((string) ($option['value'] ?? '') === $string) {
                    return (string) ($option['label'] ?? $string);
                }
            }
        }

        return $string;
    }

    private static function looksLikeFiles(array $decoded): bool
    {
        $first = $decoded[0] ?? null;

        return is_array($first) && (isset($first['original_filename']) || isset($first['s3_path']) || isset($first['path']));
    }

    private static function files(array $decoded): string
    {
        $names = [];
        foreach ($decoded as $item) {
            if (is_array($item)) {
                $names[] = $item['original_filename'] ?? basename((string) ($item['s3_path'] ?? $item['path'] ?? ''));
            } elseif (is_string($item)) {
                $names[] = basename($item);
            }
        }
        $names = array_values(array_filter($names, fn ($n) => $n !== ''));

        if ($names === []) {
            return '(no file)';
        }

        $count = count($names);

        return $count.' '.Str::plural('file', $count).': '.implode(', ', $names);
    }

    private static function optionLabels(array $values, ?Question $question): string
    {
        $options = collect($question?->options ?? []);

        return collect($values)
            ->map(fn ($v) => $options->firstWhere('value', $v)['label'] ?? $v)
            ->filter(fn ($v) => is_scalar($v) && $v !== '')
            ->implode(', ');
    }

    private static function optionLabel(string $value, ?Question $question): string
    {
        $options = collect($question?->options ?? []);

        return $options->firstWhere('value', $value)['label'] ?? $value;
    }
}
