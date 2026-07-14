<?php

namespace App\Services;

use App\Models\UserOnboarding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

/**
 * Renders a submitted application as a PDF: reference and status header,
 * registration details, then every answered question grouped by section,
 * with values formatted per question type (the server-side counterpart of
 * the portal's answerFormat.js).
 */
class ApplicationPdfService
{
    public function render(UserOnboarding $onboarding)
    {
        $onboarding->load(['user', 'userType', 'subcategory', 'answers.question.group', 'answers.files']);

        $sections = $onboarding->answers
            ->filter(fn ($answer) => $answer->question && $answer->question->group)
            ->sortBy([
                fn ($a, $b) => ($a->question->group->order ?? 0) <=> ($b->question->group->order ?? 0),
                fn ($a, $b) => ($a->question->order ?? 0) <=> ($b->question->order ?? 0),
            ])
            ->groupBy(fn ($answer) => $answer->question->group->name);

        return Pdf::loadView('pdf.application', [
            'onboarding' => $onboarding,
            'sections' => $sections,
            'formatted' => fn ($answer) => $this->formatValue($answer),
        ]);
    }

    /**
     * Human-readable rendering of an answer value: JSON-typed questions
     * (multi_select / address / ubo / table) become lines instead of raw JSON.
     *
     * @return string[] display lines
     */
    public function formatValue($answer): array
    {
        $question = $answer->question;

        if ($question->type === 'file') {
            $files = $answer->files->pluck('original_filename')->all();

            return $files === [] ? ['—'] : $files;
        }

        $value = $answer->value;
        if ($value === null || trim((string) $value) === '') {
            return ['—'];
        }

        $decoded = json_decode($value, true);

        return match ($question->type) {
            'multi_select' => [is_array($decoded) ? implode(', ', $decoded) : (string) $value],
            'address' => $this->formatAddress($decoded, $value),
            'ubo' => $this->formatUbo($decoded, $value),
            'table' => $this->formatTable($decoded, $question, $value),
            default => [(string) $value],
        };
    }

    private function formatAddress(?array $address, string $fallback): array
    {
        if (! is_array($address)) {
            return [$fallback];
        }

        $line = collect([
            $address['line1'] ?? null,
            $address['line2'] ?? null,
            $address['city'] ?? null,
            $address['state'] ?? null,
            $address['postal_code'] ?? null,
            $address['country'] ?? null,
        ])->filter()->implode(', ');

        return [$line !== '' ? $line : $fallback];
    }

    private function formatUbo(?array $owners, string $fallback): array
    {
        if (! is_array($owners) || $owners === []) {
            return [$fallback];
        }

        return collect($owners)->map(function ($owner, $i) {
            $parts = collect([
                $owner['full_name'] ?? null,
                isset($owner['ownership_percent']) ? "{$owner['ownership_percent']}% ownership" : null,
                $owner['nationality'] ?? null,
                isset($owner['date_of_birth']) ? "born {$owner['date_of_birth']}" : null,
                ! empty($owner['is_pep']) ? 'PEP' : null,
            ])->filter()->implode(' — ');

            return ($i + 1) . '. ' . ($parts !== '' ? $parts : 'Unnamed owner');
        })->values()->all();
    }

    private function formatTable(?array $rows, $question, string $fallback): array
    {
        if (! is_array($rows) || $rows === []) {
            return [$fallback];
        }

        $columns = collect($question->options['columns'] ?? [])
            ->mapWithKeys(fn ($col) => [($col['key'] ?? '') => $col['label'] ?? $col['key'] ?? '']);

        return collect($rows)->map(function ($row, $i) use ($columns) {
            $cells = collect($row)
                ->map(function ($cell, $key) use ($columns) {
                    $label = $columns->get($key, $key);
                    $display = is_array($cell)
                        ? implode(', ', array_map(fn ($f) => $f['original_filename'] ?? '', $cell))
                        : (string) $cell;

                    return "{$label}: {$display}";
                })
                ->filter(fn ($line) => ! str_ends_with($line, ': '))
                ->implode('; ');

            return ($i + 1) . '. ' . $cells;
        })->values()->all();
    }
}
