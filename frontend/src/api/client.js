import axios from 'axios';

const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:8000/api';

const client = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Attach auth token to every request
client.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Handle 401 responses globally (skip for logout endpoint to let the thunk handle it)
client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && !error.config?.url?.includes('/auth/logout')) {
      localStorage.removeItem('auth_token');
      if (!window.location.pathname.startsWith('/login')) {
        // Carry the current location through login so deep links
        // (e.g. /home?notification=5 from an email) survive an expired token.
        const target = window.location.pathname + window.location.search;
        window.location.href = '/login?redirect=' + encodeURIComponent(target);
      }
    }
    return Promise.reject(error);
  }
);

export default client;
