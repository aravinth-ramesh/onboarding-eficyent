import React from 'react';
import FileUploadField from './FileUploadField';
import TableField from './TableField';
import PhoneField from './PhoneField';
import MccField from './MccField';
import AddressField from './AddressField';
import UboField from './UboField';
// The HTML min/max here is progressive enhancement — JS validation in
// `utils/validation.js` is authoritative.
import { dateBound } from '../../utils/dateBounds';

/**
 * Cap input at the limit, counting the trimmed value. A raw `maxLength`
 * attribute counted leading/trailing spaces towards the budget and silently
 * stopped typing with no explanation (EOP-11, EOP-12).
 */
const capToLimit = (next, max) => {
  if (max == null) return next;
  const limit = Number(max);
  if (next.trim().length <= limit) return next;
  // Preserve any leading whitespace the user has typed while capping content.
  const leading = next.length - next.trimStart().length;

  return next.slice(0, leading + limit);
};

/** Live "n/max" counter so the limit is visible before it's hit (EOP-11). */
function CharacterCount({ value, max }) {
  if (max == null) return null;
  const used = String(value ?? '').trim().length;
  const limit = Number(max);
  const atLimit = used >= limit;

  return (
    <div className={`question-char-count${atLimit ? ' is-at-limit' : ''}`}>
      {used}/{limit}{atLimit ? ' — limit reached' : ''}
    </div>
  );
}

function QuestionField({ question, value, onChange, cellErrors, onRemoveUploaded }) {
  const handleChange = (newValue) => {
    onChange(question.id, newValue);
  };

  const v = question.validation_rules || {};

  switch (question.type) {
    case 'text':
      return (
        <>
          <input
            type="text"
            className="form-control"
            placeholder={question.placeholder || ''}
            value={value || ''}
            onChange={(e) => handleChange(capToLimit(e.target.value, v.max_length))}
          />
          <CharacterCount value={value} max={v.max_length} />
        </>
      );

    case 'textarea':
      return (
        <>
          <textarea
            className="form-control"
            rows={3}
            placeholder={question.placeholder || ''}
            value={value || ''}
            onChange={(e) => handleChange(capToLimit(e.target.value, v.max_length))}
          />
          <CharacterCount value={value} max={v.max_length} />
        </>
      );

    case 'number':
      return (
        <input
          type="number"
          className="form-control"
          placeholder={question.placeholder || ''}
          value={value || ''}
          min={v.min ?? undefined}
          max={v.max ?? undefined}
          onChange={(e) => handleChange(e.target.value)}
        />
      );

    case 'phone':
      return <PhoneField question={question} value={value} onChange={onChange} />;

    case 'mcc':
      return <MccField question={question} value={value} onChange={onChange} />;

    case 'address':
      return <AddressField question={question} value={value} onChange={onChange} />;

    case 'ubo':
      return <UboField question={question} value={value} onChange={onChange} />;

    case 'date':
      return (
        <input
          type="date"
          className="form-control"
          value={value || ''}
          min={dateBound(v, 'min')}
          max={dateBound(v, 'max')}
          onChange={(e) => handleChange(e.target.value)}
        />
      );

    case 'radio':
      return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
          {(question.options || []).map((option) => (
            <label
              key={option.value}
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 8,
                cursor: 'pointer',
                fontSize: '0.875rem',
              }}
            >
              <input
                type="radio"
                className="form-check-input"
                name={`question-${question.id}`}
                checked={value === option.value}
                onChange={() => handleChange(option.value)}
                style={{ margin: 0 }}
              />
              {option.label}
            </label>
          ))}
        </div>
      );

    case 'select':
      return (
        <select
          className="form-select"
          value={value || ''}
          onChange={(e) => handleChange(e.target.value)}
        >
          <option value="">-- Select --</option>
          {(question.options || []).map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      );

    case 'multi_select': {
      let selectedValues = [];
      try {
        selectedValues = typeof value === 'string' ? JSON.parse(value) : (value || []);
      } catch {
        selectedValues = [];
      }

      const toggleValue = (optValue) => {
        const newValues = selectedValues.includes(optValue)
          ? selectedValues.filter((v) => v !== optValue)
          : [...selectedValues, optValue];
        handleChange(newValues);
      };

      return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
          {(question.options || []).map((option) => (
            <label
              key={option.value}
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 8,
                cursor: 'pointer',
                fontSize: '0.875rem',
              }}
            >
              <input
                type="checkbox"
                className="form-check-input"
                checked={selectedValues.includes(option.value)}
                onChange={() => toggleValue(option.value)}
                style={{ margin: 0 }}
              />
              {option.label}
            </label>
          ))}
        </div>
      );
    }

    case 'file':
      return (
        <FileUploadField
          question={question}
          value={value}
          onChange={onChange}
          existingFiles={question.files}
          onRemoveUploaded={onRemoveUploaded}
        />
      );

    case 'table':
      return (
        <TableField
          question={question}
          value={value}
          onChange={onChange}
          cellErrors={cellErrors}
        />
      );

    default:
      return (
        <input
          type="text"
          className="form-control"
          value={value || ''}
          onChange={(e) => handleChange(e.target.value)}
        />
      );
  }
}

export default QuestionField;
