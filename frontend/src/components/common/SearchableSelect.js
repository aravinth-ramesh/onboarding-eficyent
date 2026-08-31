import React, { useState, useMemo, useRef, useEffect, useLayoutEffect } from 'react';

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
  const toggleRef = useRef(null);
  // The menu is positioned against the viewport rather than its parent: it used
  // to be absolutely positioned, so any ancestor with overflow:hidden -- the
  // step card, the registration panel -- cut the option list off part-way
  // (retest items 8 and 16). Measured on open, and kept in step while the page
  // scrolls or resizes.
  const [menuStyle, setMenuStyle] = useState(null);

  useLayoutEffect(() => {
    if (!open) return undefined;

    const place = () => {
      const el = toggleRef.current;
      if (!el) return;
      const r = el.getBoundingClientRect();
      const spaceBelow = window.innerHeight - r.bottom;
      const spaceAbove = r.top;
      // Drop upward when the space below cannot hold a usable list.
      const dropUp = spaceBelow < 220 && spaceAbove > spaceBelow;
      const available = Math.max(160, (dropUp ? spaceAbove : spaceBelow) - 16);

      setMenuStyle({
        position: 'fixed',
        left: r.left,
        width: r.width,
        maxHeight: available,
        ...(dropUp ? { bottom: window.innerHeight - r.top + 4 } : { top: r.bottom + 4 }),
      });
    };

    place();
    window.addEventListener('scroll', place, true);
    window.addEventListener('resize', place);

    return () => {
      window.removeEventListener('scroll', place, true);
      window.removeEventListener('resize', place);
    };
  }, [open]);

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
        ref={toggleRef}
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
        <div className="searchable-select-menu" style={menuStyle || undefined}>
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
