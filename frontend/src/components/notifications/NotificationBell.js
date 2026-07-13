import React, { useEffect, useRef, useState, useCallback } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { useSearchParams } from 'react-router-dom';
import { fetchUnreadCount } from '../../store/slices/notificationSlice';
import NotificationDropdown from './NotificationDropdown';
import NotificationDetail from './NotificationDetail';

function NotificationBell() {
  const dispatch = useDispatch();
  const { unreadCount } = useSelector((state) => state.notifications);
  const [isOpen, setIsOpen] = useState(false);
  const [selectedId, setSelectedId] = useState(null);
  const bellRef = useRef(null);
  const [searchParams, setSearchParams] = useSearchParams();

  // Deep link from email: /home?notification={id} opens the detail modal
  // directly. The param is stripped so closing the modal doesn't reopen it.
  useEffect(() => {
    const target = Number(searchParams.get('notification'));
    if (target) {
      setSelectedId(target);
      const next = new URLSearchParams(searchParams);
      next.delete('notification');
      setSearchParams(next, { replace: true });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    dispatch(fetchUnreadCount());
    const interval = setInterval(() => {
      dispatch(fetchUnreadCount());
    }, 30000);
    return () => clearInterval(interval);
  }, [dispatch]);

  // Close dropdown on outside click
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (bellRef.current && !bellRef.current.contains(e.target) && !selectedId) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [selectedId]);

  const handleToggle = useCallback(() => {
    setIsOpen((prev) => !prev);
    setSelectedId(null);
  }, []);

  const handleSelectNotification = useCallback((id) => {
    setSelectedId(id);
    setIsOpen(false);
  }, []);

  const handleCloseDetail = useCallback(() => {
    setSelectedId(null);
    dispatch(fetchUnreadCount());
  }, [dispatch]);

  return (
    <>
      <div className="notification-bell-wrapper" ref={bellRef}>
        <button
          className="notification-bell-btn"
          onClick={handleToggle}
          title="Notifications"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
          </svg>
          {unreadCount > 0 && (
            <span className="notification-bell-badge">
              {unreadCount > 99 ? '99+' : unreadCount}
            </span>
          )}
        </button>
        {isOpen && (
          <NotificationDropdown
            onSelect={handleSelectNotification}
            onClose={() => setIsOpen(false)}
          />
        )}
      </div>
      {selectedId && (
        <NotificationDetail
          notificationId={selectedId}
          onClose={handleCloseDetail}
        />
      )}
    </>
  );
}

export default NotificationBell;
