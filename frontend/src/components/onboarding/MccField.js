import React, { useMemo } from 'react';
import { MCC_GROUPS } from '../../config/mccCodes';
import SearchableSelect from '../common/SearchableSelect';

// Industry classification picker (Merchant Category Codes).
//
// Was a native <select>, whose type-ahead only matches from the start of the
// option text — and the text begins with the code, so typing an industry name
// like "veterinary" matched nothing (EOP-14). The searchable control matches
// the code, the label and the sector.
function MccField({ question, value, onChange }) {
  const options = useMemo(
    () =>
      MCC_GROUPS.flatMap((group) =>
        group.codes.map((c) => ({
          value: c.code,
          label: `${c.code} – ${c.label}`,
          keywords: `${c.label} ${group.category}`,
        }))
      ),
    []
  );

  return (
    <SearchableSelect
      options={options}
      value={value || ''}
      onChange={(next) => onChange(question.id, next)}
      placeholder="-- Select industry --"
      ariaLabel="Industry classification"
    />
  );
}

export default MccField;
