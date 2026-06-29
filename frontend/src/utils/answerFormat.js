import { MCC_LABELS } from '../config/mccCodes';
import { PHONE_COUNTRY_CODES } from '../config/phoneCountryCodes';

const COUNTRY_NAME = PHONE_COUNTRY_CODES.reduce((acc, c) => {
  acc[c.iso] = c.name;
  return acc;
}, {});

// Industry (MCC) value -> "code – label".
export function formatMcc(value) {
  if (!value) return '—';
  return MCC_LABELS[value] ? `${value} – ${MCC_LABELS[value]}` : value;
}

// Address value (object or JSON string) -> single readable line.
export function formatAddress(value) {
  let a = value;
  if (typeof a === 'string') {
    try { a = JSON.parse(a); } catch { return value; }
  }
  if (!a || typeof a !== 'object') return value || '—';
  const parts = [a.line1, a.city, a.state, COUNTRY_NAME[a.country] || a.country, a.postal]
    .filter((p) => p && String(p).trim());
  return parts.length ? parts.join(', ') : '—';
}
