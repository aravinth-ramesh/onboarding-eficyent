import React, { useState, useRef, useCallback } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { sendOtp, verifyOtp, clearError, resetOtpState } from '../../store/slices/authSlice';
import { Navigate, useLocation } from 'react-router-dom';
import appConfig from '../../appConfig';

// Where to land after login. Sources, in order: the location ProtectedRoute
// stashed when it bounced the user here, or a ?redirect= param set by the
// 401 interceptor. Only same-origin paths are honoured.
function postLoginTarget(location) {
  const from = location.state?.from;
  if (from?.pathname) return from.pathname + (from.search || '');

  const redirect = new URLSearchParams(location.search).get('redirect');
  if (redirect && redirect.startsWith('/') && !redirect.startsWith('//')) return redirect;

  return '/home';
}

function LoginPage() {
  const dispatch = useDispatch();
  const location = useLocation();
  const { isAuthenticated, loading, otpSent, error, email } = useSelector((state) => state.auth);

  const [emailInput, setEmailInput] = useState('');
  const [otpDigits, setOtpDigits] = useState(['', '', '', '', '', '']);
  const inputRefs = useRef([]);

  const handleSendOtp = async (e) => {
    e.preventDefault();
    dispatch(clearError());
    await dispatch(sendOtp(emailInput));
  };

  const submitOtp = useCallback((code) => {
    dispatch(clearError());
    dispatch(verifyOtp({ email, code }));
  }, [email, dispatch]);

  const handleOtpChange = useCallback((index, value) => {
    if (value.length > 1) value = value.charAt(value.length - 1);
    if (value && !/^\d$/.test(value)) return;

    setOtpDigits((prev) => {
      const next = [...prev];
      next[index] = value;

      if (value && index < 5) {
        inputRefs.current[index + 1]?.focus();
      }

      const code = next.join('');
      if (code.length === 6 && value) {
        setTimeout(() => submitOtp(code), 50);
      }

      return next;
    });
  }, [submitOtp]);

  const handleOtpKeyDown = (index, e) => {
    if (e.key === 'Backspace' && !otpDigits[index] && index > 0) {
      inputRefs.current[index - 1]?.focus();
    }
  };

  const handleOtpPaste = (e) => {
    e.preventDefault();
    const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
    if (!pasted) return;
    const newDigits = Array.from({ length: 6 }, (_, i) => pasted[i] || '');
    setOtpDigits(newDigits);
    if (pasted.length === 6) submitOtp(pasted);
  };

  const handleVerifyManual = async (e) => {
    e.preventDefault();
    const code = otpDigits.join('');
    if (code.length !== 6) return;
    submitOtp(code);
  };

  const handleBack = () => {
    dispatch(resetOtpState());
    setOtpDigits(['', '', '', '', '', '']);
  };

  if (isAuthenticated) {
    return <Navigate to={postLoginTarget(location)} replace />;
  }

  return (
    <div className="login-page">
      {/* Left branding panel */}
      <div className="login-left-panel">
        <div style={{ maxWidth: 400 }}>
          {appConfig.logoUrl ? (
            <img src={appConfig.logoUrl} alt={appConfig.siteName} style={{ height: 48, marginBottom: 24 }} />
          ) : (
            <div style={{ fontSize: '2.5rem', fontWeight: 800, marginBottom: 8 }}>
              {appConfig.siteName}
            </div>
          )}
          <h1 style={{ fontSize: '1.5rem', fontWeight: 400 }}>{appConfig.siteTagline}</h1>
          <p style={{ fontSize: '0.9rem', marginTop: 16, lineHeight: 1.7 }}>
            Complete your onboarding process securely and efficiently. Our streamlined
            workflow guides you through each step.
          </p>
        </div>
      </div>

      {/* Right form panel */}
      <div className="login-right-panel">
        <div className="login-card">
          {!otpSent ? (
            <>
              <h2>{appConfig.login.heading}</h2>
              <p className="login-subtitle">{appConfig.login.subheading}</p>

              {error && (
                <div className="alert-corporate danger" style={{ marginBottom: 16 }}>
                  {error}
                </div>
              )}

              <form onSubmit={handleSendOtp}>
                <div style={{ marginBottom: 16 }}>
                  <label className="question-label" style={{ display: 'block', marginBottom: 6 }}>
                    Email Address
                  </label>
                  <input
                    type="email"
                    className="form-control"
                    placeholder="name@company.com"
                    value={emailInput}
                    onChange={(e) => setEmailInput(e.target.value)}
                    required
                    autoFocus
                    style={{ width: '100%', padding: '0.65rem 0.85rem' }}
                  />
                </div>
                <button
                  type="submit"
                  className="btn-primary-custom"
                  disabled={loading}
                  style={{ width: '100%', justifyContent: 'center', padding: '0.7rem' }}
                >
                  {loading ? 'Sending...' : 'Continue'}
                </button>
              </form>
            </>
          ) : (
            <>
              <h2>{appConfig.login.otpHeading}</h2>
              <p className="login-subtitle">
                We sent a 6-digit code to <strong>{email}</strong>
              </p>

              {error && (
                <div className="alert-corporate danger" style={{ marginBottom: 16 }}>
                  {error}
                </div>
              )}

              <form onSubmit={handleVerifyManual}>
                <div className="otp-input-group" onPaste={handleOtpPaste}>
                  {otpDigits.map((digit, i) => (
                    <input
                      key={i}
                      ref={(el) => (inputRefs.current[i] = el)}
                      type="text"
                      inputMode="numeric"
                      maxLength={1}
                      value={digit}
                      onChange={(e) => handleOtpChange(i, e.target.value)}
                      onKeyDown={(e) => handleOtpKeyDown(i, e)}
                      autoFocus={i === 0}
                    />
                  ))}
                </div>

                <button
                  type="submit"
                  className="btn-primary-custom"
                  disabled={loading || otpDigits.join('').length !== 6}
                  style={{ width: '100%', justifyContent: 'center', padding: '0.7rem', marginBottom: 12 }}
                >
                  {loading ? 'Verifying...' : 'Verify & Sign In'}
                </button>

                <div style={{ textAlign: 'center' }}>
                  <button type="button" className="btn-link-custom" onClick={handleBack}>
                    &#8592; Use a different email
                  </button>
                </div>
              </form>
            </>
          )}
        </div>
      </div>
    </div>
  );
}

export default LoginPage;
