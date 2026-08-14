import { DEFAULT_DIAL } from '../config/phoneCountryCodes';

/**
 * A phone answer is stored as one "+CC number" string so it round-trips through
 * a plain text column, but it is entered as two controls. These helpers are the
 * single place that conversion lives, shared by the standalone PhoneField and
 * the phone cell inside a table — the table had no phone handling at all, so
 * signatories and owners typed a country code into the number box by hand
 * (retest items 28 and 31).
 */

/** Split a stored value into its dial code and local number. */
export const parsePhone = (value) => {
  if (!value || typeof value !== 'string') return { dial: DEFAULT_DIAL, number: '' };

  const match = value.match(/^(\+\d{1,4})\s*(.*)$/);

  return match ? { dial: match[1], number: match[2] } : { dial: DEFAULT_DIAL, number: value };
};

/**
 * Recombine the two controls into the stored form. Blank when no number was
 * entered, so a lone dial code never counts as an answer and `is_required`
 * still catches an empty field.
 */
export const formatPhone = (dial, number) => {
  const cleaned = (number || '').replace(/[^\d\s-]/g, '').trim();

  return cleaned ? `${dial} ${cleaned}` : '';
};
