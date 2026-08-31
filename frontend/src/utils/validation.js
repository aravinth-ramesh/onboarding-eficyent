import { isValidPhone } from './phone';

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
  // Whitespace-only strings are empty — a required field can't be satisfied
  // with spaces alone (bug report EOP-43, EOP-39).
  (typeof value === 'string' && value.trim() === '') ||
  (Array.isArray(value) && value.length === 0);

// Built-in formats a question can request via validation_rules.format, so a
// plain text field can enforce email / URL / phone / alphabetic input without
// each question needing a hand-written regex. URL and email are matched
// case-insensitively (EOP-54: uppercase URLs are valid); phone is checked
// against the selected country's numbering plan (EOP-34).
const FORMAT_VALIDATORS = {
  email: {
    test: (s) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s),
    message: 'Enter a valid email address.',
  },
  url: {
    test: (s) => /^(https?:\/\/)?([\w-]+\.)+[\w-]{2,}(\/\S*)?$/i.test(s),
    message: 'Enter a valid website URL.',
  },
  phone: {
    // Validated against the real numbering plan of the country in the dial
    // code, not just a digit count — "+91 12345" is the right length for a
    // generic check but is not a valid Indian number (EOP-34).
    test: (s) => isValidPhone(s),
    message: 'Enter a valid phone number for the selected country.',
  },
  alpha: {
    test: (s) => /^[\p{L}\s.'-]+$/u.test(s),
    message: 'Only letters are allowed.',
  },
  alphanumeric: {
    test: (s) => /^[\p{L}\p{N}\s.'-]+$/u.test(s),
    message: 'Only letters and numbers are allowed.',
  },
  // SWIFT/BIC: 8 or 11 characters, bank + country + location (+ branch).
  swift: {
    test: (s) => /^[A-Za-z]{6}[A-Za-z0-9]{2}([A-Za-z0-9]{3})?$/.test(s.replace(/\s/g, '')),
    message: 'Enter a valid 8 or 11 character SWIFT/BIC code.',
  },
  // IBAN: structure plus the mod-97 check, which catches transposed digits a
  // pattern alone cannot (EOP-24).
  iban: {
    test: (s) => isValidIban(s),
    message: 'Enter a valid IBAN.',
  },
  // One field holds either form, so requiring an IBAN rejected every valid
  // domestic account number (retest item 5). Accept an IBAN, or an account
  // number: alphanumeric, optionally spaced or hyphened, 6-34 characters.
  account_or_iban: {
    test: (s) => {
      const compact = s.replace(/[\s-]/g, '');
      if (/^[A-Za-z]{2}\d{2}[A-Za-z0-9]+$/.test(compact)) return isValidIban(s);
      return /^[A-Za-z0-9]{6,34}$/.test(compact);
    },
    message: 'Enter a valid account number or IBAN.',
  },
  // Postal codes differ far too much between countries to pin to one pattern
  // (Singapore is six digits, the UK "SW1A 1AA", Ireland "D02 AF30"), so this
  // checks shape rather than a country format: at least three characters,
  // starting and ending alphanumeric, with spaces and hyphens between. The
  // field previously accepted a single character (retest item 21).
  postal_code: {
    test: (s) => /^[\p{L}\p{N}][\p{L}\p{N}\s-]{1,10}[\p{L}\p{N}]$/u.test(s.trim()),
    message: 'Enter a valid postal code.',
  },
  // ID and passport numbers legitimately carry - and / separators, which the
  // alphanumeric format rejected (retest item 31).
  id_document: {
    test: (s) => /^[\p{L}\p{N}][\p{L}\p{N}\s/-]*[\p{L}\p{N}]$/u.test(s.trim()),
    message: 'Only letters, numbers, hyphens and slashes are allowed.',
  },
  // A single field holding both an email address and a phone number, e.g.
  // "AML Officer Contact Email & Number" — require both parts (EOP-42).
  contact: {
    test: (s) =>
      /[^\s@]+@[^\s@]+\.[^\s@]+/.test(s) && (s.replace(/[^\d]/g, '').length >= 7),
    message: 'Enter both a valid email address and a phone number.',
  },
};

/**
 * IBAN check: length/shape per ISO 13616, then the mod-97 checksum. A regex
 * alone accepts a transposed digit, which is exactly the typo this catches
 * (EOP-24).
 */
function isValidIban(value) {
  const iban = String(value || '').replace(/\s/g, '').toUpperCase();
  if (!/^[A-Z]{2}[0-9]{2}[A-Z0-9]{10,30}$/.test(iban)) return false;

  // Move the first four characters to the end, then letters -> 10..35.
  const rearranged = iban.slice(4) + iban.slice(0, 4);
  const digits = rearranged.replace(/[A-Z]/g, (c) => String(c.charCodeAt(0) - 55));

  // Mod-97 in chunks, since the number exceeds Number.MAX_SAFE_INTEGER.
  let remainder = 0;
  for (let i = 0; i < digits.length; i += 7) {
    remainder = Number(String(remainder) + digits.substr(i, 7)) % 97;
  }

  return remainder === 1;
}

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
  const str = String(value).trim();

  if (rules.min_length != null && str.length < Number(rules.min_length)) {
    return `Must be at least ${rules.min_length} characters.`;
  }
  if (rules.max_length != null && str.length > Number(rules.max_length)) {
    return `Must be at most ${rules.max_length} characters.`;
  }
  if (rules.format && FORMAT_VALIDATORS[rules.format]) {
    const fmt = FORMAT_VALIDATORS[rules.format];
    if (!fmt.test(str)) {
      return rules.pattern_message || fmt.message;
    }
  }
  // Fields like Position are alphanumeric but must not be digits-only
  // ("12345" is not a job title) — EOP-37.
  if (rules.requires_letter && !/\p{L}/u.test(str)) {
    return 'Must contain letters, not only numbers.';
  }
  if (rules.pattern) {
    const re = compileRegex(String(rules.pattern));
    // Match case-insensitively when the field is a URL (EOP-54).
    const patternRe = re && rules.format === 'url' ? new RegExp(re.source, 'i') : re;
    if (patternRe && !patternRe.test(str)) {
      return rules.pattern_message || 'Value does not match the required format.';
    }
  }
  return null;
};

export const validateNumber = (value, rules = {}) => {
  if (isEmpty(value)) return null;
  const num = Number(value);
  if (Number.isNaN(num)) return 'Must be a valid number.';

  // A count of transactions can't be fractional (EOP-18).
  if (rules.integer && !Number.isInteger(num)) {
    return 'Must be a whole number.';
  }

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

  // Minimum age, expressed relative to today rather than as a fixed max_date
  // so the rule can't go stale (EOP-32).
  if (rules.min_age != null) {
    const years = Number(rules.min_age);
    const earliestBirthday = new Date(today);
    earliestBirthday.setFullYear(earliestBirthday.getFullYear() - years);
    if (date.getTime() > earliestBirthday.getTime()) {
      return `User must be at least ${years} years old.`;
    }
  }

  // A date centuries in the past is a typo, not a birth date.
  if (rules.min_date == null && date.getFullYear() < 1900) {
    return 'Enter a valid date.';
  }

  return null;
};

// Dispatcher used for both top-level questions and individual table cells.
// `type` is one of the question/column types (text, textarea, number, date,
// ...) and `rules` is the validation metadata block sent from the backend.
/**
 * Beneficial-owner list. The `ubo` widget marks Full Name, Ownership %,
 * Nationality and ID number as required and warns when the total exceeds
 * 100%, but none of that was ever enforced — the question fell through to
 * `default` and the client advanced with half-filled owners (EOP-31).
 */
export const validateUbo = (value) => {
  let owners = value;
  if (typeof owners === 'string') {
    try { owners = JSON.parse(owners); } catch { return null; }
  }
  if (!Array.isArray(owners) || owners.length === 0) return null;

  const blank = (v) => v === null || v === undefined || String(v).trim() === '';

  for (let i = 0; i < owners.length; i += 1) {
    const owner = owners[i] || {};
    // Ignore an entirely empty row — that's the "add" scaffolding.
    const touched = Object.values(owner).some((v) => !blank(v));
    if (!touched) continue;

    for (const [field, label] of [
      ['full_name', 'Full name'],
      ['ownership_percent', 'Ownership %'],
      ['nationality', 'Nationality'],
      ['id_number', 'ID / passport number'],
    ]) {
      if (blank(owner[field])) {
        return `Beneficial owner ${i + 1}: ${label} is required.`;
      }
    }
  }

  const total = owners.reduce((sum, o) => sum + (Number(o?.ownership_percent) || 0), 0);
  if (total > 100) {
    return `Total ownership is ${total}%, which cannot exceed 100%.`;
  }

  return null;
};

export const validateByType = (type, value, rules) => {
  // A phone field carries its own country in the "+CC number" value, so it is
  // always checked against that country's numbering plan — it needs no
  // configured rules, and previously had no case here at all, which is why
  // phone questions went completely unvalidated (EOP-34).
  if (type === 'phone') {
    return isEmpty(value) || isValidPhone(value)
      ? null
      : 'Enter a valid phone number for the selected country.';
  }

  // Like phone, a UBO list validates itself and needs no configured rules.
  if (type === 'ubo') {
    return validateUbo(value);
  }

  // A structured address had no validator at all, so its postal code accepted
  // a single character — or anything else (retest item 21).
  if (type === 'address') {
    return validateAddress(value);
  }

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

/**
 * Check the parts of a structured address that have a checkable shape. Only the
 * postal code qualifies — street, city and state are free text anywhere in the
 * world. Blank stays valid here; `is_required` decides whether blank is allowed.
 */
export const validateAddress = (value) => {
  let addr = value;
  if (typeof addr === 'string') {
    try { addr = JSON.parse(addr); } catch { return null; }
  }
  if (!addr || typeof addr !== 'object') return null;

  const part = (key) => (addr[key] == null ? '' : String(addr[key]).trim());

  const postal = part('postal');
  if (postal !== '' && !FORMAT_VALIDATORS.postal_code.test(postal)) {
    return FORMAT_VALIDATORS.postal_code.message;
  }

  // A single character passed for street, city and state, so "a" satisfied a
  // mandatory address (retest item 9). These are free text worldwide, so the
  // check is a floor on length plus at least one letter, not a format.
  for (const [key, label, min] of [
    ['line1', 'Street address', 4],
    ['city', 'City', 2],
    ['state', 'State / Province', 2],
  ]) {
    const text = part(key);
    if (text === '') continue;
    if (text.length < min) return `${label} must be at least ${min} characters.`;
    if (!/\p{L}/u.test(text)) return `${label} must contain letters.`;
  }

  return null;
};

// Beneficial ownership cannot add up to more than the whole company. The rule
// lived only in validateUbo, but consolidating the two overlapping UBO widgets
// left the surviving question typed `table`, which routes straight past it — so
// two owners could each hold 100% (retest item 29).
const OWNERSHIP_KEYS = ['%_ownership', 'ownership_percent', 'ownership', 'percentage_owned'];

const ownershipColumnKey = (columns) => {
  if (!Array.isArray(columns)) return null;

  const byKey = columns.find((c) => OWNERSHIP_KEYS.includes(String(c?.key || '').toLowerCase()));
  if (byKey) return byKey.key;

  // Fall back to the label so a renamed column still gets caught.
  const byLabel = columns.find((c) => /ownership|owned/i.test(String(c?.label || '')));
  return byLabel ? byLabel.key : null;
};

export const validateOwnershipTotal = (value, columns) => {
  const key = ownershipColumnKey(columns);
  if (!key) return null;

  let rows = value;
  if (typeof rows === 'string') {
    try { rows = JSON.parse(rows); } catch { return null; }
  }
  if (!Array.isArray(rows) || rows.length === 0) return null;

  const total = rows.reduce((sum, row) => sum + (Number(row?.[key]) || 0), 0);
  const rounded = Math.round(total * 100) / 100;

  return rounded > 100
    ? `Total ownership is ${rounded}%, which cannot exceed 100%.`
    : null;
};

// Validate a top-level question — checks `is_required` first, then the
// type-specific rules from `validation_rules`.
export const validateQuestion = (question, value) => {
  if (question.is_required && isEmpty(value)) {
    return 'This field is required.';
  }

  if (question.type === 'table') {
    const overOwned = validateOwnershipTotal(value, question.options?.columns);
    if (overOwned) return overOwned;
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
