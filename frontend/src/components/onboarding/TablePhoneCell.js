import React from 'react';
import { PHONE_COUNTRY_CODES } from '../../config/phoneCountryCodes';
import { usePhoneParts } from '../../hooks/usePhoneParts';

/**
 * A phone cell inside a table: the same dial-code dropdown and number input a
 * standalone phone question gets (retest items 28 and 31).
 *
 * This is its own component rather than inline JSX because the dial code is
 * held in a hook, and a cell renderer is called per cell — hooks have to sit at
 * a component's top level to keep their state attached to the right cell.
 */
function TablePhoneCell({ column, value, onChange }) {
  const { dial, number, setDial, setNumber } = usePhoneParts(value, onChange);

  return (
    <div className="phone-field">
      <select
        className="form-select form-select-sm phone-field-dial"
        value={dial}
        onChange={(e) => setDial(e.target.value)}
        aria-label={`${column.label || 'Phone'} country dial code`}
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
        className="form-control form-control-sm phone-field-number"
        placeholder={column.placeholder || 'Phone number'}
        value={number}
        onChange={(e) => setNumber(e.target.value)}
      />
    </div>
  );
}

export default TablePhoneCell;
