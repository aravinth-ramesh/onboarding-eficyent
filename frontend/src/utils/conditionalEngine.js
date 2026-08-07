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

  // Each rule carries its own action. Evaluating the whole set under the FIRST
  // rule's action meant a newly added `hide` rule was silently treated as a
  // `show` condition (and vice versa) whenever the question already had a rule
  // — the admin's new rule appeared to do nothing (EOP-96).
  const showRules = rulesArray.filter((r) => (r.action || 'show') === 'show');
  const hideRules = rulesArray.filter((r) => r.action === 'hide');

  // Show rules gate visibility; hide rules then take it away.
  let visible = showRules.length > 0 ? combineRules(showRules, answers) : true;
  if (visible && hideRules.length > 0 && combineRules(hideRules, answers)) {
    visible = false;
  }

  return visible;
}

/**
 * Combine a set of same-action rules: every `and` rule must pass and at least
 * one `or` rule must pass. A missing logical_operator counts as `and`.
 */
function combineRules(rules, answers) {
  const andRules = rules.filter((r) => !r.logical_operator || r.logical_operator === 'and');
  const orRules = rules.filter((r) => r.logical_operator === 'or');

  const andResult = andRules.length > 0
    ? andRules.every((rule) => evaluateSingleRule(rule, answers))
    : true;
  const orResult = orRules.length > 0
    ? orRules.some((rule) => evaluateSingleRule(rule, answers))
    : true;

  return andResult && orResult;
}

/**
 * Parse a rule's trigger_value into an array for in/not_in comparisons.
 * Accepts a JSON array, a comma-separated string, or a single value —
 * a malformed value must never throw and break visibility for the group.
 */
function parseTriggerList(triggerValue) {
  if (Array.isArray(triggerValue)) return triggerValue;
  if (triggerValue === null || triggerValue === undefined || triggerValue === '') return [];
  try {
    const parsed = JSON.parse(triggerValue);
    if (Array.isArray(parsed)) return parsed;
    return [parsed];
  } catch {
    return String(triggerValue).split(',').map((v) => v.trim()).filter(Boolean);
  }
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
  // A rule keys off either a parent question's answer or a virtual field
  // (e.g. `country_code` from the registration step), injected into `answers`.
  const parentAnswer = rule.parent_field
    ? answers[rule.parent_field]
    : answers[rule.parent_question_id];
  const triggerValue = rule.trigger_value;

  switch (rule.comparison_type) {
    case 'equals': {
      // For multi-value answers (multi_select / checkbox), "equals X" matches
      // when X is one of the selected values.
      const arr = parseAnswerArray(parentAnswer);
      return arr.some((v) => String(v) === String(triggerValue));
    }
    case 'not_equals': {
      const arr = parseAnswerArray(parentAnswer);
      return !arr.some((v) => String(v) === String(triggerValue));
    }
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
      const values = parseTriggerList(triggerValue).map(String);
      const answerArr = parseAnswerArray(parentAnswer);
      return answerArr.some((v) => values.includes(String(v)));
    }
    case 'not_in': {
      const values = parseTriggerList(triggerValue).map(String);
      const answerArr = parseAnswerArray(parentAnswer);
      return !answerArr.some((v) => values.includes(String(v)));
    }
    case 'is_empty':
      return !parentAnswer || parentAnswer === '' || (Array.isArray(parentAnswer) && parentAnswer.length === 0);
    case 'is_not_empty':
      return parentAnswer && parentAnswer !== '' && !(Array.isArray(parentAnswer) && parentAnswer.length === 0);
    default:
      return true;
  }
}
