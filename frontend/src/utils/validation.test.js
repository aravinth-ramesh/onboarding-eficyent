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

  test('integer rule rejects a fractional count (EOP-18)', () => {
    const rules = { min: 0, integer: true };
    expect(validateByType('number', '12.5', rules)).toBe('Must be a whole number.');
    expect(validateByType('number', '12', rules)).toBeNull();
    expect(validateByType('number', '-1', rules)).toBeTruthy();
  });

  test('IBAN uses the mod-97 check, not just a shape (EOP-24)', () => {
    const rules = { format: 'iban' };
    expect(validateText('GB82 WEST 1234 5698 7654 32', rules)).toBeNull();
    expect(validateText('DE89370400440532013000', rules)).toBeNull();
    // Correct shape, transposed digits — a regex alone would accept this.
    expect(validateText('GB82WEST12345698765433', rules)).toBeTruthy();
    expect(validateText('not-an-iban', rules)).toBeTruthy();
  });

  test('SWIFT/BIC accepts 8 or 11 characters only (EOP-24)', () => {
    const rules = { format: 'swift' };
    expect(validateText('DEUTDEFF', rules)).toBeNull();
    expect(validateText('DEUTDEFF500', rules)).toBeNull();
    expect(validateText('DEUT', rules)).toBeTruthy();
    expect(validateText('1234DEFF', rules)).toBeTruthy();
  });

  test('a date of birth must be at least 18 years ago (EOP-32)', () => {
    const rules = { allow_future: false, min_age: 18 };
    const yearsAgo = (n) => {
      const d = new Date();
      d.setFullYear(d.getFullYear() - n);
      return d.toISOString().slice(0, 10);
    };

    expect(validateByType('date', yearsAgo(17), rules)).toBe('User must be at least 18 years old.');
    expect(validateByType('date', yearsAgo(18), rules)).toBeNull();
    expect(validateByType('date', yearsAgo(40), rules)).toBeNull();
    // A stray year is a typo, not a birth date.
    expect(validateByType('date', '1832-04-12', rules)).toBe('Enter a valid date.');
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

describe('ownership totals (retest item 29)', () => {
  // Consolidating the two UBO widgets left the surviving question typed
  // `table`, which routed past validateUbo's 100% ceiling entirely.
  const columns = [
    { key: 'full_legal_name', label: 'Full Legal Name', type: 'text' },
    { key: '%_ownership', label: '% Ownership', type: 'number' },
  ];
  const question = { type: 'table', is_required: false, options: { columns } };

  it('rejects two owners each holding the whole company', () => {
    const value = JSON.stringify([{ '%_ownership': 100 }, { '%_ownership': 100 }]);
    expect(validateQuestion(question, value)).toBe(
      'Total ownership is 200%, which cannot exceed 100%.'
    );
  });

  it('allows a split totalling exactly 100', () => {
    const value = JSON.stringify([{ '%_ownership': 60 }, { '%_ownership': 40 }]);
    expect(validateQuestion(question, value)).toBeNull();
  });

  it('leaves a table without an ownership column alone', () => {
    const bank = {
      type: 'table',
      is_required: false,
      options: { columns: [{ key: 'bank_name', label: 'Bank Name', type: 'text' }] },
    };
    expect(validateQuestion(bank, JSON.stringify([{ bank_name: 'Acme' }]))).toBeNull();
  });
});

describe('postal codes and ID documents (retest items 21, 31)', () => {
  const address = (postal) => JSON.stringify({ line1: '1 High St', city: 'London', postal });

  it('rejects a single-character postal code', () => {
    expect(validateByType('address', address('1'))).toBe('Enter a valid postal code.');
  });

  it('accepts postal codes from different countries', () => {
    ['560001', 'SW1A 1AA', 'D02 AF30', '12345-6789'].forEach((code) => {
      expect(validateByType('address', address(code))).toBeNull();
    });
  });

  it('leaves a blank postal code to the required flag', () => {
    expect(validateByType('address', address(''))).toBeNull();
  });

  it('accepts ID numbers containing hyphens and slashes', () => {
    const rules = { format: 'id_document', min_length: 4, max_length: 30 };
    expect(validateByType('text', 'AB-1234/X', rules)).toBeNull();
    expect(validateByType('text', 'S1234567D', rules)).toBeNull();
  });

  it('still rejects an ID number of punctuation alone', () => {
    const rules = { format: 'id_document', min_length: 4, max_length: 30 };
    expect(validateByType('text', '///-', rules)).not.toBeNull();
  });
});
