import React, { useMemo } from 'react';
import { PHONE_COUNTRY_CODES } from '../../config/phoneCountryCodes';

const ID_TYPES = [
  { value: 'passport', label: 'Passport' },
  { value: 'national_id', label: 'National ID' },
  { value: 'drivers_license', label: "Driver's License" },
  { value: 'other', label: 'Other' },
];

const blankOwner = () => ({
  full_name: '', ownership_percent: '', nationality: '', date_of_birth: '',
  id_type: '', id_number: '', is_pep: '',
});

function parseOwners(value) {
  let v = value;
  if (typeof v === 'string') {
    try { v = JSON.parse(v); } catch { v = []; }
  }
  return Array.isArray(v) && v.length > 0 ? v : [blankOwner()];
}

/**
 * Flat list of Ultimate Beneficial Owners. Each owner is a structured record;
 * the list shows a running ownership-% total with a warning when it exceeds
 * 100%. Stored as a JSON array (empty string when blank, for required checks).
 */
function UboField({ question, value, onChange }) {
  const owners = useMemo(() => parseOwners(value), [value]);

  const totalPercent = owners.reduce((sum, o) => sum + (parseFloat(o.ownership_percent) || 0), 0);
  const over = totalPercent > 100;

  const emit = (next) => {
    const isEmpty = next.every((o) => Object.values(o).every((v) => !v || !String(v).trim()));
    onChange(question.id, isEmpty ? '' : next);
  };

  const update = (index, patch) => emit(owners.map((o, i) => (i === index ? { ...o, ...patch } : o)));
  const addOwner = () => emit([...owners, blankOwner()]);
  const removeOwner = (index) => emit(owners.filter((_, i) => i !== index));

  return (
    <div className="ubo-field">
      <div className="ubo-hint">Disclose every individual who ultimately owns or controls 25% or more of the company.</div>

      <div className="table-field-form">
        {owners.map((owner, index) => (
          <div key={index} className="table-field-card">
            <div className="table-field-card-header">
              <span>Beneficial Owner {index + 1}</span>
              {owners.length > 1 && (
                <button type="button" className="table-field-remove-btn" title="Remove owner" onClick={() => removeOwner(index)}>
                  {'✕'}
                </button>
              )}
            </div>
            <div className="table-field-card-grid">
              <div className="table-field-card-field">
                <label className="table-field-card-label">Full Name<span className="required">*</span></label>
                <input className="form-control table-field-input" value={owner.full_name || ''} onChange={(e) => update(index, { full_name: e.target.value })} />
              </div>
              <div className="table-field-card-field">
                <label className="table-field-card-label">Ownership %<span className="required">*</span></label>
                <input type="number" min="0" max="100" className="form-control table-field-input" value={owner.ownership_percent || ''} onChange={(e) => update(index, { ownership_percent: e.target.value })} />
              </div>
              <div className="table-field-card-field">
                <label className="table-field-card-label">Nationality<span className="required">*</span></label>
                <select className="form-select table-field-input" value={owner.nationality || ''} onChange={(e) => update(index, { nationality: e.target.value })}>
                  <option value="">-- Select --</option>
                  {PHONE_COUNTRY_CODES.map((c) => <option key={c.iso} value={c.iso}>{c.name}</option>)}
                </select>
              </div>
              <div className="table-field-card-field">
                <label className="table-field-card-label">Date of Birth</label>
                <input type="date" className="form-control table-field-input" value={owner.date_of_birth || ''} onChange={(e) => update(index, { date_of_birth: e.target.value })} />
              </div>
              <div className="table-field-card-field">
                <label className="table-field-card-label">ID Type</label>
                <select className="form-select table-field-input" value={owner.id_type || ''} onChange={(e) => update(index, { id_type: e.target.value })}>
                  <option value="">-- Select --</option>
                  {ID_TYPES.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                </select>
              </div>
              <div className="table-field-card-field">
                <label className="table-field-card-label">ID / Passport Number<span className="required">*</span></label>
                <input className="form-control table-field-input" value={owner.id_number || ''} onChange={(e) => update(index, { id_number: e.target.value })} />
              </div>
              <div className="table-field-card-field">
                <label className="table-field-card-label">Politically Exposed Person (PEP)?</label>
                <select className="form-select table-field-input" value={owner.is_pep || ''} onChange={(e) => update(index, { is_pep: e.target.value })}>
                  <option value="">-- Select --</option>
                  <option value="no">No</option>
                  <option value="yes">Yes</option>
                </select>
              </div>
            </div>
          </div>
        ))}
      </div>

      <div className="ubo-footer">
        <button type="button" className="table-field-add-btn" onClick={addOwner}>+ Add Beneficial Owner</button>
        <div className={`ubo-total ${over ? 'over' : ''}`}>
          Total ownership: <strong>{totalPercent}%</strong>
          {over && <span className="ubo-total-warn"> — exceeds 100%</span>}
        </div>
      </div>
    </div>
  );
}

export default UboField;
