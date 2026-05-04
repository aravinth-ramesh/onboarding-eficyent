import React, { useMemo, useCallback } from 'react';

function TableField({ question, value, onChange }) {
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

      case 'file': {
        const file = rowValue instanceof File ? rowValue : null;
        return (
          <div className="table-field-file">
            <input
              type="file"
              className="form-control form-control-sm table-field-input"
              accept={column.accept || '.pdf,.jpg,.jpeg,.png,.docx,.doc'}
              onChange={(e) => {
                const picked = e.target.files && e.target.files[0] ? e.target.files[0] : null;
                handleCellChange(rowIndex, column.key, picked);
              }}
            />
            {file && (
              <div className="table-field-file-name" title={file.name}>{file.name}</div>
            )}
          </div>
        );
      }

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
      {/* Desktop table view */}
      <div className="table-field-desktop">
        <div className="table-responsive">
          <table className="table-field-table">
            <thead>
              <tr>
                <th className="table-field-row-num">#</th>
                {tableConfig.columns.map((col) => (
                  <th key={col.key}>
                    {col.label}
                    {col.required && <span className="required">*</span>}
                  </th>
                ))}
                {canRemoveRow && <th className="table-field-action-col"></th>}
              </tr>
            </thead>
            <tbody>
              {rows.map((row, rowIndex) => (
                <tr key={rowIndex}>
                  <td className="table-field-row-num">{rowIndex + 1}</td>
                  {tableConfig.columns.map((col) => (
                    <td key={col.key}>
                      {renderCellInput(col, row[col.key], rowIndex)}
                    </td>
                  ))}
                  {canRemoveRow && (
                    <td className="table-field-action-col">
                      <button
                        type="button"
                        className="table-field-remove-btn"
                        onClick={() => handleRemoveRow(rowIndex)}
                        title="Remove row"
                      >
                        {'\u2715'}
                      </button>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Mobile card view */}
      <div className="table-field-mobile">
        {rows.map((row, rowIndex) => (
          <div key={rowIndex} className="table-field-card">
            <div className="table-field-card-header">
              <span>Row {rowIndex + 1}</span>
              {canRemoveRow && (
                <button
                  type="button"
                  className="table-field-remove-btn"
                  onClick={() => handleRemoveRow(rowIndex)}
                  title="Remove row"
                >
                  {'\u2715'}
                </button>
              )}
            </div>
            {tableConfig.columns.map((col) => (
              <div key={col.key} className="table-field-card-field">
                <label className="table-field-card-label">
                  {col.label}
                  {col.required && <span className="required">*</span>}
                </label>
                {renderCellInput(col, row[col.key], rowIndex)}
              </div>
            ))}
          </div>
        ))}
      </div>

      {canAddRow && (
        <button
          type="button"
          className="table-field-add-btn"
          onClick={handleAddRow}
        >
          + Add Row
        </button>
      )}

      {rows.length >= tableConfig.maxRows && (
        <div style={{ fontSize: '0.78rem', color: 'var(--color-text-muted)', marginTop: 4 }}>
          Maximum of {tableConfig.maxRows} rows reached.
        </div>
      )}
    </div>
  );
}

export default TableField;
