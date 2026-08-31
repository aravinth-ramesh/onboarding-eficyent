import React, { useState, useRef, useCallback, useEffect } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { sendOtp, verifyOtp, clearError, resetOtpState, logoutUser } from '../../store/slices/authSlice';
import { Navigate, useLocation } from 'react-router-dom';
import { getInvitation, acceptInvitation } from '../../api/team';
import appConfig from '../../appConfig';

// Stricter than the browser's `type="email"`, which follows the WHATWG rule
// and accepts a dotless domain such as "aaaa@a" (EOP-2).
const isValidEmail = (value) => /^[^\s@]+@[^\s@.]+(\.[^\s@.]+)+$/.test(String(value || '').trim());

/** Whole seconds left until a deadline, or null when there isn't one. */
const remainingSeconds = (deadline) =>
  deadline == null ? null : Math.max(0, Math.ceil((deadline - Date.now()) / 1000));

const formatCountdown = (seconds) => {
  const s = Math.max(0, seconds);
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
};

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
  const { isAuthenticated, user, loading, otpSent, error, email } = useSelector((state) => state.auth);

  const [emailInput, setEmailInput] = useState('');
  const [emailError, setEmailError] = useState(null);
  const [otpDigits, setOtpDigits] = useState(['', '', '', '', '', '']);
  // Seconds until the code expires, and until a resend is allowed (EOP-3/EOP-4).
  const [expiresIn, setExpiresIn] = useState(null);
  const [resendIn, setResendIn] = useState(0);
  // Absolute deadlines the counters are recomputed from. Subtracting one per
  // tick drifts: browsers throttle timers in a background tab and stop them
  // while the machine sleeps, so the page would still show time remaining on a
  // code the server had already expired.
  const expiryDeadline = useRef(null);
  const resendDeadline = useRef(null);
  const inputRefs = useRef([]);

  // Team invitation deep link (?invite=token). The link must never simply drop
  // the visitor into whatever session the browser already holds — that is how
  // an invitee ended up inside the owner's account (EOP-53).
  const inviteToken = new URLSearchParams(location.search).get('invite');
  const [invitation, setInvitation] = useState(null);
  const [inviteError, setInviteError] = useState(null);
  const [inviteAccepted, setInviteAccepted] = useState(false);

  useEffect(() => {
    if (!inviteToken) return;
    let cancelled = false;
    getInvitation(inviteToken)
      .then((res) => {
        if (cancelled) return;
        const data = res.data.data;
        setInvitation(data);
        setEmailInput((prev) => prev || data.email);
      })
      .catch(() => {
        if (!cancelled) setInviteError('This invitation link is not valid or has expired.');
      });
    return () => { cancelled = true; };
  }, [inviteToken]);

  // Signed in as the invited address: bind the membership, then continue.
  const invitedEmail = invitation?.email?.toLowerCase();
  const currentEmail = user?.email?.toLowerCase();
  const wrongAccount = Boolean(inviteToken && invitation && isAuthenticated && currentEmail && currentEmail !== invitedEmail);

  useEffect(() => {
    if (!inviteToken || !isAuthenticated || !invitation) return;
    if (!currentEmail || currentEmail !== invitedEmail) return;
    let cancelled = false;
    acceptInvitation(inviteToken)
      .catch(() => { /* already a member, or already accepted */ })
      .finally(() => { if (!cancelled) setInviteAccepted(true); });
    return () => { cancelled = true; };
  }, [inviteToken, isAuthenticated, invitation, currentEmail, invitedEmail]);

  const requestOtp = useCallback(async (address) => {
    dispatch(clearError());
    const result = await dispatch(sendOtp(address));
    // The API reports how long the code lasts and when a resend is allowed.
    const payload = result?.payload;

    if (payload && !result.error) {
      // Prefer the server's absolute deadline; fall back to the duration for an
      // older backend that only sends seconds.
      const parsed = payload.expires_at ? Date.parse(payload.expires_at) : NaN;
      expiryDeadline.current = Number.isNaN(parsed)
        ? (payload.expires_in_seconds != null ? Date.now() + payload.expires_in_seconds * 1000 : null)
        : parsed;
      resendDeadline.current = Date.now() + (payload.resend_available_in_seconds ?? 0) * 1000;

      setExpiresIn(remainingSeconds(expiryDeadline.current));
      setResendIn(remainingSeconds(resendDeadline.current) ?? 0);
    } else if (result?.error) {
      // A refused resend still tells us when one becomes available (EOP-3).
      const wait = result.payload?.resend_available_in_seconds;
      if (wait != null) {
        resendDeadline.current = Date.now() + wait * 1000;
        setResendIn(wait);
      }
    }

    return result;
  }, [dispatch]);

  const handleSendOtp = async (e) => {
    e.preventDefault();
    // `type="email"` alone accepts a dotless domain like "aaaa@a", so the
    // portal would mail a code to an undeliverable address (EOP-2).
    const address = emailInput.trim();
    if (!isValidEmail(address)) {
      setEmailError('Enter a valid email address, for example name@company.com.');
      return;
    }
    setEmailError(null);
    await requestOtp(address);
  };

  const handleResendOtp = async () => {
    if (resendIn > 0) return;
    setOtpDigits(['', '', '', '', '', '']);
    await requestOtp(email);
  };

  // One ticker drives both countdowns, recomputing each from its deadline so a
  // throttled or suspended tab catches up instead of drifting.
  useEffect(() => {
    if (!otpSent) return undefined;
    const tick = () => {
      setExpiresIn(remainingSeconds(expiryDeadline.current));
      setResendIn(remainingSeconds(resendDeadline.current) ?? 0);
    };
    tick();
    const id = setInterval(tick, 1000);
    return () => clearInterval(id);
  }, [otpSent]);

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

  // An invite link in a browser already signed in as someone else must stop
  // here — redirecting would show that account's application (EOP-53).
  if (wrongAccount) {
    return (
      <div className="login-page">
        <div className="login-right-panel">
          <div className="login-card">
            <h2>Wrong account</h2>
            <p className="login-subtitle">
              This invitation was sent to <strong>{invitation.email}</strong>, but you are
              signed in as <strong>{user.email}</strong>.
            </p>
            <div className="alert-corporate danger" style={{ marginBottom: 16 }}>
              Sign out and sign in as {invitation.email} to join the application.
            </div>
            <button
              type="button"
              className="btn-primary-custom"
              onClick={() => dispatch(logoutUser())}
              style={{ width: '100%', justifyContent: 'center', padding: '0.7rem' }}
            >
              Sign out
            </button>
          </div>
        </div>
      </div>
    );
  }

  // Hold the redirect until the invitation is bound to this account.
  if (isAuthenticated && inviteToken && !inviteAccepted && !inviteError) {
    return (
      <div className="spinner-corporate" style={{ minHeight: '100vh' }}>
        <div className="spinner-border" role="status" />
        <p>Joining the application...</p>
      </div>
    );
  }

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
              <h2>{invitation ? 'Join the application' : appConfig.login.heading}</h2>
              <p className="login-subtitle">
                {invitation
                  ? `${invitation.inviter} invited you to collaborate${invitation.company ? ` on ${invitation.company}` : ''}. Verify your email to join.`
                  : appConfig.login.subheading}
              </p>

              {inviteError && (
                <div className="alert-corporate danger" style={{ marginBottom: 16 }}>
                  {inviteError}
                </div>
              )}

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
                    onChange={(e) => { setEmailInput(e.target.value); if (emailError) setEmailError(null); }}
                    required
                    autoFocus={!invitation}
                    /* The invitation is bound to one address — signing in as
                       anyone else must not join this application (EOP-53). */
                    readOnly={Boolean(invitation)}
                    style={{ width: '100%', padding: '0.65rem 0.85rem' }}
                  />
                  {emailError && (
                    <div className="question-error" style={{ marginTop: 6 }}>{emailError}</div>
                  )}
                  {invitation && (
                    <div className="login-subtitle" style={{ fontSize: '0.8rem', marginTop: 6 }}>
                      This invitation is for this address only.
                    </div>
                  )}
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
              {expiresIn !== null && (
                <p className="login-subtitle" style={{ marginTop: -8 }}>
                  {expiresIn > 0 ? (
                    <>This code expires in <strong>{formatCountdown(expiresIn)}</strong>.</>
                  ) : (
                    <>This code has expired — request a new one.</>
                  )}
                </p>
              )}

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

                <div style={{ textAlign: 'center', display: 'flex', flexDirection: 'column', gap: 4 }}>
                  {/* Without this the only recovery from a lost or expired
                      code was to start over (EOP-3). */}
                  <button
                    type="button"
                    className="btn-link-custom"
                    onClick={handleResendOtp}
                    disabled={loading || resendIn > 0}
                  >
                    {resendIn > 0 ? `Resend code in ${formatCountdown(resendIn)}` : 'Resend code'}
                  </button>
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
