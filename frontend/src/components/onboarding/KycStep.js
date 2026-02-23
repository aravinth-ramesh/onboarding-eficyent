import React, { useState } from 'react';
import { useDispatch } from 'react-redux';
import { completeOnboardingStep, fetchOnboardingStatus } from '../../store/slices/onboardingSlice';

function KycStep({ step, onBack, isFirstStep }) {
  const dispatch = useDispatch();
  const [files, setFiles] = useState({});
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const requiredDocuments = [
    { key: 'id_proof', label: 'Government ID Proof', hint: 'Passport, National ID, or Driver\'s License' },
    { key: 'address_proof', label: 'Address Proof', hint: 'Utility bill or bank statement (last 3 months)' },
    { key: 'incorporation_cert', label: 'Certificate of Incorporation', hint: 'Official registration document' },
  ];

  const handleFileChange = (key, file) => {
    setFiles((prev) => ({ ...prev, [key]: file }));
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
          Upload the required documents below. Accepted formats: PDF, JPG, PNG (max 10MB each).
        </div>

        <div style={{ display: 'grid', gap: 12 }}>
          {requiredDocuments.map((doc) => (
            <label
              key={doc.key}
              className={`kyc-upload-item ${files[doc.key] ? 'uploaded' : ''}`}
            >
              <input
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                onChange={(e) => handleFileChange(doc.key, e.target.files[0])}
                style={{ display: 'none' }}
              />
              {files[doc.key] ? (
                <>
                  <div className="kyc-upload-success">{'\u2713'} {files[doc.key].name}</div>
                  <div className="kyc-upload-hint">Click to replace</div>
                </>
              ) : (
                <>
                  <div className="kyc-upload-label">
                    {doc.label} <span style={{ color: 'var(--color-danger)' }}>*</span>
                  </div>
                  <div className="kyc-upload-hint">{doc.hint}</div>
                  <div style={{ marginTop: 8, fontSize: '0.8rem', color: 'var(--color-accent)' }}>
                    Click to browse files
                  </div>
                </>
              )}
            </label>
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
          {loading ? 'Uploading...' : 'Upload & Continue \u2192'}
        </button>
      </div>
    </div>
  );
}

export default KycStep;
