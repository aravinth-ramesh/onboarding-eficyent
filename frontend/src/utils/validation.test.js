import { validateText, validateByType, validateQuestion } from './validation';

describe('field validation engine', () => {
  test('whitespace-only fails a required field (EOP-43/EOP-39)', () => {
    const q = { is_required: true, type: 'text' };
    expect(validateQuestion(q, '   ')).toBe('This field is required.');
    expect(validateQuestion(q, 'Acme')).toBeNull();
  });

  test('email format (EOP-38/EOP-42)', () => {
    const rules = { format: 'email' };
    expect(validateText('not-an-email', rules)).toBeTruthy();
    expect(validateText('a@b.com', rules)).toBeNull();
  });

  test('phone format (EOP-38/EOP-42)', () => {
    const rules = { format: 'phone' };
    expect(validateText('abc', rules)).toBeTruthy();
    expect(validateText('123', rules)).toBeTruthy(); // too few digits
    expect(validateText('+91 98765 43210', rules)).toBeNull();
  });

  test('url accepts uppercase (EOP-54)', () => {
    const rules = { format: 'url' };
    expect(validateText('HTTPS://WWW.GOOGLE.COM', rules)).toBeNull();
    expect(validateText('https://www.google.com', rules)).toBeNull();
    expect(validateText('not a url', rules)).toBeTruthy();
  });

  test('alpha rejects numeric-only names (EOP-37)', () => {
    const rules = { format: 'alpha' };
    expect(validateText('12345', rules)).toBeTruthy();
    expect(validateText("Jean-Pierre O'Brien", rules)).toBeNull();
  });

  test('max_length caps ID numbers (EOP-36)', () => {
    const rules = { max_length: 30 };
    expect(validateText('x'.repeat(200), rules)).toBeTruthy();
    expect(validateText('ABC123', rules)).toBeNull();
  });

  test('validateByType dispatches text formats', () => {
    expect(validateByType('text', 'bad', { format: 'email' })).toBeTruthy();
    expect(validateByType('text', 'a@b.co', { format: 'email' })).toBeNull();
  });

  test('min_length rejects a single-character ID (EOP-39)', () => {
    const rules = { min_length: 4, max_length: 30 };
    expect(validateText('X', rules)).toBeTruthy();
    expect(validateText('   ', rules)).toBeNull(); // empty is the required-check's job
    expect(validateText('AB1234', rules)).toBeNull();
  });

  test('requires_letter rejects a digits-only Position (EOP-37)', () => {
    const rules = { format: 'alphanumeric', requires_letter: true };
    expect(validateText('12345', rules)).toBeTruthy();
    expect(validateText('Director 2', rules)).toBeNull();
    expect(validateText('CFO', rules)).toBeNull();
  });

  test('contact format needs both an email and a number (EOP-42)', () => {
    const rules = { format: 'contact' };
    expect(validateText('mlro@acme.com', rules)).toBeTruthy(); // no number
    expect(validateText('+91 98765 43210', rules)).toBeTruthy(); // no email
    expect(validateText('mlro@acme.com / +91 98765 43210', rules)).toBeNull();
  });

  test('a date column rejects a future date of birth (EOP-32)', () => {
    const rules = { allow_future: false };
    const future = new Date();
    future.setFullYear(future.getFullYear() + 1);
    const futureIso = future.toISOString().slice(0, 10);

    expect(validateByType('date', futureIso, rules)).toBe('Future dates are not allowed.');
    expect(validateByType('date', '1985-04-12', rules)).toBeNull();
  });
});
