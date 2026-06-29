import React from 'react';
import { PHONE_COUNTRY_CODES } from '../../config/phoneCountryCodes';
import { statesFor } from '../../config/states';

function parseAddress(value) {
  if (!value) return {};
  if (typeof value === 'object') return value;
  try {
    return JSON.parse(value);
  } catch {
    return {};
  }
}

/**
 * Structured address field: street, country, state/province (auto-loaded
 * dropdown when available for the country, free text otherwise), city and
 * postal code. Stored as a JSON object (empty string when fully blank, so
 * required validation works).
 */
function AddressField({ question, value, onChange }) {
  const addr = parseAddress(value);
  const states = statesFor(addr.country);

  const update = (patch) => {
    const next = { ...addr, ...patch };
    const isEmpty = Object.values(next).every((v) => !v || !String(v).trim());
    onChange(question.id, isEmpty ? '' : next);
  };

  return (
    <div className="address-field">
      <input
        type="text"
        className="form-control"
        placeholder="Street address"
        value={addr.line1 || ''}
        onChange={(e) => update({ line1: e.target.value })}
      />

      <div className="address-row">
        <select
          className="form-select"
          value={addr.country || ''}
          onChange={(e) => update({ country: e.target.value, state: '' })}
          aria-label="Country"
        >
          <option value="">Country</option>
          {PHONE_COUNTRY_CODES.map((c) => (
            <option key={c.iso} value={c.iso}>{c.name}</option>
          ))}
        </select>

        {states ? (
          <select
            className="form-select"
            value={addr.state || ''}
            onChange={(e) => update({ state: e.target.value })}
            aria-label="State or province"
          >
            <option value="">State / Province</option>
            {states.map((s) => (
              <option key={s} value={s}>{s}</option>
            ))}
          </select>
        ) : (
          <input
            type="text"
            className="form-control"
            placeholder="State / Province"
            value={addr.state || ''}
            onChange={(e) => update({ state: e.target.value })}
          />
        )}
      </div>

      <div className="address-row">
        <input
          type="text"
          className="form-control"
          placeholder="City"
          value={addr.city || ''}
          onChange={(e) => update({ city: e.target.value })}
        />
        <input
          type="text"
          className="form-control"
          placeholder="Postal code"
          value={addr.postal || ''}
          onChange={(e) => update({ postal: e.target.value })}
        />
      </div>
    </div>
  );
}

export default AddressField;
