import { evaluateConditionalRules } from './conditionalEngine';

const showRule = (overrides = {}) => ({
  parent_question_id: 1, comparison_type: 'equals', trigger_value: 'yes',
  action: 'show', logical_operator: 'and', ...overrides,
});

describe('conditional rule engine', () => {
  test('no rules means always visible', () => {
    expect(evaluateConditionalRules(null, {})).toBe(true);
    expect(evaluateConditionalRules([], {})).toBe(true);
  });

  test('a show rule reveals the question only when it matches', () => {
    const rules = [showRule()];
    expect(evaluateConditionalRules(rules, { 1: 'yes' })).toBe(true);
    expect(evaluateConditionalRules(rules, { 1: 'no' })).toBe(false);
  });

  test('a hide rule removes the question when it matches', () => {
    const rules = [showRule({ action: 'hide' })];
    expect(evaluateConditionalRules(rules, { 1: 'yes' })).toBe(false);
    expect(evaluateConditionalRules(rules, { 1: 'no' })).toBe(true);
  });

  test('each rule uses its own action, not the first rule\'s (EOP-96)', () => {
    // A question already gated by a show rule, to which an admin adds a hide
    // rule on a second parent. Previously the hide rule was evaluated as a
    // show condition because only rules[0].action was consulted, so the newly
    // added rule appeared to do nothing.
    const rules = [
      showRule({ parent_question_id: 1, trigger_value: 'yes', action: 'show' }),
      showRule({ parent_question_id: 2, trigger_value: 'exempt', action: 'hide' }),
    ];

    // Show condition met, hide condition not met -> visible.
    expect(evaluateConditionalRules(rules, { 1: 'yes', 2: 'none' })).toBe(true);
    // Show condition met, but the new hide rule now fires -> hidden.
    expect(evaluateConditionalRules(rules, { 1: 'yes', 2: 'exempt' })).toBe(false);
    // Show condition not met -> hidden regardless.
    expect(evaluateConditionalRules(rules, { 1: 'no', 2: 'none' })).toBe(false);
  });

  test('and/or combine within the same action', () => {
    const rules = [
      showRule({ parent_question_id: 1, trigger_value: 'a', logical_operator: 'or' }),
      showRule({ parent_question_id: 2, trigger_value: 'b', logical_operator: 'or' }),
    ];
    expect(evaluateConditionalRules(rules, { 1: 'a', 2: 'x' })).toBe(true);
    expect(evaluateConditionalRules(rules, { 1: 'x', 2: 'b' })).toBe(true);
    expect(evaluateConditionalRules(rules, { 1: 'x', 2: 'x' })).toBe(false);
  });

  test('a rule can key off a virtual field such as country_code', () => {
    const rules = [showRule({ parent_question_id: null, parent_field: 'country_code', trigger_value: 'IN' })];
    expect(evaluateConditionalRules(rules, { country_code: 'IN' })).toBe(true);
    expect(evaluateConditionalRules(rules, { country_code: 'GB' })).toBe(false);
  });

  test('multi-select answers match when the trigger is one of the selections', () => {
    const rules = [showRule({ trigger_value: 'aml' })];
    expect(evaluateConditionalRules(rules, { 1: ['kyc', 'aml'] })).toBe(true);
    expect(evaluateConditionalRules(rules, { 1: ['kyc'] })).toBe(false);
  });
});
