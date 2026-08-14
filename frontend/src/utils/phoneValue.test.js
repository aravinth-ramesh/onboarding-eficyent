import { parsePhone, formatPhone } from './phoneValue';
import { DEFAULT_DIAL } from '../config/phoneCountryCodes';

describe('phone value round-trip (retest items 28, 31)', () => {
  it('splits a stored value into dial code and number', () => {
    expect(parsePhone('+65 9856545')).toEqual({ dial: '+65', number: '9856545' });
  });

  it('treats a bare number as the default country', () => {
    expect(parsePhone('9856545')).toEqual({ dial: DEFAULT_DIAL, number: '9856545' });
  });

  it('falls back to the default for an empty value', () => {
    expect(parsePhone('')).toEqual({ dial: DEFAULT_DIAL, number: '' });
    expect(parsePhone(null)).toEqual({ dial: DEFAULT_DIAL, number: '' });
  });

  it('recombines into the stored form', () => {
    expect(formatPhone('+65', '9856545')).toBe('+65 9856545');
  });

  it('stores blank when no number is entered, so a lone dial code is not an answer', () => {
    expect(formatPhone('+65', '')).toBe('');
    expect(formatPhone('+65', '   ')).toBe('');
  });

  it('strips characters that are not part of a number', () => {
    expect(formatPhone('+91', '98765 43210abc')).toBe('+91 98765 43210');
  });

  it('round-trips a value unchanged', () => {
    const stored = '+44 7700 900123';
    const { dial, number } = parsePhone(stored);
    expect(formatPhone(dial, number)).toBe(stored);
  });
});
