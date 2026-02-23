import React, { useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { logoutUser } from '../../store/slices/authSlice';
import { useNavigate } from 'react-router-dom';
import appConfig from '../../appConfig';

function AppLayout({ children, pageTitle }) {
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const { user } = useSelector((state) => state.auth);
  const [showLogoutModal, setShowLogoutModal] = useState(false);

  const handleLogout = async () => {
    setShowLogoutModal(false);
    await dispatch(logoutUser());
    navigate('/login');
  };

  const userInitial = (user?.name || user?.email || '?').charAt(0).toUpperCase();

  return (
    <div className="app-wrapper">
      {/* Sidebar */}
      <aside className="app-sidebar">
        <div className="sidebar-brand">
          {appConfig.logoUrl ? (
            <img src={appConfig.logoUrl} alt={appConfig.siteName} className="sidebar-brand-logo" />
          ) : (
            <div className="sidebar-brand-icon">
              {appConfig.siteName.charAt(0)}
            </div>
          )}
          <div className="sidebar-brand-text">
            {appConfig.siteName}
            <small>{appConfig.siteTagline}</small>
          </div>
        </div>

        <nav className="sidebar-nav">
          <a href="/home" className="sidebar-nav-item active">
            <span className="sidebar-nav-icon">&#9632;</span>
            Onboarding
          </a>
        </nav>

        <div className="sidebar-footer">
          <div className="sidebar-user-info">
            <div className="sidebar-user-avatar">{userInitial}</div>
            <div className="sidebar-user-details">
              <div className="sidebar-user-name">{user?.name || 'User'}</div>
              <div className="sidebar-user-email">{user?.email}</div>
            </div>
            <button className="btn-logout" onClick={() => setShowLogoutModal(true)} title="Sign out">
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

      {/* Logout Confirmation Modal */}
      {showLogoutModal && (
        <div className="modal-overlay" onClick={() => setShowLogoutModal(false)}>
          <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h5>Sign Out</h5>
              <button className="modal-close" onClick={() => setShowLogoutModal(false)}>
                &#x2715;
              </button>
            </div>
            <div className="modal-body">
              <p>Are you sure you want to sign out? Any unsaved progress will be lost.</p>
            </div>
            <div className="modal-footer">
              <button className="btn-secondary-custom" onClick={() => setShowLogoutModal(false)}>
                Cancel
              </button>
              <button className="btn-danger-custom" onClick={handleLogout}>
                Sign Out
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

export default AppLayout;
