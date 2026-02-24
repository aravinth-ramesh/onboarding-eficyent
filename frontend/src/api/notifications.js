import client from './client';

export const getNotifications = (page = 1) =>
  client.get(`/notifications?page=${page}`);

export const getUnreadCount = () =>
  client.get('/notifications/count');

export const getNotification = (id) =>
  client.get(`/notifications/${id}`);

export const markAsRead = (id) =>
  client.post(`/notifications/${id}/read`);

export const resolveNotification = (id, value) =>
  client.post(`/notifications/${id}/resolve`, { value });

export const resolveNotificationWithFile = (id, formData) =>
  client.post(`/notifications/${id}/resolve-upload`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
