import React, { useMemo, useCallback, useRef, useState } from 'react';

const formatFileSize = (bytes) => {
  if (!bytes && bytes !== 0) return '';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

function TableFileCell({ column, value, onChange }) {
  const inputRef = useRef(null);
  const [dragActive, setDragActive] = useState(false);

  const isFile = value instanceof File;
  const uploaded = !isFile && value && typeof value === 'object' && (value.filename || value.path)
    ? value
    : null;

  const acceptAttr = column.accept || '.pdf,.jpg,.jpeg,.png,.docx,.doc';

  const handlePick = useCallback((file) => {
    onChange(file || null);
  }, [onChange]);

  const handleInputChange = (e) => {
    const picked = e.target.files && e.target.files[0] ? e.target.files[0] : null;
    handlePick(picked);
    e.target.value = '';
  };

  const handleDrag = useCallback((e) => {
    e.preventDefault();
    e.stopPropagation();
    if (e.type === 'dragenter' || e.type === 'dragover') {
      setDragActive(true);
    } else if (e.type === 'dragleave') {
      setDragActive(false);
    }
  }, []);

  const handleDrop = useCallback((e) => {
    e.preventDefault();
    e.stopPropagation();
    setDragActive(false);
    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      handlePick(e.dataTransfer.files[0]);
    }
  }, [handlePick]);

  return (
    <div className="table-field-file">
      <input
        ref={inputRef}
        type="file"
        accept={acceptAttr}
        onChange={handleInputChange}
        style={{ display: 'none' }}
      />

      {isFile && (
        <div className="file-upload-preview">
          <div className="file-upload-preview-icon">{'\u{1F4C4}'}</div>
          <div className="file-upload-preview-info">
            <span className="file-upload-preview-name">{value.name}</span>
            <span className="file-upload-preview-size">{formatFileSize(value.size)}</span>
          </div>
          <button
            type="button"
            className="file-upload-remove"
            onClick={() => handlePick(null)}
            title="Remove file"
          >
            {'✕'}
          </button>
        </div>
      )}

      {!isFile && uploaded && (
        <>
          <div className="file-upload-preview uploaded">
            <div className="file-upload-preview-icon">{'\u{1F4C4}'}</div>
            <div className="file-upload-preview-info">
              {uploaded.url ? (
                <a
                  href={uploaded.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="kyc-file-link"
                >
                  {uploaded.filename || 'Uploaded file'}
                </a>
              ) : (
                <span className="file-upload-preview-name">{uploaded.filename || 'Uploaded file'}</span>
              )}
              {uploaded.size != null && (
                <span className="file-upload-preview-size">{formatFileSize(uploaded.size)}</span>
              )}
            </div>
            <span className="file-upload-preview-badge">Uploaded</span>
          </div>
          <div style={{ marginTop: 6 }}>
            <button
              type="button"
              className="btn-link-custom"
              style={{ fontSize: '0.78rem', color: 'var(--color-accent)' }}
              onClick={() => inputRef.current?.click()}
            >
              Replace file
            </button>
          </div>
        </>
      )}

      {!isFile && !uploaded && (
        <label
          className={`file-upload-dropzone ${dragActive ? 'drag-active' : ''}`}
          onDragEnter={handleDrag}
          onDragLeave={handleDrag}
          onDragOver={handleDrag}
          onDrop={handleDrop}
          onClick={() => inputRef.current?.click()}
        >
          <div className="file-upload-dropzone-content">
            <div className="file-upload-dropzone-icon">{'\u{1F4CE}'}</div>
            <div className="file-upload-dropzone-text">
              <span>Drag &amp; drop a file, or <strong>click to browse</strong></span>
            </div>
          </div>
        </label>
      )}
    </div>
  );
}

function TableField({ question, value, onChange, cellErrors }) {
  const errorMap = cellErrors || {};
  const hasCellError = (rowIndex, columnKey) => Boolean(errorMap[`${rowIndex}_${columnKey}`]);
  const tableConfig = useMemo(() => {
    const opts = question.options || {};
    return {
      columns: opts.columns || [],
      minRows: opts.min_rows || 1,
      maxRows: opts.max_rows || 10,
      allowAddRows: opts.allow_add_rows !== false,
    };
  }, [question.options]);

  const rows = useMemo(() => {
    let parsed = value;
    if (typeof parsed === 'string') {
      try {
        parsed = JSON.parse(parsed);
      } catch {
        parsed = [];
      }
    }
    if (!Array.isArray(parsed)) parsed = [];

    // Ensure minimum rows
    while (parsed.length < tableConfig.minRows) {
      const emptyRow = {};
      tableConfig.columns.forEach((col) => {
        emptyRow[col.key] = '';
      });
      parsed.push(emptyRow);
    }

    return parsed;
  }, [value, tableConfig]);

  const updateRows = useCallback(
    (newRows) => {
      onChange(question.id, newRows);
    },
    [onChange, question.id]
  );

  const handleCellChange = useCallback(
    (rowIndex, columnKey, cellValue) => {
      const newRows = rows.map((row, i) => {
        if (i === rowIndex) {
          return { ...row, [columnKey]: cellValue };
        }
        return row;
      });
      updateRows(newRows);
    },
    [rows, updateRows]
  );

  const handleAddRow = useCallback(() => {
    if (rows.length >= tableConfig.maxRows) return;
    const emptyRow = {};
    tableConfig.columns.forEach((col) => {
      emptyRow[col.key] = '';
    });
    updateRows([...rows, emptyRow]);
  }, [rows, tableConfig, updateRows]);

  const handleRemoveRow = useCallback(
    (rowIndex) => {
      if (rows.length <= tableConfig.minRows) return;
      updateRows(rows.filter((_, i) => i !== rowIndex));
    },
    [rows, tableConfig, updateRows]
  );

  const renderCellInput = (column, rowValue, rowIndex) => {
    const cellValue = rowValue || '';

    switch (column.type) {
      case 'number':
        return (
          <input
            type="number"
            className="form-control form-control-sm table-field-input"
            placeholder={column.placeholder || ''}
            value={cellValue}
            onChange={(e) => handleCellChange(rowIndex, column.key, e.target.value)}
          />
        );

      case 'date':
        return (
          <input
            type="date"
            className="form-control form-control-sm table-field-input"
            value={cellValue}
            onChange={(e) => handleCellChange(rowIndex, column.key, e.target.value)}
          />
        );

      case 'select':
        return (
          <select
            className="form-select form-select-sm table-field-input"
            value={cellValue}
            onChange={(e) => handleCellChange(rowIndex, column.key, e.target.value)}
          >
            <option value="">-- Select --</option>
            {(column.options || []).map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </select>
        );

      case 'checkbox': {
        const selected = Array.isArray(rowValue) ? rowValue : [];
        const toggle = (optValue) => {
          const next = selected.includes(optValue)
            ? selected.filter((v) => v !== optValue)
            : [...selected, optValue];
          handleCellChange(rowIndex, column.key, next);
        };
        return (
          <div className="table-field-checkbox-group">
            {(column.options || []).map((opt) => (
              <label key={opt.value} className="table-field-checkbox-option">
                <input
                  type="checkbox"
                  className="form-check-input"
                  checked={selected.includes(opt.value)}
                  onChange={() => toggle(opt.value)}
                />
                <span>{opt.label}</span>
              </label>
            ))}
          </div>
        );
      }

      case 'file':
        return (
          <TableFileCell
            column={column}
            value={rowValue}
            onChange={(picked) => handleCellChange(rowIndex, column.key, picked)}
          />
        );

      default:
        return (
          <input
            type="text"
            className="form-control form-control-sm table-field-input"
            placeholder={column.placeholder || ''}
            value={cellValue}
            onChange={(e) => handleCellChange(rowIndex, column.key, e.target.value)}
          />
        );
    }
  };

  if (tableConfig.columns.length === 0) {
    return <div className="text-muted" style={{ fontSize: '0.85rem' }}>No columns configured for this table.</div>;
  }

  const canAddRow = tableConfig.allowAddRows && rows.length < tableConfig.maxRows;
  const canRemoveRow = tableConfig.allowAddRows && rows.length > tableConfig.minRows;

  return (
    <div className="table-field">
      <div className="table-field-form">
        {rows.map((row, rowIndex) => (
          <div key={rowIndex} className="table-field-card">
            {(rows.length > 1 || tableConfig.allowAddRows) && (
              <div className="table-field-card-header">
                <span>Entry {rowIndex + 1}</span>
                {canRemoveRow && (
                  <button
                    type="button"
                    className="table-field-remove-btn"
                    onClick={() => handleRemoveRow(rowIndex)}
                    title="Remove entry"
                  >
                    {'\u2715'}
                  </button>
                )}
              </div>
            )}
            <div className="table-field-card-grid">
              {tableConfig.columns.map((col) => {
                const wide = col.type === 'checkbox' || col.type === 'file';
                const invalid = hasCellError(rowIndex, col.key);
                return (
                  <div
                    key={col.key}
                    className={`table-field-card-field${wide ? ' full-width' : ''}${invalid ? ' has-error' : ''}`}
                  >
                    <label className="table-field-card-label">
                      {col.label}
                      {col.required && <span className="required">*</span>}
                    </label>
                    {renderCellInput(col, row[col.key], rowIndex)}
                    {invalid && (
                      <div className="table-field-cell-error">This field is required.</div>
                    )}
                  </div>
                );
              })}
            </div>
          </div>
        ))}
      </div>

      {canAddRow && (
        <button
          type="button"
          className="table-field-add-btn"
          onClick={handleAddRow}
        >
          + Add Entry
        </button>
      )}

      {rows.length >= tableConfig.maxRows && (
        <div style={{ fontSize: '0.78rem', color: 'var(--color-text-muted)', marginTop: 4 }}>
          Maximum of {tableConfig.maxRows} entries reached.
        </div>
      )}
    </div>
  );
}

export default TableField;
