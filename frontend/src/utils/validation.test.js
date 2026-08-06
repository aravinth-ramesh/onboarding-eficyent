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
});
