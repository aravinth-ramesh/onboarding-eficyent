import React from 'react';

export const formatFileSize = (bytes) => {
  if (bytes === undefined || bytes === null || bytes === '') return '';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

export const looksLikePdf = (mime, name = '') =>
  mime === 'application/pdf' || /\.pdf$/i.test(name || '');

export const looksLikeImage = (mime) => Boolean(mime) && mime.startsWith('image/');

/**
 * Compact file preview card used by FileUploadField (top-level file
 * questions), TableFileCell (table-cell file uploads), and the notification
 * response form. Renders a thumbnail (image or icon), a coloured pill
 * badge ("Selected" / "Uploaded"), filename, size, and optional
 * Replace / Remove actions.
 */
// AI document-validation verdicts worth surfacing to the user. 'passed' and
// 'skipped' render nothing extra; the rest get an amber "review" pill.
const VALIDATION_LABELS = {
  passed: { label: 'Verified', tone: 'ok' },
  reviewed: { label: 'Approved by reviewer', tone: 'ok' },
  needs_review: { label: 'Pending review', tone: 'warn' },
  type_mismatch: { label: 'Accepted with justification', tone: 'warn' },
  expired: { label: 'Expired — justified', tone: 'warn' },
  stale: { label: 'Outdated — justified', tone: 'warn' },
};

function FilePreviewCard({
  name,
  size,
  mime,
  previewUrl,
  downloadUrl,
  kind,
  validationStatus,
  onReplace,
  onRemove,
}) {
  const isImage = looksLikeImage(mime) && previewUrl;
  const isPdf = looksLikePdf(mime, name);
  const validation = VALIDATION_LABELS[validationStatus];

  return (
    <div className={`file-preview-card ${kind}`}>
      <div className="file-preview-thumb">
        {isImage ? (
          <img src={previewUrl} alt={name} />
        ) : (
          <div className="file-preview-icon">{isPdf ? '\u{1F4C4}' : '\u{1F4CE}'}</div>
        )}
      </div>
      <div className="file-preview-info">
        <span className={`file-preview-badge ${kind}`}>
          {'✓'} {kind === 'uploaded' ? 'Uploaded' : 'Selected'}
        </span>
        {validation && (
          <span className={`file-preview-badge validation-${validation.tone}`}>
            {validation.label}
          </span>
        )}
        {downloadUrl ? (
          <a
            href={downloadUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="file-preview-name"
            title={name}
          >
            {name}
          </a>
        ) : (
          <span className="file-preview-name" title={name}>{name}</span>
        )}
        {size != null && size !== '' && (
          <span className="file-preview-size">{formatFileSize(size)}</span>
        )}
      </div>
      <div className="file-preview-actions">
        {/* {onReplace && (
          <button type="button" className="kyc-btn-link" onClick={onReplace}>
            Replace
          </button>
        )} */}
        {onRemove && (
          <button type="button" className="kyc-btn-link danger" onClick={onRemove}>
            Remove
          </button>
        )}
      </div>
    </div>
  );
}

export default FilePreviewCard;
