<?php

use App\Models\Question;
use Illuminate\Database\Migrations\Migration;

/**
 * Apply sensible field-validation rules to the standard onboarding template so
 * the client-side validation engine actually enforces them (bug report EOP-36,
 * EOP-37, EOP-38, EOP-54). Conservative and idempotent: a rule is only set
 * where none exists, so admin-configured rules are never overwritten.
 *
 * Formats are enforced by frontend/src/utils/validation.js
 * (email / phone / url / alpha / alphanumeric, min/max length).
 */
return new class extends Migration
{
    /** Standalone text questions: label substring (lower-case) => rules to fill. */
    private array $questionRules = [
        'website' => ['format' => 'url'],
        'url' => ['format' => 'url'],
    ];

    /** Table columns: column key => rules to fill (only when the column is text). */
    private array $columnRules = [
        'full_name' => ['format' => 'alpha'],
        'full_legal_name' => ['format' => 'alpha'],
        'position' => ['format' => 'alphanumeric'],
        'email' => ['format' => 'email'],
        'phone' => ['format' => 'phone'],
        'id_number' => ['max_length' => 30],
        'license_number' => ['max_length' => 50],
    ];

    public function up(): void
    {
        // Standalone text questions.
        Question::where('type', 'text')->get()->each(function (Question $q) {
            $existing = $q->validation_rules ?? [];
            if (! empty($existing)) {
                return; // never clobber configured rules
            }
            $label = strtolower((string) $q->label);
            foreach ($this->questionRules as $needle => $rules) {
                if (str_contains($label, $needle)) {
                    $q->update(['validation_rules' => $rules]);

                    return;
                }
            }
        });

        // Table columns.
        Question::where('type', 'table')->get()->each(function (Question $q) {
            $options = $q->options ?? [];
            $columns = $options['columns'] ?? [];
            if (empty($columns)) {
                return;
            }

            $changed = false;
            foreach ($columns as $i => $column) {
                if (($column['type'] ?? 'text') !== 'text') {
                    continue;
                }
                if (! empty($column['validation'])) {
                    continue; // don't overwrite
                }
                $key = $column['key'] ?? '';
                if (isset($this->columnRules[$key])) {
                    $columns[$i]['validation'] = $this->columnRules[$key];
                    $changed = true;
                }
            }

            if ($changed) {
                $options['columns'] = $columns;
                $q->update(['options' => $options]);
            }
        });
    }

    public function down(): void
    {
        // Data-only, best-effort seed; nothing to reverse deterministically.
    }
};
