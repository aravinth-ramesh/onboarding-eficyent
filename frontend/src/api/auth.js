import client from './client';

export const sendOtp = (email) =>
  client.post('/auth/send-otp', { email });

export const verifyOtp = (email, code) =>
  client.post('/auth/verify-otp', { email, code });

export const getMe = () =>
  client.get('/auth/me');

export const logout = () =>
  client.post('/auth/logout');
