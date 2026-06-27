import React from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { logoutUser, clearAuth } from '../../store/slices/authSlice';
import { useNavigate } from 'react-router-dom';
import appConfig from '../../appConfig';
import NotificationBell from '../notifications/NotificationBell';

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
  const { steps, currentStep, userType, status, onboardingId, startedAt } = useSelector(
    (state) => state.onboarding
  );

  const handleLogout = () => {
    dispatch(clearAuth());
    dispatch(logoutUser());
    navigate('/login');
  };

  const userInitial = (user?.name || user?.email || '?').charAt(0).toUpperCase();

  // ── Progress derived from live onboarding state ──
  const totalSteps = steps.length;
  const completedSteps = steps.filter((s) => s.status === 'completed').length;
  const progressPct = totalSteps ? Math.round((completedSteps / totalSteps) * 100) : 0;
  const currentIndex = currentStep ? steps.findIndex((s) => s.id === currentStep.id) : -1;
  const hasOnboarding = totalSteps > 0;

  const reference = formatReference(onboardingId, startedAt);
  const startedLabel = formatDate(startedAt);
  const statusLabel = humanizeStatus(status);
  const statusClass = status === 'completed' ? 'success' : 'progress';

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
                    const cls = isCompleted ? 'done' : isActive ? 'active' : '';
                    return (
                      <li key={step.id} className={cls}>
                        <span className="sb-dot">{isCompleted ? '✓' : index + 1}</span>
                        {step.name}
                      </li>
                    );
                  })}
                </ul>

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
          <a className="sb-card sb-help" href={`mailto:${appConfig.supportEmail}`}>
            <div className="sb-help-icon">?</div>
            <div>
              <div className="sb-help-title">Need a hand?</div>
              <div className="sb-help-sub">{appConfig.supportEmail}</div>
            </div>
          </a>
        </div>

        <div className="sidebar-footer">
          <div className="sidebar-user-info">
            <div className="sidebar-user-avatar">{userInitial}</div>
            <div className="sidebar-user-details">
              <div className="sidebar-user-name">{user?.name || 'User'}</div>
              <div className="sidebar-user-email">{user?.email}</div>
            </div>
            <button className="btn-logout" onClick={handleLogout} title="Sign out">
              &#x2192;
            </button>
          </div>
        </div>
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
