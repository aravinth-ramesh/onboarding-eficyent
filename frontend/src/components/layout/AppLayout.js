import React from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { logoutUser, clearAuth } from '../../store/slices/authSlice';
import { useNavigate, Link } from 'react-router-dom';
import appConfig from '../../appConfig';

function AppLayout({ children, pageTitle }) {
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const { user } = useSelector((state) => state.auth);

  const handleLogout = () => {
    localStorage.removeItem('auth_token');
    dispatch(clearAuth());
    dispatch(logoutUser());
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
          <Link to="/home" className="sidebar-nav-item active">
          <span className="sidebar-nav-icon">&#9632;</span>
          Onboarding
          </Link>
        </nav>

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
