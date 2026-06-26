import React, { useEffect } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { fetchNotifications, markNotificationAsRead } from '../../store/slices/notificationSlice';

function NotificationDropdown({ onSelect, onClose }) {
  const dispatch = useDispatch();
  const { notifications, loading } = useSelector((state) => state.notifications);

  useEffect(() => {
    dispatch(fetchNotifications());
  }, [dispatch]);

  const changeRequests = notifications.filter((n) => n.type === 'change_request');
  const newQuestions = notifications.filter((n) => n.type === 'new_question');

  const handleClick = (notification) => {
    if (!notification.read_at) {
      dispatch(markNotificationAsRead(notification.id));
    }
    onSelect(notification.id);
  };

  const formatTime = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) return `${diffHours}h ago`;
    const diffDays = Math.floor(diffHours / 24);
    if (diffDays < 7) return `${diffDays}d ago`;
    return date.toLocaleDateString();
  };

  const renderNotificationItem = (notification) => (
    <button
      key={notification.id}
      className={`notification-item ${!notification.read_at ? 'unread' : ''} ${notification.status === 'resolved' ? 'resolved' : ''}`}
      onClick={() => handleClick(notification)}
    >
      <div className="notification-item-icon">
        {notification.type === 'change_request' ? (
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
          </svg>
        ) : (
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="16" />
            <line x1="8" y1="12" x2="16" y2="12" />
          </svg>
        )}
      </div>
      <div className="notification-item-content">
        <div className="notification-item-title">
          {notification.question_label || 'Notification'}
        </div>
        <div className="notification-item-message">
          {notification.message.length > 80
            ? notification.message.substring(0, 80) + '...'
            : notification.message}
        </div>
        <div className="notification-item-meta">
          <span className="notification-item-time">{formatTime(notification.created_at)}</span>
          {notification.status === 'resolved' && (
            <span className="notification-item-status resolved">Resolved</span>
          )}
          {notification.status === 'pending' && (
            <span className="notification-item-status pending">Pending</span>
          )}
        </div>
      </div>
      {!notification.read_at && <span className="notification-item-dot" />}
    </button>
  );

  return (
    <div className="notification-dropdown">
      <div className="notification-dropdown-header">
        <h6>Notifications</h6>
        {notifications.length > 0 && (
          <span className="notification-dropdown-count">{notifications.length}</span>
        )}
      </div>
      <div className="notification-dropdown-body">
        {loading && notifications.length === 0 ? (
          <div className="notification-dropdown-empty">
            <div className="spinner-border spinner-border-sm" role="status" />
            <span>Loading...</span>
          </div>
        ) : notifications.length === 0 ? (
          <div className="notification-dropdown-empty">
            No notifications yet
          </div>
        ) : (
          <>
            {changeRequests.length > 0 && (
              <div className="notification-section">
                <div className="notification-section-title">Requested Changes</div>
                {changeRequests.map(renderNotificationItem)}
              </div>
            )}
            {newQuestions.length > 0 && (
              <div className="notification-section">
                <div className="notification-section-title">New Questions</div>
                {newQuestions.map(renderNotificationItem)}
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}

export default NotificationDropdown;
