import React, { useState } from 'react';
import { Card, Form, Button, Alert, Spinner } from 'react-bootstrap';
import { useDispatch } from 'react-redux';
import { completeOnboardingStep, fetchOnboardingStatus } from '../../store/slices/onboardingSlice';

function KycStep({ step }) {
  const dispatch = useDispatch();
  const [files, setFiles] = useState({});
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const requiredDocuments = [
    { key: 'id_proof', label: 'Government ID Proof' },
    { key: 'address_proof', label: 'Address Proof' },
    { key: 'incorporation_cert', label: 'Certificate of Incorporation' },
  ];

  const handleFileChange = (key, file) => {
    setFiles((prev) => ({ ...prev, [key]: file }));
  };

  const handleContinue = async () => {
    // Validate all required documents are uploaded
    const missing = requiredDocuments.filter((doc) => !files[doc.key]);
    if (missing.length > 0) {
      setError(`Please upload: ${missing.map((d) => d.label).join(', ')}`);
      return;
    }

    setLoading(true);
    setError(null);

    // In a real implementation, files would be uploaded via FormData to a file upload endpoint
    await dispatch(completeOnboardingStep(step.id));
    dispatch(fetchOnboardingStatus());
    setLoading(false);
  };

  return (
    <Card>
      <Card.Header>
        <h5 className="mb-0">KYC Document Upload</h5>
      </Card.Header>
      <Card.Body>
        {error && <Alert variant="danger">{error}</Alert>}

        <p className="text-muted mb-3">
          Please upload the following documents to complete your KYC verification.
        </p>

        {requiredDocuments.map((doc) => (
          <Form.Group key={doc.key} className="mb-3">
            <Form.Label>
              {doc.label} <span className="text-danger">*</span>
            </Form.Label>
            <Form.Control
              type="file"
              accept=".pdf,.jpg,.jpeg,.png"
              onChange={(e) => handleFileChange(doc.key, e.target.files[0])}
            />
            {files[doc.key] && (
              <Form.Text className="text-success">
                Selected: {files[doc.key].name}
              </Form.Text>
            )}
          </Form.Group>
        ))}

        <div className="mt-4 d-flex justify-content-end">
          <Button variant="primary" onClick={handleContinue} disabled={loading}>
            {loading ? <Spinner size="sm" /> : 'Upload & Continue'}
          </Button>
        </div>
      </Card.Body>
    </Card>
  );
}

export default KycStep;
