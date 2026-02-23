import React from 'react';
import { Navigate } from 'react-router-dom';
import { useSelector } from 'react-redux';

function ProtectedRoute({ children }) {
  const { isAuthenticated, loading } = useSelector((state) => state.auth);

  if (loading) {
    return (
      <div className="spinner-corporate" style={{ minHeight: '100vh' }}>
        <div className="spinner-border" role="status" />
        <p>Loading...</p>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return children;
}

export default ProtectedRoute;
