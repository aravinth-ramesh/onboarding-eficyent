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
  // Prefer the options the backend seeds onto the question so the picker and
  // every admin surface read the same list; fall back to the bundled codes.
  const options = useMemo(() => {
    const seeded = question.options;
    if (Array.isArray(seeded) && seeded.length > 0) {
      return seeded.map((o) => ({
        value: o.value,
        label: `${o.value} – ${o.label}`,
        keywords: `${o.label} ${o.group || ''}`,
      }));
    }

    return MCC_GROUPS.flatMap((group) =>
      group.codes.map((c) => ({
        value: c.code,
        label: `${c.code} – ${c.label}`,
        keywords: `${c.label} ${group.category}`,
      }))
    );
  }, [question.options]);

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
