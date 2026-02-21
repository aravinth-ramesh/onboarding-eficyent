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
            'equals' => (string) $parentAnswer === (string) $triggerValue,
            'not_equals' => (string) $parentAnswer !== (string) $triggerValue,
            'contains' => str_contains((string) $parentAnswer, (string) $triggerValue),
            'not_contains' => !str_contains((string) $parentAnswer, (string) $triggerValue),
            'greater_than' => (float) $parentAnswer > (float) $triggerValue,
            'less_than' => (float) $parentAnswer < (float) $triggerValue,
            'in' => in_array($parentAnswer, json_decode($triggerValue, true) ?? []),
            'not_in' => !in_array($parentAnswer, json_decode($triggerValue, true) ?? []),
            'is_empty' => empty($parentAnswer),
            'is_not_empty' => !empty($parentAnswer),
            default => true,
        };
    }
}
