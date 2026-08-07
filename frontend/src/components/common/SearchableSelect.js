import React, { useEffect, useMemo, useRef, useState } from 'react';

/**
 * A single-select that can be typed into.
 *
 * The portal previously used plain `<select>` elements for country and
 * industry lists of 60–215 entries. A native select only type-ahead-matches
 * from the START of the option text, so with options like "0742 – Veterinary
 * Services" typing "veterinary" matched nothing (EOP-13, EOP-14).
 *
 * Options are `{ value, label }`; an optional `keywords` string is also
 * searched, which lets the industry list match on both code and name.
 */
function SearchableSelect({
  options = [],
  value,
  onChange,
  placeholder = '-- Select --',
  disabled = false,
  ariaLabel,
  id,
}) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [highlighted, setHighlighted] = useState(0);
  const rootRef = useRef(null);
  const inputRef = useRef(null);

  const selected = useMemo(
    () => options.find((o) => String(o.value) === String(value)) || null,
    [options, value]
  );

  const matches = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return options;

    return options.filter((o) =>
      `${o.label} ${o.keywords || ''} ${o.value}`.toLowerCase().includes(q)
    );
  }, [options, query]);

  // Close when focus or a click leaves the control.
  useEffect(() => {
    if (!open) return undefined;
    const onDocClick = (e) => {
      if (rootRef.current && !rootRef.current.contains(e.target)) {
        setOpen(false);
        setQuery('');
      }
    };
    document.addEventListener('mousedown', onDocClick);
    return () => document.removeEventListener('mousedown', onDocClick);
  }, [open]);

  useEffect(() => {
    if (open) inputRef.current?.focus();
  }, [open]);

  useEffect(() => { setHighlighted(0); }, [query]);

  const commit = (option) => {
    onChange(option ? option.value : '');
    setOpen(false);
    setQuery('');
  };

  const onKeyDown = (e) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setHighlighted((i) => Math.min(i + 1, matches.length - 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setHighlighted((i) => Math.max(i - 1, 0));
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (matches[highlighted]) commit(matches[highlighted]);
    } else if (e.key === 'Escape') {
      setOpen(false);
      setQuery('');
    }
  };

  return (
    <div className="searchable-select" ref={rootRef}>
      <button
        type="button"
        id={id}
        className="form-control searchable-select-toggle"
        onClick={() => !disabled && setOpen((v) => !v)}
        disabled={disabled}
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-label={ariaLabel}
      >
        <span className={selected ? '' : 'searchable-select-placeholder'}>
          {selected ? selected.label : placeholder}
        </span>
        <span className="searchable-select-caret" aria-hidden="true">▾</span>
      </button>

      {open && (
        <div className="searchable-select-menu">
          <input
            ref={inputRef}
            type="text"
            className="form-control searchable-select-search"
            placeholder="Type to search…"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onKeyDown={onKeyDown}
            aria-label="Search options"
          />
          <ul className="searchable-select-options" role="listbox">
            {matches.length === 0 && (
              <li className="searchable-select-empty">No matches</li>
            )}
            {matches.map((option, i) => (
              <li
                key={option.value}
                role="option"
                aria-selected={String(option.value) === String(value)}
                className={`searchable-select-option${i === highlighted ? ' is-highlighted' : ''}${
                  String(option.value) === String(value) ? ' is-selected' : ''
                }`}
                onMouseEnter={() => setHighlighted(i)}
                onMouseDown={(e) => { e.preventDefault(); commit(option); }}
              >
                {option.label}
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}

export default SearchableSelect;
