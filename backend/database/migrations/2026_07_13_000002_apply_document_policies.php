<?php

use App\Models\Question;
use Illuminate\Database\Migrations\Migration;

/**
 * Data migration: attach AI document-validation policies to existing file
 * questions on already-seeded databases. Idempotent — merging the same policy
 * twice is a no-op, and fresh databases get the policies from the seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('document_validation.question_policies', []) as $label => $policy) {
            Question::where('type', 'file')->where('label', $label)->get()
                ->each(function (Question $question) use ($policy) {
                    $question->update([
                        'validation_rules' => array_merge($question->validation_rules ?? [], $policy),
                    ]);
                });
        }
    }

    public function down(): void
    {
        foreach (config('document_validation.question_policies', []) as $label => $policy) {
            Question::where('type', 'file')->where('label', $label)->get()
                ->each(function (Question $question) use ($policy) {
                    $rules = $question->validation_rules ?? [];
                    foreach (array_keys($policy) as $key) {
                        unset($rules[$key]);
                    }
                    $question->update(['validation_rules' => $rules ?: null]);
                });
        }
    }
};
