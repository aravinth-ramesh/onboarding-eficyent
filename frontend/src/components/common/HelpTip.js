import React, { useState, useRef, useEffect, useId } from 'react';

/**
 * Accessible info popover. Renders a small "i" trigger next to a label and
 * reveals richer help content on hover (desktop), focus (keyboard) or click
 * (touch). Closes on Escape or an outside click.
 *
 * Renders nothing when there is no content, so callers can pass it
 * unconditionally.
 */
function HelpTip({ content, label }) {
  const [open, setOpen] = useState(false);
  const wrapRef = useRef(null);
  const id = useId();

  useEffect(() => {
    if (!open) return undefined;
    const onDocClick = (e) => {
      if (wrapRef.current && !wrapRef.current.contains(e.target)) setOpen(false);
    };
    const onKey = (e) => {
      if (e.key === 'Escape') setOpen(false);
    };
    document.addEventListener('mousedown', onDocClick);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onDocClick);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  if (content === null || content === undefined || content === '') return null;

  return (
    <span
      className="help-tip"
      ref={wrapRef}
      onMouseEnter={() => setOpen(true)}
      onMouseLeave={() => setOpen(false)}
    >
      <button
        type="button"
        className="help-tip-btn"
        aria-label={label || 'More information'}
        aria-expanded={open}
        aria-describedby={open ? id : undefined}
        onClick={() => setOpen((o) => !o)}
        onFocus={() => setOpen(true)}
        onBlur={() => setOpen(false)}
      >
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="10" />
          <line x1="12" y1="16" x2="12" y2="12" />
          <line x1="12" y1="8" x2="12.01" y2="8" />
        </svg>
      </button>
      {open && (
        <span className="help-tip-pop" role="tooltip" id={id}>
          {content}
        </span>
      )}
    </span>
  );
}

export default HelpTip;
