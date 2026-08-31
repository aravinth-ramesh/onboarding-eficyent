<?php

namespace App\Services;

use App\Models\Question;
use App\Models\UserOnboarding;

/**
 * Check an application against its own conditional rules at the moment of
 * submission.
 *
 * Conditional visibility was enforced only in the browser, so nothing stopped
 * an application reaching review with a required question left unanswered —
 * the client simply had to not ask it. This runs at submission rather than on
 * every save so a half-filled draft is never blocked, but nothing arrives for
 * review violating the rules it was collected under.
 *
 * Hidden questions are deliberately not policed: a client who answers something
 * then makes it irrelevant has done nothing wrong, and the stale answer is
 * already ignored everywhere it is read.
 */
class SubmissionCompleteness
{
    public function __construct(private ConditionalRuleEngine $rules) {}

    /**
     * Labels of questions that are required, visible under the current answers,
     * and still unanswered. Empty means the application may be submitted.
     *
     * @return array<int, string>
     */
    public function missingRequired(UserOnboarding $onboarding): array
    {
        $questions = ApplicableQuestions::for($onboarding);
        $answers = ApplicableQuestions::answerMap($onboarding);

        $missing = [];

        foreach ($questions as $question) {
            if (! $question->is_required) {
                continue;
            }

            if (! $this->isVisible($question, $answers)) {
                continue;
            }

            if (self::isBlank($answers[$question->id] ?? null)) {
                $missing[] = $question->label;
            }
        }

        return $missing;
    }

    /** @param array<int|string, mixed> $answers */
    private function isVisible(Question $question, array $answers): bool
    {
        $rules = $question->conditionalRules;

        // No rules means the question always applies.
        return $rules === null || $rules->isEmpty()
            ? true
            : $this->rules->evaluate($rules, $answers);
    }

    /**
     * Blank in the same sense the browser uses: whitespace is not an answer,
     * and neither is an empty table or multi-select.
     */
    private static function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        $string = is_string($value) ? trim($value) : $value;

        if ($string === '' || $string === '[]' || $string === '{}') {
            return true;
        }

        if (is_string($string) && str_starts_with($string, '[')) {
            $decoded = json_decode($string, true);
            if (is_array($decoded)) {
                // Rows that are entirely empty are scaffolding, not answers.
                return collect($decoded)->every(
                    fn ($row) => is_array($row)
                        ? collect($row)->every(fn ($cell) => $cell === null || $cell === '' || $cell === [])
                        : ($row === null || $row === ''),
                );
            }
        }

        return false;
    }
}
