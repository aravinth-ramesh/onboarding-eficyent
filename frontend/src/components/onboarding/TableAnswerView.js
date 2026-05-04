import React from 'react';

/**
 * Read-only card layout for a table-type answer.
 * Each row becomes its own "entry" card; columns are label/value pairs in a
 * responsive 2-up grid. File and checkbox columns span the full width because
 * their content is taller / multi-line. Used by Review and SubmittedAnswers.
 */
function TableAnswerView({ question, value }) {
  let rows = value;
  if (typeof rows === 'string') {
    try { rows = JSON.parse(rows); } catch { rows = []; }
  }
  if (!Array.isArray(rows) || rows.length === 0) {
    return <span className="table-answer-empty">{'—'}</span>;
  }

  const columns = (question.options && question.options.columns) || [];
  if (columns.length === 0) {
    return <span className="table-answer-empty">{`${rows.length} entry${rows.length === 1 ? '' : 's'}`}</span>;
  }

  const renderCell = (col, cellVal) => {
    if (col.type === 'file') {
      if (cellVal && typeof cellVal === 'object' && (cellVal.filename || cellVal.path)) {
        const name = cellVal.filename || 'Uploaded file';
        return cellVal.url ? (
          <a
            href={cellVal.url}
            target="_blank"
            rel="noopener noreferrer"
            className="kyc-file-link"
          >
            {'\u{1F4CE}'} {name}
          </a>
        ) : (
          <span>{'\u{1F4CE}'} {name}</span>
        );
      }
      return <span className="table-answer-empty">{'—'}</span>;
    }

    if (col.type === 'checkbox') {
      const arr = Array.isArray(cellVal) ? cellVal : [];
      if (arr.length === 0) return <span className="table-answer-empty">{'—'}</span>;
      const labels = arr.map((v) => {
        const opt = (col.options || []).find((o) => o.value === v);
        return opt ? opt.label : v;
      });
      return (
        <div className="table-answer-tags">
          {labels.map((label, i) => (
            <span key={i} className="table-answer-tag">{label}</span>
          ))}
        </div>
      );
    }

    if (col.type === 'select' && col.options) {
      const opt = col.options.find((o) => o.value === cellVal);
      const text = opt ? opt.label : cellVal;
      if (!text) return <span className="table-answer-empty">{'—'}</span>;
      return <span>{text}</span>;
    }

    if (cellVal === null || cellVal === undefined || cellVal === '') {
      return <span className="table-answer-empty">{'—'}</span>;
    }
    return <span>{cellVal}</span>;
  };

  const showHeader = rows.length > 1;

  return (
    <div className="table-answer-cards">
      {rows.map((row, i) => (
        <div key={i} className="table-answer-card">
          {showHeader && (
            <div className="table-answer-card-header">
              <span className="table-answer-card-index">Entry {i + 1}</span>
            </div>
          )}
          <dl className="table-answer-card-grid">
            {columns.map((col) => {
              const wide = col.type === 'file' || col.type === 'checkbox';
              return (
                <div
                  key={col.key}
                  className={`table-answer-field${wide ? ' full-width' : ''}`}
                >
                  <dt className="table-answer-field-label">{col.label}</dt>
                  <dd className="table-answer-field-value">{renderCell(col, row?.[col.key])}</dd>
                </div>
              );
            })}
          </dl>
        </div>
      ))}
    </div>
  );
}

export default TableAnswerView;
