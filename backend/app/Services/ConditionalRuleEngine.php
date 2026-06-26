<?php

namespace App\Services;

use App\Models\ConditionalRule;

class ConditionalRuleEngine
{
    /**
     * Evaluate whether a question should be visible based on conditional rules and current answers.
     *
     * @param array<ConditionalRule> $rules
     * @param array<int, mixed> $answers Map of question_id => answer_value
     */
    public function evaluate(iterable $rules, array $answers): bool
    {
        if (empty($rules)) {
            return true; // No rules means always visible
        }

        $groupedByOperator = collect($rules)->groupBy('logical_operator');

        // Process AND rules - all must pass
        $andRules = $groupedByOperator->get('and', collect());
        if ($andRules->isNotEmpty()) {
            foreach ($andRules as $rule) {
                if (!$this->evaluateRule($rule, $answers)) {
                    return $rule->action === 'hide'; // If AND fails, invert for 'hide' action
                }
            }
        }

        // Process OR rules - at least one must pass
        $orRules = $groupedByOperator->get('or', collect());
        if ($orRules->isNotEmpty()) {
            $orPassed = false;
            foreach ($orRules as $rule) {
                if ($this->evaluateRule($rule, $answers)) {
                    $orPassed = true;
                    break;
                }
            }
            if (!$orPassed) {
                return $orRules->first()->action === 'hide';
            }
        }

        // Default action from first rule
        $firstRule = collect($rules)->first();
        return $firstRule->action === 'show';
    }

    private function evaluateRule(ConditionalRule $rule, array $answers): bool
    {
        $parentAnswer = $answers[$rule->parent_question_id] ?? null;
        $triggerValue = $rule->trigger_value;

        return match ($rule->comparison_type) {
            'equals' => $this->valueEquals($parentAnswer, $triggerValue),
            'not_equals' => !$this->valueEquals($parentAnswer, $triggerValue),
            'contains' => $this->valueContains($parentAnswer, $triggerValue),
            'not_contains' => !$this->valueContains($parentAnswer, $triggerValue),
            'greater_than' => (float) $parentAnswer > (float) $triggerValue,
            'less_than' => (float) $parentAnswer < (float) $triggerValue,
            'in' => $this->valueInList($parentAnswer, json_decode($triggerValue ?? '', true) ?? []),
            'not_in' => !$this->valueInList($parentAnswer, json_decode($triggerValue ?? '', true) ?? []),
            'is_empty' => $this->valueIsEmpty($parentAnswer),
            'is_not_empty' => !$this->valueIsEmpty($parentAnswer),
            default => true,
        };
    }

    /**
     * Decode answer values that may be stored as JSON arrays (multi_select /
     * checkbox) into a list of comparable scalars.
     */
    private function parseAnswerArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            return [$value];
        }
        if ($value === null) {
            return [];
        }
        return [(string) $value];
    }

    private function valueEquals(mixed $parentAnswer, ?string $triggerValue): bool
    {
        foreach ($this->parseAnswerArray($parentAnswer) as $v) {
            if ((string) $v === (string) $triggerValue) {
                return true;
            }
        }
        return false;
    }

    private function valueContains(mixed $parentAnswer, ?string $triggerValue): bool
    {
        foreach ($this->parseAnswerArray($parentAnswer) as $v) {
            if (str_contains((string) $v, (string) $triggerValue)) {
                return true;
            }
        }
        return false;
    }

    private function valueInList(mixed $parentAnswer, array $values): bool
    {
        $stringValues = array_map('strval', $values);
        foreach ($this->parseAnswerArray($parentAnswer) as $v) {
            if (in_array((string) $v, $stringValues, true)) {
                return true;
            }
        }
        return false;
    }

    private function valueIsEmpty(mixed $parentAnswer): bool
    {
        if ($parentAnswer === null || $parentAnswer === '') {
            return true;
        }
        $arr = $this->parseAnswerArray($parentAnswer);
        return count($arr) === 0;
    }
}
