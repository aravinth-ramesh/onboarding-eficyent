import { parsePhoneNumberFromString } from 'libphonenumber-js';

/**
 * Phone validation against the real numbering plan of the country in the dial
 * code, rather than a generic digit count (EOP-34).
 *
 * Values are stored as "+CC number" by PhoneField, so the country is carried
 * in the string itself and no separate country argument is needed.
 *
 * Empty is treated as valid here — whether a field is mandatory is the
 * required-check's job, not the format check's.
 */
export function isValidPhone(value) {
  const str = String(value ?? '').trim();
  if (str === '') return true;

  // Without a dial code there is no country to validate against; fall back to
  // an E.164-shaped digit-count check so such values aren't waved through.
  if (!str.startsWith('+')) {
    const digits = str.replace(/\D/g, '');

    return digits.length >= 7 && digits.length <= 15;
  }

  try {
    const parsed = parsePhoneNumberFromString(str);

    return Boolean(parsed && parsed.isValid());
  } catch {
    return false;
  }
}

/**
 * Format a stored value for display in its international form
 * (e.g. "+91 98765 43210"), falling back to the original when unparseable.
 */
export function formatPhone(value) {
  const str = String(value ?? '').trim();
  if (str === '' || !str.startsWith('+')) return str;

  try {
    const parsed = parsePhoneNumberFromString(str);

    return parsed ? parsed.formatInternational() : str;
  } catch {
    return str;
  }
}
