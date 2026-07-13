import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useSelector } from 'react-redux';

function ProtectedRoute({ children }) {
  const { isAuthenticated, loading } = useSelector((state) => state.auth);
  const location = useLocation();

  if (loading) {
    return (
      <div className="spinner-corporate" style={{ minHeight: '100vh' }}>
        <div className="spinner-border" role="status" />
        <p>Loading...</p>
      </div>
    );
  }

  if (!isAuthenticated) {
    // Remember where the user was headed (e.g. an email deep link like
    // /home?notification=5) so LoginPage can return them there after OTP.
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  return children;
}

export default ProtectedRoute;
