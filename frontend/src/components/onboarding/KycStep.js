import React, { useState, useRef, useMemo, useEffect } from 'react';
import { useDispatch } from 'react-redux';
import { completeOnboardingStep, fetchOnboardingStatus, setKycDocStatus } from '../../store/slices/onboardingSlice';
import { REQUIRED_KYC_DOCUMENTS } from '../../config/onboardingConfig';
import { MAX_FILE_SIZE_MB, formatFileSize, partitionBySize, oversizeMessage } from '../../utils/files';

function KycUploadTile({ doc, file, onChange }) {
  const inputRef = useRef(null);
  const [dragActive, setDragActive] = useState(false);
  const [sizeError, setSizeError] = useState(null);

  const previewUrl = useMemo(() => {
    if (!file || !file.type || !file.type.startsWith('image/')) return null;
    return URL.createObjectURL(file);
  }, [file]);

  useEffect(() => {
    return () => {
      if (previewUrl) URL.revokeObjectURL(previewUrl);
    };
  }, [previewUrl]);

  const handlePick = (picked) => {
    if (picked) {
      const { rejected } = partitionBySize([picked]);
      if (rejected.length > 0) {
        setSizeError(oversizeMessage(rejected));
        return;
      }
    }
    setSizeError(null);
    onChange(picked || null);
  };

  const handleInputChange = (e) => {
    const picked = e.target.files && e.target.files[0] ? e.target.files[0] : null;
    handlePick(picked);
    e.target.value = '';
  };

  const handleDrag = (e) => {
    e.preventDefault();
    e.stopPropagation();
    if (e.type === 'dragenter' || e.type === 'dragover') {
      setDragActive(true);
    } else if (e.type === 'dragleave') {
      setDragActive(false);
    }
  };

  const handleDrop = (e) => {
    e.preventDefault();
    e.stopPropagation();
    setDragActive(false);
    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      handlePick(e.dataTransfer.files[0]);
    }
  };

  if (file) {
    const isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name);
    return (
      <div className="kyc-upload-item uploaded">
        <input
          ref={inputRef}
          type="file"
          accept=".pdf,.jpg,.jpeg,.png"
          onChange={handleInputChange}
          style={{ display: 'none' }}
        />
        <div className="kyc-selected">
          <div className="kyc-selected-thumb">
            {previewUrl ? (
              <img src={previewUrl} alt={file.name} />
            ) : (
              <div className="kyc-selected-icon">{isPdf ? '\u{1F4C4}' : '\u{1F4CE}'}</div>
            )}
          </div>
          <div className="kyc-selected-info">
            <div className="kyc-selected-badge">{'✓'} Selected</div>
            <div className="kyc-selected-doc">{doc.label}</div>
            <div className="kyc-selected-name" title={file.name}>{file.name}</div>
            <div className="kyc-selected-size">{formatFileSize(file.size)}</div>
          </div>
          <div className="kyc-selected-actions">
            <button
              type="button"
              className="kyc-btn-link danger"
              onClick={() => handlePick(null)}
            >
              Remove
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <label
      className={`kyc-upload-item${dragActive ? ' drag-active' : ''}`}
      onDragEnter={handleDrag}
      onDragOver={handleDrag}
      onDragLeave={handleDrag}
      onDrop={handleDrop}
    >
      <input
        type="file"
        accept=".pdf,.jpg,.jpeg,.png"
        onChange={handleInputChange}
        style={{ display: 'none' }}
      />
      <div className="kyc-upload-label">
        {doc.label} <span style={{ color: 'var(--color-danger)' }}>*</span>
      </div>
      <div className="kyc-upload-hint">{doc.hint}</div>
      <div className="kyc-upload-cta">
        Drag &amp; drop or <strong>click to browse</strong>
      </div>
      {sizeError && (
        <div className="alert-corporate danger" style={{ marginTop: 8, fontSize: '0.8rem' }}>
          {sizeError}
        </div>
      )}
    </label>
  );
}

function KycStep({ step, onBack, isFirstStep }) {
  const dispatch = useDispatch();
  const [files, setFiles] = useState({});
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const requiredDocuments = REQUIRED_KYC_DOCUMENTS;

  const handleFileChange = (key, file) => {
    setFiles((prev) => {
      const next = { ...prev };
      if (file) next[key] = file; else delete next[key];
      return next;
    });
    // Mirror selection state into Redux so the sidebar checklist ticks off.
    dispatch(setKycDocStatus({ key, present: !!file }));
    setError(null);
  };

  const handleContinue = async () => {
    const missing = requiredDocuments.filter((doc) => !files[doc.key]);
    if (missing.length > 0) {
      setError(`Please upload the following: ${missing.map((d) => d.label).join(', ')}`);
      return;
    }

    setLoading(true);
    setError(null);

    await dispatch(completeOnboardingStep(step.id));
    dispatch(fetchOnboardingStatus());
    setLoading(false);
  };

  return (
    <div className="ob-card">
      <div className="ob-card-header">
        <h5>KYC Document Upload</h5>
      </div>
      <div className="ob-card-body">
        {error && (
          <div className="alert-corporate danger" style={{ marginBottom: 16 }}>{error}</div>
        )}

        <div className="alert-corporate info" style={{ marginBottom: 20 }}>
          Upload the required documents below. Accepted formats: PDF, JPG, PNG (max {MAX_FILE_SIZE_MB}MB each).
        </div>

        <div style={{ display: 'grid', gap: 12 }}>
          {requiredDocuments.map((doc) => (
            <KycUploadTile
              key={doc.key}
              doc={doc}
              file={files[doc.key]}
              onChange={(file) => handleFileChange(doc.key, file)}
            />
          ))}
        </div>
      </div>
      <div className="ob-card-footer">
        {!isFirstStep ? (
          <button className="btn-secondary-custom" onClick={onBack}>
            &#8592; Back
          </button>
        ) : <div />}
        <button className="btn-primary-custom" onClick={handleContinue} disabled={loading}>
          {loading ? 'Uploading...' : 'Upload & Continue →'}
        </button>
      </div>
    </div>
  );
}

export default KycStep;
