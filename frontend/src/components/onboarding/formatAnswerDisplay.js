import React from 'react';
import TableAnswerView from './TableAnswerView';
import { formatMcc, formatAddress, formatUbo } from '../../utils/answerFormat';

/**
 * Render a question's answer for the read-only views (ReviewStep and
 * SubmittedAnswersView). Handles every question type: file links, option
 * labels, MCC/address/UBO formatting, and table sub-rendering.
 */
export default function formatAnswerDisplay(question, value) {
  if (!value) return '—';

  // File-type questions: show file links from question.files
  if (question.type === 'file' && question.files && question.files.length > 0) {
    return (
      <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
        {question.files.map((file) => (
          <a
            key={file.id}
            href={file.url}
            target="_blank"
            rel="noopener noreferrer"
            className="kyc-file-link"
            style={{ fontSize: '0.85rem' }}
          >
            {'\u{1F4CE}'} {file.original_filename}
          </a>
        ))}
      </div>
    );
  }

  // File marker from local selection (not yet uploaded)
  if (question.type === 'file') {
    return '—';
  }

  if (question.type === 'multi_select') {
    try {
      const arr = typeof value === 'string' ? JSON.parse(value) : value;
      const labels = arr.map((v) => {
        const opt = (question.options || []).find((o) => o.value === v);
        return opt ? opt.label : v;
      });
      return labels.join(', ');
    } catch {
      return value;
    }
  }

  if (['radio', 'select'].includes(question.type)) {
    const opt = (question.options || []).find((o) => o.value === value);
    return opt ? opt.label : value;
  }

  if (question.type === 'mcc') {
    return formatMcc(value);
  }

  if (question.type === 'address') {
    return formatAddress(value);
  }

  if (question.type === 'ubo') {
    return formatUbo(value);
  }

  if (question.type === 'table') {
    return <TableAnswerView question={question} value={value} />;
  }

  return value;
}
