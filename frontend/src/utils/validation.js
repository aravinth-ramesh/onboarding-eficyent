// Field-level validation driven by per-question metadata sent from the
// backend.  Each question (or table column) can carry a `validation_rules`
// object whose keys are interpreted based on the field type:
//
//   text / textarea:
//     - pattern          : regex source string the value must fully match
//     - pattern_message  : custom message shown when the pattern fails
//     - min_length       : minimum number of characters
//     - max_length       : maximum number of characters
//
//   number:
//     - min              : minimum allowed value (inclusive)
//     - max              : maximum allowed value (inclusive)
//
//   date:
//     - allow_past       : if false, dates strictly before today are rejected
//     - allow_future     : if false, dates strictly after today are rejected
//     - allow_today      : if false, today's date is rejected
//     - min_date         : ISO date string the value must be >= to
//     - max_date         : ISO date string the value must be <= to
//
// All validators return either an error message string, or `null` when the
// value satisfies the rules (or the rules don't apply).

const isEmpty = (value) =>
  value === null ||
  value === undefined ||
  value === '' ||
  (Array.isArray(value) && value.length === 0);

const toDateOnly = (input) => {
  if (!input) return null;
  // Accept Date objects, ISO strings, and yyyy-mm-dd values.
  const d = input instanceof Date ? new Date(input) : new Date(`${input}T00:00:00`);
  if (Number.isNaN(d.getTime())) return null;
  d.setHours(0, 0, 0, 0);
  return d;
};

const todayDateOnly = () => {
  const d = new Date();
  d.setHours(0, 0, 0, 0);
  return d;
};

const compileRegex = (pattern) => {
  try {
    // Anchor the pattern so the entire value must match — matches the
    // semantics of HTML `pattern` attribute and most backend validators.
    const anchored = pattern.startsWith('^') ? pattern : `^(?:${pattern})$`;
    return new RegExp(anchored);
  } catch {
    return null;
  }
};

export const validateText = (value, rules = {}) => {
  if (isEmpty(value)) return null;
  const str = String(value);

  if (rules.min_length != null && str.length < Number(rules.min_length)) {
    return `Must be at least ${rules.min_length} characters.`;
  }
  if (rules.max_length != null && str.length > Number(rules.max_length)) {
    return `Must be at most ${rules.max_length} characters.`;
  }
  if (rules.pattern) {
    const re = compileRegex(String(rules.pattern));
    if (re && !re.test(str)) {
      return rules.pattern_message || 'Value does not match the required format.';
    }
  }
  return null;
};

export const validateNumber = (value, rules = {}) => {
  if (isEmpty(value)) return null;
  const num = Number(value);
  if (Number.isNaN(num)) return 'Must be a valid number.';

  if (rules.min != null && num < Number(rules.min)) {
    return `Must be at least ${rules.min}.`;
  }
  if (rules.max != null && num > Number(rules.max)) {
    return `Must be at most ${rules.max}.`;
  }
  return null;
};

export const validateDate = (value, rules = {}) => {
  if (isEmpty(value)) return null;
  const date = toDateOnly(value);
  if (!date) return 'Enter a valid date.';

  const today = todayDateOnly();
  const isPast = date.getTime() < today.getTime();
  const isFuture = date.getTime() > today.getTime();
  const isToday = date.getTime() === today.getTime();

  if (rules.allow_past === false && isPast) {
    return 'Past dates are not allowed.';
  }
  if (rules.allow_future === false && isFuture) {
    return 'Future dates are not allowed.';
  }
  if (rules.allow_today === false && isToday) {
    return "Today's date is not allowed.";
  }

  const minDate = toDateOnly(rules.min_date);
  if (minDate && date.getTime() < minDate.getTime()) {
    return `Date must be on or after ${rules.min_date}.`;
  }
  const maxDate = toDateOnly(rules.max_date);
  if (maxDate && date.getTime() > maxDate.getTime()) {
    return `Date must be on or before ${rules.max_date}.`;
  }
  return null;
};

// Dispatcher used for both top-level questions and individual table cells.
// `type` is one of the question/column types (text, textarea, number, date,
// ...) and `rules` is the validation metadata block sent from the backend.
export const validateByType = (type, value, rules) => {
  if (!rules || typeof rules !== 'object') return null;
  switch (type) {
    case 'text':
    case 'textarea':
      return validateText(value, rules);
    case 'number':
      return validateNumber(value, rules);
    case 'date':
      return validateDate(value, rules);
    default:
      return null;
  }
};

// Validate a top-level question — checks `is_required` first, then the
// type-specific rules from `validation_rules`.
export const validateQuestion = (question, value) => {
  if (question.is_required && isEmpty(value)) {
    return 'This field is required.';
  }
  return validateByType(question.type, value, question.validation_rules);
};

// Validate a single table cell — checks the column's `required` flag, then
// the column's `validation` rules (which mirror question.validation_rules).
export const validateTableCell = (column, value) => {
  if (column.required && isEmpty(value)) {
    return 'This field is required.';
  }
  return validateByType(column.type, value, column.validation);
};
