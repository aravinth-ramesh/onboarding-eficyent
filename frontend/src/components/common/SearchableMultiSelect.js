import React, { useEffect, useMemo, useRef, useState } from 'react';

/**
 * A searchable, checkbox-based dropdown for choosing several options.
 *
 * The country fields — inbound countries, outbound destinations, tax residency,
 * jurisdictions — rendered every one of ~190 options as a checkbox stacked in
 * the page, so the form became a wall of countries and picking a few meant
 * scrolling past all of them (retest item 15). They are a dropdown now, with a
 * filter box, and the selection summarised on the closed control.
 *
 * The panel is rendered in normal flow beneath the control rather than absolutely
 * positioned, so a parent with its own scrolling cannot clip it — which is what
 * cut the single-select lists off mid-way (items 8 and 16).
 */
function SearchableMultiSelect({
  options = [],
  values = [],
  onChange,
  placeholder = 'Select…',
  ariaLabel,
  maxHeight = 260,
}) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const rootRef = useRef(null);

  const selected = useMemo(
    () => options.filter((o) => values.includes(o.value)),
    [options, values],
  );

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return options;
    return options.filter((o) => String(o.label ?? o.value).toLowerCase().includes(q));
  }, [options, query]);

  useEffect(() => {
    if (!open) return undefined;
    const onDocClick = (e) => {
      if (rootRef.current && !rootRef.current.contains(e.target)) setOpen(false);
    };
    document.addEventListener('mousedown', onDocClick);
    return () => document.removeEventListener('mousedown', onDocClick);
  }, [open]);

  const toggle = (optValue) => {
    onChange(
      values.includes(optValue)
        ? values.filter((v) => v !== optValue)
        : [...values, optValue],
    );
  };

  const summary =
    selected.length === 0
      ? placeholder
      : selected.length <= 3
        ? selected.map((o) => o.label ?? o.value).join(', ')
        : `${selected.length} selected`;

  return (
    <div className="searchable-multi-select" ref={rootRef}>
      <button
        type="button"
        className="form-select text-start"
        aria-label={ariaLabel}
        aria-expanded={open}
        onClick={() => setOpen((o) => !o)}
      >
        <span className={selected.length ? '' : 'text-muted'}>{summary}</span>
      </button>

      {open && (
        <div className="searchable-multi-select-panel border rounded mt-1">
          <input
            type="text"
            className="form-control form-control-sm"
            placeholder="Search…"
            value={query}
            autoFocus
            onChange={(e) => setQuery(e.target.value)}
          />

          {selected.length > 0 && (
            <button
              type="button"
              className="btn btn-link btn-sm px-0"
              onClick={() => onChange([])}
            >
              Clear all ({selected.length})
            </button>
          )}

          <div style={{ maxHeight, overflowY: 'auto' }}>
            {filtered.length === 0 && (
              <div className="text-muted small py-2">No matches.</div>
            )}
            {filtered.map((option) => (
              <label key={option.value} className="d-flex align-items-center gap-2 py-1">
                <input
                  type="checkbox"
                  checked={values.includes(option.value)}
                  onChange={() => toggle(option.value)}
                />
                <span>{option.label ?? option.value}</span>
              </label>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

export default SearchableMultiSelect;
