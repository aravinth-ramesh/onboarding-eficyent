import React, { useMemo } from 'react';
import { PHONE_COUNTRY_CODES } from '../../config/phoneCountryCodes';
import { parsePhone, formatPhone } from '../../utils/phoneValue';

/**
 * Phone number field: a country dial-code dropdown + a number input.
 * Stored as a single "+CC number" string (empty when no number is entered,
 * so required validation behaves correctly).
 */
function PhoneField({ question, value, onChange }) {
  const { dial, number } = useMemo(() => parsePhone(value), [value]);

  const emit = (nextDial, nextNumber) => onChange(question.id, formatPhone(nextDial, nextNumber));

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
