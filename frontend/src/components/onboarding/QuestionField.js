import React from 'react';
import FileUploadField from './FileUploadField';
import TableField from './TableField';

// Resolve a date input's `min`/`max` HTML attribute by combining the explicit
// `min_date`/`max_date` rules with `allow_past`/`allow_future` flags. This is
// progressive enhancement — JS validation in `utils/validation.js` is
// authoritative.
const todayIso = () => new Date().toISOString().slice(0, 10);
const dateBound = (rules, edge) => {
  if (!rules) return undefined;
  if (edge === 'min') {
    if (rules.min_date) return rules.min_date;
    if (rules.allow_past === false) return todayIso();
    return undefined;
  }
  if (rules.max_date) return rules.max_date;
  if (rules.allow_future === false) return todayIso();
  return undefined;
};

function QuestionField({ question, value, onChange, cellErrors }) {
  const handleChange = (newValue) => {
    onChange(question.id, newValue);
  };

  const v = question.validation_rules || {};

  switch (question.type) {
    case 'text':
      return (
        <input
          type="text"
          className="form-control"
          placeholder={question.placeholder || ''}
          value={value || ''}
          maxLength={v.max_length ?? undefined}
          onChange={(e) => handleChange(e.target.value)}
        />
      );

    case 'textarea':
      return (
        <textarea
          className="form-control"
          rows={3}
          placeholder={question.placeholder || ''}
          value={value || ''}
          maxLength={v.max_length ?? undefined}
          onChange={(e) => handleChange(e.target.value)}
        />
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
