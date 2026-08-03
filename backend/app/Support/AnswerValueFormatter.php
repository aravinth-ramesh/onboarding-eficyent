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

        // Single-choice answers store the option value; show its label.
        if (in_array($type, ['radio', 'select'], true) && ! $isJsonArray) {
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
            $rows = count($decoded);

            return $rows === 0 ? '(no rows)' : $rows.' '.Str::plural('row', $rows);
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
