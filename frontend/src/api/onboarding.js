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

/**
 * Save answers with file uploads using multipart/form-data.
 * @param {Array} answers - Array of { question_id, value } for non-file answers
 * @param {Object} fileAnswers - Map of questionId -> File[] for file-type answers
 */
export const saveAnswersWithFiles = (answers, fileAnswers) => {
  const formData = new FormData();

  // Append non-file answers
  answers.forEach((answer, index) => {
    formData.append(`answers[${index}][question_id]`, answer.question_id);
    const val = Array.isArray(answer.value) ? JSON.stringify(answer.value) : (answer.value ?? '');
    formData.append(`answers[${index}][value]`, val);
  });

  // Append file answers
  let fileIndex = 0;
  Object.entries(fileAnswers).forEach(([questionId, files]) => {
    files.forEach((file) => {
      formData.append(`file_answers[${fileIndex}][question_id]`, questionId);
      formData.append(`file_answers[${fileIndex}][file]`, file);
      fileIndex++;
    });
  });

  return client.post('/onboarding/answers', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
};

export const completeStep = (stepId) =>
  client.post(`/onboarding/steps/${stepId}/complete`);

export const previousStep = (stepId) =>
  client.post(`/onboarding/steps/${stepId}/previous`);
