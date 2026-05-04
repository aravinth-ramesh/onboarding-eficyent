import React, { useRef, useState, useCallback, useMemo, useEffect } from 'react';

const formatSize = (bytes) => {
  if (bytes === undefined || bytes === null) return '';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const looksLikePdf = (mime, name = '') => mime === 'application/pdf' || /\.pdf$/i.test(name);
const looksLikeImage = (mime) => mime && mime.startsWith('image/');

function FilePreviewCard({
  name,
  size,
  mime,
  previewUrl,
  downloadUrl,
  kind,
  onReplace,
  onRemove,
}) {
  const isImage = looksLikeImage(mime) && previewUrl;
  const isPdf = looksLikePdf(mime, name);

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
          <span className="file-preview-size">{formatSize(size)}</span>
        )}
      </div>
      <div className="file-preview-actions">
        {onReplace && (
          <button type="button" className="kyc-btn-link" onClick={onReplace}>
            Replace
          </button>
        )}
        {onRemove && (
          <button type="button" className="kyc-btn-link danger" onClick={onRemove}>
            Remove
          </button>
        )}
      </div>
    </div>
  );
}

/**
 * File upload field with drag-and-drop UI (matching KYC module styling).
 * Supports single or multiple file selection.
 * Stores actual File objects and notifies parent via onChange.
 */
function FileUploadField({ question, value, onChange, existingFiles }) {
  const inputRef = useRef(null);
  const [dragActive, setDragActive] = useState(false);

  const isMultiple = question.options?.multiple !== false;
  const selectedFiles = Array.isArray(value) ? value : [];

  // Generate (and revoke) object URLs for image previews of newly selected files.
  const selectedPreviews = useMemo(() => {
    return selectedFiles.map((f) =>
      f && f.type && f.type.startsWith('image/') ? URL.createObjectURL(f) : null
    );
  }, [selectedFiles]);

  useEffect(() => {
    return () => {
      selectedPreviews.forEach((u) => u && URL.revokeObjectURL(u));
    };
  }, [selectedPreviews]);

  const handleFiles = useCallback((fileList) => {
    const newFiles = Array.from(fileList);
    if (!isMultiple) {
      onChange(question.id, newFiles.slice(0, 1));
    } else {
      onChange(question.id, [...selectedFiles, ...newFiles]);
    }
  }, [question.id, isMultiple, selectedFiles, onChange]);

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
      handleFiles(e.dataTransfer.files);
    }
  }, [handleFiles]);

  const handleInputChange = (e) => {
    if (e.target.files && e.target.files.length > 0) {
      handleFiles(e.target.files);
    }
    e.target.value = '';
  };

  const handleRemoveSelected = (index) => {
    const updated = selectedFiles.filter((_, i) => i !== index);
    onChange(question.id, updated);
  };

  const hasNewFiles = selectedFiles.length > 0;
  const hasExistingFiles = existingFiles && existingFiles.length > 0 && !hasNewFiles;
  // Show the dropzone whenever the user can still add another file:
  // - always when nothing is selected and no existing file is shown
  // - in multi-file mode, also when files already exist (to add more)
  const showDropzone = !hasExistingFiles && (!hasNewFiles || isMultiple);

  return (
    <div>
      <input
        ref={inputRef}
        type="file"
        multiple={isMultiple}
        accept={question.options?.accept || '.pdf,.jpg,.jpeg,.png,.docx,.doc,.xlsx,.xls,.csv'}
        onChange={handleInputChange}
        style={{ display: 'none' }}
      />

      {hasExistingFiles && (
        <div className="file-preview-list">
          {existingFiles.map((file) => (
            <FilePreviewCard
              key={file.id}
              kind="uploaded"
              name={file.original_filename}
              size={file.file_size}
              mime={file.mime_type}
              previewUrl={looksLikeImage(file.mime_type) ? file.url : null}
              downloadUrl={file.url}
              onReplace={() => inputRef.current?.click()}
            />
          ))}
        </div>
      )}

      {hasNewFiles && (
        <div className="file-preview-list">
          {selectedFiles.map((file, index) => (
            <FilePreviewCard
              key={`${file.name}-${index}`}
              kind="selected"
              name={file.name}
              size={file.size}
              mime={file.type}
              previewUrl={selectedPreviews[index]}
              onReplace={!isMultiple ? () => inputRef.current?.click() : undefined}
              onRemove={() => handleRemoveSelected(index)}
            />
          ))}
        </div>
      )}

      {showDropzone && (
        <label
          className={`file-upload-dropzone ${dragActive ? 'drag-active' : ''} ${hasNewFiles ? 'has-files' : ''}`}
          onDragEnter={handleDrag}
          onDragLeave={handleDrag}
          onDragOver={handleDrag}
          onDrop={handleDrop}
          onClick={() => inputRef.current?.click()}
        >
          <div className="file-upload-dropzone-content">
            <div className="file-upload-dropzone-icon">{'\u{1F4CE}'}</div>
            <div className="file-upload-dropzone-text">
              <span>
                {hasNewFiles && isMultiple
                  ? <>Add another file — drag &amp; drop or <strong>click to browse</strong></>
                  : <>Drag &amp; drop files here, or <strong>click to browse</strong></>}
              </span>
            </div>
            <div className="file-upload-dropzone-hint">
              PDF, JPG, PNG, DOCX (max 5MB each)
            </div>
          </div>
        </label>
      )}
    </div>
  );
}

export default FileUploadField;
