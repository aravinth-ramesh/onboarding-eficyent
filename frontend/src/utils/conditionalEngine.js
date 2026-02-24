/**
 * Evaluate whether a question should be visible based on its conditional rules
 * and the current set of answers.
 *
 * @param {Array|Object} rules - Array of conditional rule objects from the API
 * @param {Object} answers - Map of questionId -> answer value
 * @returns {boolean} Whether the question should be visible
 */
export function evaluateConditionalRules(rules, answers) {
  // Normalize: ensure rules is always an array
  if (!rules) return true;
  const rulesArray = Array.isArray(rules) ? rules : Object.values(rules);
  if (rulesArray.length === 0) return true;

  // Default null/undefined logical_operator to 'and'
  const andRules = rulesArray.filter(
    (r) => !r.logical_operator || r.logical_operator === 'and'
  );
  const orRules = rulesArray.filter((r) => r.logical_operator === 'or');

  let andResult = true;
  if (andRules.length > 0) {
    andResult = andRules.every((rule) => evaluateSingleRule(rule, answers));
  }

  let orResult = true;
  if (orRules.length > 0) {
    orResult = orRules.some((rule) => evaluateSingleRule(rule, answers));
  }

  const passed = andResult && orResult;
  const action = rulesArray[0]?.action || 'show';

  return action === 'show' ? passed : !passed;
}

/**
 * Normalize an answer value to a string for comparison.
 * Handles arrays (multi_select), JSON strings, and primitive values.
 */
function normalizeAnswer(value) {
  if (value === null || value === undefined) return '';
  if (Array.isArray(value)) return value.join(',');
  return String(value);
}

/**
 * Parse an answer value into an array for multi-value comparisons.
 */
function parseAnswerArray(value) {
  if (Array.isArray(value)) return value;
  if (typeof value === 'string') {
    try {
      const parsed = JSON.parse(value);
      if (Array.isArray(parsed)) return parsed;
    } catch {
      // Not JSON — return as single-item array
    }
    return [value];
  }
  if (value === null || value === undefined) return [];
  return [String(value)];
}

function evaluateSingleRule(rule, answers) {
  const parentAnswer = answers[rule.parent_question_id];
  const triggerValue = rule.trigger_value;

  switch (rule.comparison_type) {
    case 'equals':
      return normalizeAnswer(parentAnswer) === String(triggerValue);
    case 'not_equals':
      return normalizeAnswer(parentAnswer) !== String(triggerValue);
    case 'contains': {
      // Handle array answers (multi_select) — check if trigger value is in the array
      const answerArr = parseAnswerArray(parentAnswer);
      return answerArr.some(
        (v) => String(v).includes(String(triggerValue))
      );
    }
    case 'not_contains': {
      const answerArr = parseAnswerArray(parentAnswer);
      return !answerArr.some(
        (v) => String(v).includes(String(triggerValue))
      );
    }
    case 'greater_than':
      return Number(parentAnswer) > Number(triggerValue);
    case 'less_than':
      return Number(parentAnswer) < Number(triggerValue);
    case 'in': {
      const values = JSON.parse(triggerValue || '[]');
      const answerArr = parseAnswerArray(parentAnswer);
      return answerArr.some((v) => values.includes(v));
    }
    case 'not_in': {
      const values = JSON.parse(triggerValue || '[]');
      const answerArr = parseAnswerArray(parentAnswer);
      return !answerArr.some((v) => values.includes(v));
    }
    case 'is_empty':
      return !parentAnswer || parentAnswer === '' || (Array.isArray(parentAnswer) && parentAnswer.length === 0);
    case 'is_not_empty':
      return parentAnswer && parentAnswer !== '' && !(Array.isArray(parentAnswer) && parentAnswer.length === 0);
    default:
      return true;
  }
}
