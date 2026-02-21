/**
 * Evaluate whether a question should be visible based on its conditional rules
 * and the current set of answers.
 *
 * @param {Array} rules - Array of conditional rule objects from the API
 * @param {Object} answers - Map of questionId -> answer value
 * @returns {boolean} Whether the question should be visible
 */
export function evaluateConditionalRules(rules, answers) {
  if (!rules || rules.length === 0) {
    return true; // No rules = always visible
  }

  const andRules = rules.filter((r) => r.logical_operator === 'and');
  const orRules = rules.filter((r) => r.logical_operator === 'or');

  let andResult = true;
  if (andRules.length > 0) {
    andResult = andRules.every((rule) => evaluateSingleRule(rule, answers));
  }

  let orResult = true;
  if (orRules.length > 0) {
    orResult = orRules.some((rule) => evaluateSingleRule(rule, answers));
  }

  const passed = andResult && orResult;
  const action = rules[0]?.action || 'show';

  return action === 'show' ? passed : !passed;
}

function evaluateSingleRule(rule, answers) {
  const parentAnswer = answers[rule.parent_question_id];
  const triggerValue = rule.trigger_value;

  switch (rule.comparison_type) {
    case 'equals':
      return String(parentAnswer) === String(triggerValue);
    case 'not_equals':
      return String(parentAnswer) !== String(triggerValue);
    case 'contains':
      return String(parentAnswer || '').includes(String(triggerValue));
    case 'not_contains':
      return !String(parentAnswer || '').includes(String(triggerValue));
    case 'greater_than':
      return Number(parentAnswer) > Number(triggerValue);
    case 'less_than':
      return Number(parentAnswer) < Number(triggerValue);
    case 'in': {
      const values = JSON.parse(triggerValue || '[]');
      return values.includes(parentAnswer);
    }
    case 'not_in': {
      const values = JSON.parse(triggerValue || '[]');
      return !values.includes(parentAnswer);
    }
    case 'is_empty':
      return !parentAnswer || parentAnswer === '';
    case 'is_not_empty':
      return parentAnswer && parentAnswer !== '';
    default:
      return true;
  }
}
