import React from 'react';
import { Form } from 'react-bootstrap';

function QuestionField({ question, value, onChange }) {
  const handleChange = (newValue) => {
    onChange(question.id, newValue);
  };

  switch (question.type) {
    case 'text':
      return (
        <Form.Control
          type="text"
          placeholder={question.placeholder || ''}
          value={value || ''}
          onChange={(e) => handleChange(e.target.value)}
        />
      );

    case 'textarea':
      return (
        <Form.Control
          as="textarea"
          rows={3}
          placeholder={question.placeholder || ''}
          value={value || ''}
          onChange={(e) => handleChange(e.target.value)}
        />
      );

    case 'number':
      return (
        <Form.Control
          type="number"
          placeholder={question.placeholder || ''}
          value={value || ''}
          onChange={(e) => handleChange(e.target.value)}
        />
      );

    case 'date':
      return (
        <Form.Control
          type="date"
          value={value || ''}
          onChange={(e) => handleChange(e.target.value)}
        />
      );

    case 'radio':
      return (
        <div>
          {(question.options || []).map((option) => (
            <Form.Check
              key={option.value}
              type="radio"
              id={`q${question.id}-${option.value}`}
              label={option.label}
              name={`question-${question.id}`}
              checked={value === option.value}
              onChange={() => handleChange(option.value)}
            />
          ))}
        </div>
      );

    case 'select':
      return (
        <Form.Select
          value={value || ''}
          onChange={(e) => handleChange(e.target.value)}
        >
          <option value="">-- Select --</option>
          {(question.options || []).map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </Form.Select>
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
        <div>
          {(question.options || []).map((option) => (
            <Form.Check
              key={option.value}
              type="checkbox"
              id={`q${question.id}-${option.value}`}
              label={option.label}
              checked={selectedValues.includes(option.value)}
              onChange={() => toggleValue(option.value)}
            />
          ))}
        </div>
      );
    }

    case 'file':
      return (
        <Form.Control
          type="file"
          onChange={(e) => handleChange(e.target.files[0]?.name || '')}
        />
      );

    default:
      return (
        <Form.Control
          type="text"
          value={value || ''}
          onChange={(e) => handleChange(e.target.value)}
        />
      );
  }
}

export default QuestionField;
