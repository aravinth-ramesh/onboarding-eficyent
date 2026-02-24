import React, { useRef, useState, useCallback } from 'react';

/**
 * File upload field with drag-and-drop UI (matching KYC module styling).
 * Supports single or multiple file selection.
 * Stores actual File objects and notifies parent via onChange.
 */
function FileUploadField({ question, value, onChange, existingFiles }) {
  const inputRef = useRef(null);
  const [dragActive, setDragActive] = useState(false);

  // value = array of File objects selected by user
  const selectedFiles = Array.isArray(value) ? value : [];

  const handleFiles = useCallback((fileList) => {
    const newFiles = Array.from(fileList);
    if (question.options?.multiple === false) {
      // Single file mode: replace
      onChange(question.id, newFiles.slice(0, 1));
    } else {
      // Multi file mode: append
      onChange(question.id, [...selectedFiles, ...newFiles]);
    }
  }, [question.id, question.options, selectedFiles, onChange]);

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
    // Reset input so same file can be re-selected
    e.target.value = '';
  };

  const handleRemoveFile = (index) => {
    const updated = selectedFiles.filter((_, i) => i !== index);
    onChange(question.id, updated);
  };

  const handleRemoveExisting = () => {
    // Clear existing files marker — parent will handle
    onChange(question.id, []);
  };

  const formatSize = (bytes) => {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  };

  const hasNewFiles = selectedFiles.length > 0;
  const hasExistingFiles = existingFiles && existingFiles.length > 0 && !hasNewFiles;

  return (
    <div>
      {/* Show previously uploaded files from server */}
      {hasExistingFiles && (
        <div style={{ marginBottom: 10 }}>
          {existingFiles.map((file) => (
            <div key={file.id} className="file-upload-preview uploaded">
              <div className="file-upload-preview-icon">&#128196;</div>
              <div className="file-upload-preview-info">
                <a
                  href={file.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="kyc-file-link"
                >
                  {file.original_filename}
                </a>
                <span className="file-upload-preview-size">{formatSize(file.file_size)}</span>
              </div>
              <span className="file-upload-preview-badge">Uploaded</span>
            </div>
          ))}
          <div style={{ marginTop: 6 }}>
            <button
              type="button"
              className="btn-link-custom"
              style={{ fontSize: '0.78rem', color: 'var(--color-accent)' }}
              onClick={() => inputRef.current?.click()}
            >
              Replace files
            </button>
          </div>
        </div>
      )}

      {/* Show newly selected files */}
      {hasNewFiles && (
        <div style={{ marginBottom: 10 }}>
          {selectedFiles.map((file, index) => (
            <div key={`${file.name}-${index}`} className="file-upload-preview">
              <div className="file-upload-preview-icon">&#128196;</div>
              <div className="file-upload-preview-info">
                <span className="file-upload-preview-name">{file.name}</span>
                <span className="file-upload-preview-size">{formatSize(file.size)}</span>
              </div>
              <button
                type="button"
                className="file-upload-remove"
                onClick={() => handleRemoveFile(index)}
                title="Remove file"
              >
                &#10005;
              </button>
            </div>
          ))}
        </div>
      )}

      {/* Drop zone (always visible unless existing files shown without intent to replace) */}
      {!hasExistingFiles && (
        <label
          className={`file-upload-dropzone ${dragActive ? 'drag-active' : ''} ${hasNewFiles ? 'has-files' : ''}`}
          onDragEnter={handleDrag}
          onDragLeave={handleDrag}
          onDragOver={handleDrag}
          onDrop={handleDrop}
        >
          <input
            ref={inputRef}
            type="file"
            multiple={question.options?.multiple !== false}
            accept={question.options?.accept || '.pdf,.jpg,.jpeg,.png,.docx,.doc,.xlsx,.xls,.csv'}
            onChange={handleInputChange}
            style={{ display: 'none' }}
          />
          <div className="file-upload-dropzone-content">
            <div className="file-upload-dropzone-icon">&#128206;</div>
            <div className="file-upload-dropzone-text">
              <span>Drag & drop files here, or <strong>click to browse</strong></span>
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
