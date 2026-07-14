import client from './client';

export const getMessages = () => client.get('/onboarding/messages');

export const sendMessage = (body) => client.post('/onboarding/messages', { body });

export const getUnreadMessageCount = () => client.get('/onboarding/messages/unread-count');

export const markMessagesRead = () => client.post('/onboarding/messages/read');
