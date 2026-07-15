import React, { useRef, useState, useCallback, useMemo, useEffect } from 'react';
import FilePreviewCard, { looksLikeImage } from './FilePreviewCard';
import { MAX_FILE_SIZE_MB, partitionBySize, oversizeMessage } from '../../utils/files';

/**
 * File upload field with drag-and-drop UI (matching KYC module styling).
 * Supports single or multiple file selection.
 * Stores actual File objects and notifies parent via onChange.
 */
function FileUploadField({ question, value, onChange, existingFiles }) {
  const inputRef = useRef(null);
  const [dragActive, setDragActive] = useState(false);
  const [sizeError, setSizeError] = useState(null);

  const isMultiple = question.options?.multiple !== false;
  const selectedFiles = useMemo(
    () => (Array.isArray(value) ? value : []),
    [value]
  );

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
    // Enforce the server's per-file limit up front for instant feedback.
    const { accepted, rejected } = partitionBySize(fileList);
    setSizeError(rejected.length > 0 ? oversizeMessage(rejected) : null);
    if (accepted.length === 0) return;

    if (!isMultiple) {
      onChange(question.id, accepted.slice(0, 1));
    } else {
      onChange(question.id, [...selectedFiles, ...accepted]);
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
              validationStatus={file.reviewed ? 'reviewed' : file.validation_status}
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
              onRemove={() => handleRemoveSelected(index)}
            />
          ))}
        </div>
      )}

      {sizeError && (
        <div className="alert-corporate danger" style={{ marginBottom: 10 }}>
          {sizeError}
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
              PDF, JPG, PNG, DOCX (max {MAX_FILE_SIZE_MB}MB each)
            </div>
          </div>
        </label>
      )}
    </div>
  );
}

export default FileUploadField;
