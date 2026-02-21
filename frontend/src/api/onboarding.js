import client from './client';

export const getUserTypes = () =>
  client.get('/user-types');

export const getOnboardingStatus = () =>
  client.get('/onboarding/status');

export const setUserType = (userTypeId, subcategoryId = null) =>
  client.post('/onboarding/set-type', {
    user_type_id: userTypeId,
    subcategory_id: subcategoryId,
  });

export const getQuestions = () =>
  client.get('/onboarding/questions');

export const saveAnswers = (answers) =>
  client.post('/onboarding/answers', { answers });

export const completeStep = (stepId) =>
  client.post(`/onboarding/steps/${stepId}/complete`);
