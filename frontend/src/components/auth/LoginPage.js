import React, { useState } from 'react';
import { Card, Form, Button, Alert, Spinner } from 'react-bootstrap';
import { useDispatch, useSelector } from 'react-redux';
import { sendOtp, verifyOtp, clearError, resetOtpState } from '../../store/slices/authSlice';
import { useNavigate } from 'react-router-dom';

function LoginPage() {
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const { loading, otpSent, error } = useSelector((state) => state.auth);

  const [email, setEmail] = useState('');
  const [code, setCode] = useState('');

  const handleSendOtp = async (e) => {
    e.preventDefault();
    dispatch(clearError());
    const result = await dispatch(sendOtp(email));
    if (!result.error) {
      // OTP sent successfully
    }
  };

  const handleVerifyOtp = async (e) => {
    e.preventDefault();
    dispatch(clearError());
    const result = await dispatch(verifyOtp({ email, code }));
    if (!result.error) {
      navigate('/home');
    }
  };

  const handleBack = () => {
    dispatch(resetOtpState());
    setCode('');
  };

  return (
    <div className="d-flex justify-content-center align-items-center min-vh-100 bg-light">
      <Card style={{ width: '100%', maxWidth: '420px' }}>
        <Card.Body className="p-4">
          <h3 className="text-center mb-4">Login</h3>

          {error && (
            <Alert variant="danger" dismissible onClose={() => dispatch(clearError())}>
              {error}
            </Alert>
          )}

          {!otpSent ? (
            <Form onSubmit={handleSendOtp}>
              <Form.Group className="mb-3">
                <Form.Label>Email Address</Form.Label>
                <Form.Control
                  type="email"
                  placeholder="Enter your email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  required
                  autoFocus
                />
              </Form.Group>
              <Button type="submit" variant="primary" className="w-100" disabled={loading}>
                {loading ? <Spinner size="sm" /> : 'Send Verification Code'}
              </Button>
            </Form>
          ) : (
            <Form onSubmit={handleVerifyOtp}>
              <p className="text-muted mb-3">
                A verification code has been sent to <strong>{email}</strong>
              </p>
              <Form.Group className="mb-3">
                <Form.Label>Verification Code</Form.Label>
                <Form.Control
                  type="text"
                  placeholder="Enter 6-digit code"
                  value={code}
                  onChange={(e) => setCode(e.target.value)}
                  maxLength={6}
                  required
                  autoFocus
                />
              </Form.Group>
              <Button type="submit" variant="primary" className="w-100 mb-2" disabled={loading}>
                {loading ? <Spinner size="sm" /> : 'Verify & Login'}
              </Button>
              <Button variant="link" className="w-100" onClick={handleBack}>
                Use a different email
              </Button>
            </Form>
          )}
        </Card.Body>
      </Card>
    </div>
  );
}

export default LoginPage;
