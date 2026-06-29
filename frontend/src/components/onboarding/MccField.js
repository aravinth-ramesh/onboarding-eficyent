import React from 'react';
import { MCC_GROUPS } from '../../config/mccCodes';

// Industry classification picker (Merchant Category Codes), grouped by sector.
function MccField({ question, value, onChange }) {
  return (
    <select
      className="form-select"
      value={value || ''}
      onChange={(e) => onChange(question.id, e.target.value)}
    >
      <option value="">-- Select industry --</option>
      {MCC_GROUPS.map((group) => (
        <optgroup key={group.category} label={group.category}>
          {group.codes.map((c) => (
            <option key={c.code} value={c.code}>{c.code} – {c.label}</option>
          ))}
        </optgroup>
      ))}
    </select>
  );
}

export default MccField;
