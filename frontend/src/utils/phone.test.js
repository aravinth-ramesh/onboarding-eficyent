import { isValidPhone, formatPhone } from './phone';
import { validateByType, validateText } from './validation';

describe('phone validation by country (EOP-34)', () => {
  test('accepts real numbers for their own country', () => {
    expect(isValidPhone('+91 98765 43210')).toBe(true);   // India, 10 digits
    expect(isValidPhone('+44 7400 123456')).toBe(true);   // UK mobile
    expect(isValidPhone('+1 202 555 0182')).toBe(true);   // US
    expect(isValidPhone('+971 50 123 4567')).toBe(true);  // UAE
  });

  test('rejects a number that is wrong for the selected country code', () => {
    // The whole point of the ticket: right-ish digit count, wrong for +91.
    expect(isValidPhone('+91 12345')).toBe(false);
    // A US-length number claimed under the UK dial code.
    expect(isValidPhone('+44 1')).toBe(false);
    // Too long for any plan (E.164 caps at 15 digits).
    expect(isValidPhone('+91 9876543210123456')).toBe(false);
  });

  test('a number of the right length is still rejected on an invalid prefix', () => {
    // Correct digit count for each country, but not an allocated range — the
    // kind of value a pure length check waves through.
    expect(isValidPhone('+91 0000000000')).toBe(false);
    expect(isValidPhone('+44 0000 000000')).toBe(false);
    expect(isValidPhone('+1 000 000 0000')).toBe(false);
    expect(isValidPhone('+91 9876543210')).toBe(true);
  });

  test('empty is left to the required check', () => {
    expect(isValidPhone('')).toBe(true);
    expect(isValidPhone(null)).toBe(true);
    expect(isValidPhone(undefined)).toBe(true);
  });

  test('a value with no dial code falls back to an E.164 digit count', () => {
    expect(isValidPhone('5551234')).toBe(true);
    expect(isValidPhone('123')).toBe(false);
    expect(isValidPhone('1234567890123456')).toBe(false);
  });

  test('malformed input never throws', () => {
    expect(isValidPhone('+')).toBe(false);
    expect(isValidPhone('+++')).toBe(false);
    expect(isValidPhone('not a phone')).toBe(false);
  });

  test('formatPhone renders the international form', () => {
    expect(formatPhone('+919876543210')).toBe('+91 98765 43210');
    expect(formatPhone('')).toBe('');
    expect(formatPhone('not a phone')).toBe('not a phone');
  });
});

describe('phone wiring into the validation engine', () => {
  test('a phone-type question is validated even with no configured rules', () => {
    // Previously validateByType had no phone case at all, so these questions
    // were completely unvalidated.
    expect(validateByType('phone', '+91 12345', null)).toBeTruthy();
    expect(validateByType('phone', '+91 98765 43210', null)).toBeNull();
    expect(validateByType('phone', '', null)).toBeNull();
  });

  test('a text column with format: phone uses the same country-aware check', () => {
    const rules = { format: 'phone' };
    expect(validateText('+91 12345', rules)).toBeTruthy();
    expect(validateText('+91 98765 43210', rules)).toBeNull();
  });
});
