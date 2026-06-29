import React, { useMemo } from 'react';
import { PHONE_COUNTRY_CODES, DEFAULT_DIAL } from '../../config/phoneCountryCodes';

// Split a stored "+CC number" value into its dial code and the local number.
function parseValue(value) {
  if (!value || typeof value !== 'string') return { dial: DEFAULT_DIAL, number: '' };
  const m = value.match(/^(\+\d{1,4})\s*(.*)$/);
  if (m) return { dial: m[1], number: m[2] };
  return { dial: DEFAULT_DIAL, number: value };
}

/**
 * Phone number field: a country dial-code dropdown + a number input.
 * Stored as a single "+CC number" string (empty when no number is entered,
 * so required validation behaves correctly).
 */
function PhoneField({ question, value, onChange }) {
  const { dial, number } = useMemo(() => parseValue(value), [value]);

  const emit = (nextDial, nextNumber) => {
    const cleaned = (nextNumber || '').replace(/[^\d\s-]/g, '').trim();
    onChange(question.id, cleaned ? `${nextDial} ${cleaned}` : '');
  };

  return (
    <div className="phone-field">
      <select
        className="form-select phone-field-dial"
        value={dial}
        onChange={(e) => emit(e.target.value, number)}
        aria-label="Country dial code"
      >
        {PHONE_COUNTRY_CODES.map((c) => (
          <option key={c.iso} value={c.dial}>
            {c.name} ({c.dial})
          </option>
        ))}
      </select>
      <input
        type="tel"
        inputMode="tel"
        className="form-control phone-field-number"
        placeholder={question.placeholder || 'Phone number'}
        value={number}
        onChange={(e) => emit(dial, e.target.value)}
      />
    </div>
  );
}

export default PhoneField;
