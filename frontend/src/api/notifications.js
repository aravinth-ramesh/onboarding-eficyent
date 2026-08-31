import client from './client';

export const getNotifications = (page = 1) =>
  client.get(`/notifications?page=${page}`);

export const getUnreadCount = () =>
  client.get('/notifications/count');

export const getNotification = (id) =>
  client.get(`/notifications/${id}`);

export const markAsRead = (id) =>
  client.post(`/notifications/${id}/read`);

export const resolveNotification = (id, value, tableFileAnswers = []) => {
  // Plain JSON unless the answer carries replacement uploads for table file
  // cells — those have to go as multipart, or the File stringifies to {} and
  // the document is lost (report item 13).
  if (!tableFileAnswers.length) {
    return client.post(`/notifications/${id}/resolve`, { value });
  }

  const formData = new FormData();
  formData.append('value', typeof value === 'string' ? value : JSON.stringify(value));

  tableFileAnswers.forEach((entry, i) => {
    formData.append(`table_file_answers[${i}][row_index]`, entry.rowIndex);
    formData.append(`table_file_answers[${i}][column_key]`, entry.columnKey);
    formData.append(`table_file_answers[${i}][file]`, entry.file);
  });

  return client.post(`/notifications/${id}/resolve`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
};

export const resolveNotificationWithFile = (id, formData) =>
  client.post(`/notifications/${id}/resolve-upload`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
