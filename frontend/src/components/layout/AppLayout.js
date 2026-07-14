import React, { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { logoutUser, clearAuth } from '../../store/slices/authSlice';
import { goToOnboardingStep, fetchOnboardingStatus } from '../../store/slices/onboardingSlice';
import { useNavigate, useSearchParams } from 'react-router-dom';
import appConfig from '../../appConfig';
import NotificationBell from '../notifications/NotificationBell';
import MessagesPanel from '../messages/MessagesPanel';
import TeamPanel from '../team/TeamPanel';
import NotificationSettingsPanel from '../settings/NotificationSettingsPanel';
import { getUnreadMessageCount } from '../../api/messages';
import { STEP_TIME_ESTIMATES, DEFAULT_STEP_MINUTES, REQUIRED_KYC_DOCUMENTS } from '../../config/onboardingConfig';
import { evaluateConditionalRules } from '../../utils/conditionalEngine';

const hasValue = (a) => {
  if (a === undefined || a === null || a === '') return false;
  if (Array.isArray(a) && a.length === 0) return false;
  return true;
};

// How far through the *current* step the user is (0–1), so the ring moves as
// data is entered — not only when a whole step completes.
function activeStepFraction(currentStep, questionGroups, answers, kycDocStatus) {
  if (!currentStep) return 0;
  const key = currentStep.component_key;

  if (key === 'questions' && questionGroups.length) {
    let total = 0;
    let filled = 0;
    questionGroups.forEach((g) => g.questions.forEach((q) => {
      const visible = !q.conditional_rules || q.conditional_rules.length === 0
        || evaluateConditionalRules(q.conditional_rules, answers);
      if (!visible) return;
      total += 1;
      if (hasValue(answers[q.id])) filled += 1;
    }));
    return total ? filled / total : 0.5;
  }

  if (key === 'kyc') {
    const total = REQUIRED_KYC_DOCUMENTS.length;
    const filled = REQUIRED_KYC_DOCUMENTS.filter((d) => kycDocStatus[d.key]).length;
    return total ? filled / total : 0.5;
  }

  // Other steps: count as half-done while active.
  return 0.5;
}

// Friendly date like "Jun 20, 2026"; returns null on bad/empty input.
function formatDate(value) {
  if (!value) return null;
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return null;
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

// e.g. ONB-2026-0042 (year from start date, falls back to padded id only).
function formatReference(id, startedAt) {
  if (!id) return null;
  const padded = String(id).padStart(4, '0');
  const d = startedAt ? new Date(startedAt) : null;
  const year = d && !Number.isNaN(d.getTime()) ? d.getFullYear() : null;
  return year ? `ONB-${year}-${padded}` : `ONB-${padded}`;
}

function humanizeStatus(status) {
  if (!status) return null;
  return status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function AppLayout({ children, pageTitle }) {
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const { user } = useSelector((state) => state.auth);
  const { steps, currentStep, userType, status, onboardingId, startedAt, kycDocStatus, questionGroups, answers } = useSelector(
    (state) => state.onboarding
  );

  const [searchParams, setSearchParams] = useSearchParams();
  const [messagesOpen, setMessagesOpen] = useState(false);
  const [teamOpen, setTeamOpen] = useState(false);
  const [settingsOpen, setSettingsOpen] = useState(false);
  const [unreadMessages, setUnreadMessages] = useState(0);

  // Unread badge: fetch on mount and poll alongside the notification bell.
  useEffect(() => {
    let active = true;
    const refresh = () =>
      getUnreadMessageCount()
        .then((r) => { if (active) setUnreadMessages(r.data.count); })
        .catch(() => {});
    refresh();
    const interval = setInterval(refresh, 30000);
    return () => { active = false; clearInterval(interval); };
  }, [messagesOpen]);

  // Email deep link: /home?messages=1 opens the thread directly.
  useEffect(() => {
    if (searchParams.get('messages')) {
      setMessagesOpen(true);
      const next = new URLSearchParams(searchParams);
      next.delete('messages');
      setSearchParams(next, { replace: true });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleCloseMessages = () => {
    setMessagesOpen(false);
    setUnreadMessages(0);
  };

  const handleLogout = () => {
    dispatch(clearAuth());
    dispatch(logoutUser());
    navigate('/login');
  };

  // Jump back to an already-completed step from the tracker.
  const handleStepClick = (step) => {
    if (step.status !== 'completed') return;
    if (currentStep && step.id === currentStep.id) return;
    dispatch(goToOnboardingStep(step.id)).then((result) => {
      if (!result.error) dispatch(fetchOnboardingStatus());
    });
  };

  const userInitial = (user?.name || user?.email || '?').charAt(0).toUpperCase();

  // ── Progress derived from live onboarding state ──
  const totalSteps = steps.length;
  const completedSteps = steps.filter((s) => s.status === 'completed').length;
  // Include partial progress through the active step so the ring responds to
  // data entry, not just whole-step completion.
  const activeFraction = currentStep && currentStep.status !== 'completed'
    ? activeStepFraction(currentStep, questionGroups, answers, kycDocStatus)
    : 0;
  const progressPct = totalSteps
    ? Math.min(100, Math.round(((completedSteps + activeFraction) / totalSteps) * 100))
    : 0;
  const currentIndex = currentStep ? steps.findIndex((s) => s.id === currentStep.id) : -1;
  const hasOnboarding = totalSteps > 0;

  const reference = formatReference(onboardingId, startedAt);
  const startedLabel = formatDate(startedAt);
  const statusLabel = humanizeStatus(status);
  const statusClass =
    status === 'completed' || status === 'approved'
      ? 'success'
      : status === 'rejected'
        ? 'danger'
        : 'progress';

  // Estimated time remaining from steps not yet completed.
  const minutesLeft = steps
    .filter((s) => s.status !== 'completed' && s.status !== 'skipped')
    .reduce((sum, s) => sum + (STEP_TIME_ESTIMATES[s.component_key] ?? DEFAULT_STEP_MINUTES), 0);

  const onKycStep = currentStep?.component_key === 'kyc';

  return (
    <div className="app-wrapper">
      {/* Sidebar */}
      <aside className="app-sidebar">
        <div className="sidebar-brand">
          {appConfig.logoUrl ? (
            <img src={appConfig.logoUrl} alt={appConfig.siteName} className="sidebar-brand-logo" />
          ) : (
            <div className="sidebar-brand-icon">{appConfig.siteName.charAt(0)}</div>
          )}
          <div className="sidebar-brand-text">
            {appConfig.siteName}
            <small>{appConfig.siteTagline}</small>
          </div>
        </div>

        <div className="sidebar-stack">
          {hasOnboarding ? (
            <>
              {/* Progress tracker */}
              <div className="sb-card">
                <div className="sb-progress-top">
                  <div className="sb-ring" style={{ '--pct': `${progressPct}%` }}>
                    <span>{progressPct}%</span>
                  </div>
                  <div>
                    <div className="sb-progress-title">Your progress</div>
                    <div className="sb-progress-sub">
                      {currentStep
                        ? `Step ${currentIndex + 1} of ${totalSteps} · ${currentStep.name}`
                        : `${completedSteps} of ${totalSteps} complete`}
                    </div>
                  </div>
                </div>

                <ul className="sb-steps">
                  {steps.map((step, index) => {
                    const isCompleted = step.status === 'completed';
                    const isActive = currentStep && step.id === currentStep.id;
                    const clickable = isCompleted && !isActive;
                    const cls = `${isCompleted ? 'done' : isActive ? 'active' : ''}${clickable ? ' clickable' : ''}`.trim();
                    return (
                      <li
                        key={step.id}
                        className={cls}
                        onClick={clickable ? () => handleStepClick(step) : undefined}
                        role={clickable ? 'button' : undefined}
                        tabIndex={clickable ? 0 : undefined}
                        onKeyDown={clickable ? (e) => {
                          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleStepClick(step); }
                        } : undefined}
                        title={clickable ? `Go back to ${step.name}` : undefined}
                      >
                        <span className="sb-dot">{isCompleted ? '✓' : index + 1}</span>
                        {step.name}
                        {clickable && <span className="sb-step-jump">{'↩'}</span>}
                      </li>
                    );
                  })}
                </ul>

                {minutesLeft > 0 && status !== 'completed' && (
                  <div className="sb-eta">{'⏱'} About {minutesLeft} min remaining</div>
                )}

                <div className="sb-autosave">
                  <span className="sb-autosave-dot" />
                  Progress saved automatically
                </div>
              </div>

              {/* Application summary */}
              <div className="sb-card">
                <div className="sb-card-label">Your application</div>
                {reference && (
                  <div className="sb-info-row">
                    <span>Reference</span>
                    <strong>{reference}</strong>
                  </div>
                )}
                {userType?.name && (
                  <div className="sb-info-row">
                    <span>Type</span>
                    <strong>{userType.name}</strong>
                  </div>
                )}
                {startedLabel && (
                  <div className="sb-info-row">
                    <span>Started</span>
                    <strong>{startedLabel}</strong>
                  </div>
                )}
                {statusLabel && (
                  <div className="sb-info-row">
                    <span>Status</span>
                    <span className={`sb-pill ${statusClass}`}>{statusLabel}</span>
                  </div>
                )}
              </div>

              {/* KYC document checklist (only while on the KYC step) */}
              {onKycStep && (
                <div className="sb-card">
                  <div className="sb-card-label">Documents needed</div>
                  <ul className="sb-checklist">
                    {REQUIRED_KYC_DOCUMENTS.map((doc) => {
                      const done = !!kycDocStatus[doc.key];
                      return (
                        <li key={doc.key} className={done ? 'done' : ''}>
                          <span className="sb-check">{done ? '✓' : ''}</span>
                          {doc.label}
                        </li>
                      );
                    })}
                  </ul>
                </div>
              )}
            </>
          ) : (
            <div className="sb-card">
              <div className="sb-progress-title">Welcome{user?.name ? `, ${user.name.split(' ')[0]}` : ''} {'\u{1F44B}'}</div>
              <div className="sb-progress-sub" style={{ marginTop: 4 }}>
                Let's get your onboarding set up.
              </div>
            </div>
          )}

          {/* Help / support */}
          <button type="button" className="sb-card sb-help sb-messages" onClick={() => setTeamOpen(true)}>
            <div className="sb-help-icon">{'👥'}</div>
            <div>
              <div className="sb-help-title">Team</div>
              <div className="sb-help-sub">Invite colleagues to collaborate</div>
            </div>
          </button>

          <button type="button" className="sb-card sb-help sb-messages" onClick={() => setMessagesOpen(true)}>
            <div className="sb-help-icon">
              {'💬'}
              {unreadMessages > 0 && <span className="sb-messages-badge">{unreadMessages}</span>}
            </div>
            <div>
              <div className="sb-help-title">Messages</div>
              <div className="sb-help-sub">
                {unreadMessages > 0 ? `${unreadMessages} new repl${unreadMessages === 1 ? 'y' : 'ies'}` : 'Chat with our team'}
              </div>
            </div>
          </button>
        </div>

        {messagesOpen && <MessagesPanel onClose={handleCloseMessages} />}
        {teamOpen && <TeamPanel onClose={() => setTeamOpen(false)} />}

        <div className="sidebar-footer">
          <div className="sidebar-user-info">
            <div className="sidebar-user-avatar">{userInitial}</div>
            <div className="sidebar-user-details">
              <div className="sidebar-user-name">{user?.name || 'User'}</div>
              <div className="sidebar-user-email">{user?.email}</div>
            </div>
            <button className="btn-logout" onClick={() => setSettingsOpen(true)} title="Email notification settings">
              &#9881;
            </button>
            <button className="btn-logout" onClick={handleLogout} title="Sign out">
              &#x2192;
            </button>
          </div>
        </div>
        {settingsOpen && <NotificationSettingsPanel onClose={() => setSettingsOpen(false)} />}
      </aside>

      {/* Main Content */}
      <main className="app-main">
        <header className="app-topbar">
          <div className="topbar-title">{pageTitle || 'Client Onboarding'}</div>
          <div className="topbar-actions">
            <NotificationBell />
            <span style={{ fontSize: '0.8rem', color: 'var(--color-text-muted)' }}>
              {user?.email}
            </span>
          </div>
        </header>

        <div className="app-content">
          {children}
        </div>

        <footer className="app-footer">
          {appConfig.copyrightText}
        </footer>
      </main>
    </div>
  );
}

export default AppLayout;
