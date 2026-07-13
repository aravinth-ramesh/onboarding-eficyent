import React, { useEffect } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { useDispatch, useSelector } from 'react-redux';
import { fetchUser } from './store/slices/authSlice';
import LoginPage from './components/auth/LoginPage';
import HomePage from './pages/HomePage';
import ProtectedRoute from './components/common/ProtectedRoute';
import ThemeProvider from './components/common/ThemeProvider';
import 'bootstrap/dist/css/bootstrap.min.css';
import './theme.css';

function App() {
  const dispatch = useDispatch();
  const { isAuthenticated, loading } = useSelector((state) => state.auth);

  useEffect(() => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      dispatch(fetchUser());
    }
  }, [dispatch]);

  if (loading) {
    return (
      <ThemeProvider>
        <div className="spinner-corporate" style={{ minHeight: '100vh' }}>
          <div className="spinner-border" role="status" />
          <p>Loading...</p>
        </div>
      </ThemeProvider>
    );
  }

  return (
    <ThemeProvider>
      <BrowserRouter>
        <Routes>
          {/* LoginPage handles the authenticated redirect itself so email
              deep links (?notification=...) survive the OTP round-trip. */}
          <Route path="/login" element={<LoginPage />} />
          <Route
            path="/home"
            element={
              <ProtectedRoute>
                <HomePage />
              </ProtectedRoute>
            }
          />
          <Route path="*" element={<Navigate to={isAuthenticated ? '/home' : '/login'} replace />} />
        </Routes>
      </BrowserRouter>
    </ThemeProvider>
  );
}

export default App;
