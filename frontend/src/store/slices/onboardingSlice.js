import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';
import { logoutUser, clearAuth } from './authSlice';
import * as onboardingApi from '../../api/onboarding';

export const fetchOnboardingStatus = createAsyncThunk(
  'onboarding/fetchStatus',
  async (_, { rejectWithValue }) => {
    try {
      const response = await onboardingApi.getOnboardingStatus();
      return response.data.data;
    } catch (error) {
      return rejectWithValue(error.response?.data?.message || 'Failed to fetch onboarding status');
    }
  }
);

export const fetchUserTypes = createAsyncThunk(
  'onboarding/fetchUserTypes',
  async (_, { rejectWithValue }) => {
    try {
      const response = await onboardingApi.getUserTypes();
      return response.data.data;
    } catch (error) {
      return rejectWithValue(error.response?.data?.message || 'Failed to fetch user types');
    }
  }
);

export const selectUserType = createAsyncThunk(
  'onboarding/selectUserType',
  async ({ userTypeId, subcategoryId }, { rejectWithValue }) => {
    try {
      const response = await onboardingApi.setUserType(userTypeId, subcategoryId);
      return response.data.data;
    } catch (error) {
      return rejectWithValue(error.response?.data?.message || 'Failed to set user type');
    }
  }
);

export const fetchQuestions = createAsyncThunk(
  'onboarding/fetchQuestions',
  async (_, { rejectWithValue }) => {
    try {
      const response = await onboardingApi.getQuestions();
      return response.data.data;
    } catch (error) {
      return rejectWithValue(error.response?.data?.message || 'Failed to fetch questions');
    }
  }
);

export const submitAnswers = createAsyncThunk(
  'onboarding/submitAnswers',
  async ({ answers, fileAnswers, tableFileAnswers, fileJustifications }, { rejectWithValue }) => {
    try {
      const hasFiles =
        (fileAnswers && Object.keys(fileAnswers).length > 0) ||
        (Array.isArray(tableFileAnswers) && tableFileAnswers.length > 0);
      const response = hasFiles
        ? await onboardingApi.saveAnswersWithFiles(answers, fileAnswers, tableFileAnswers, fileJustifications)
        : await onboardingApi.saveAnswers(answers);
      return response.data;
    } catch (error) {
      const data = error.response?.data;
      return rejectWithValue({
        message: data?.message || 'Failed to save answers',
        // Per-question AI document validation failures (422), keyed by
        // question id — see OnboardingController::saveAnswers.
        documentValidation: data?.code === 'document_validation_failed'
          ? data.document_validation
          : null,
      });
    }
  }
);

export const completeOnboardingStep = createAsyncThunk(
  'onboarding/completeStep',
  async (stepId, { rejectWithValue }) => {
    try {
      const response = await onboardingApi.completeStep(stepId);
      return response.data.data;
    } catch (error) {
      return rejectWithValue(error.response?.data?.message || 'Failed to complete step');
    }
  }
);

export const goToPreviousStep = createAsyncThunk(
  'onboarding/previousStep',
  async (stepId, { rejectWithValue }) => {
    try {
      const response = await onboardingApi.previousStep(stepId);
      return response.data.data;
    } catch (error) {
      return rejectWithValue(error.response?.data?.message || 'Failed to go back');
    }
  }
);

export const goToOnboardingStep = createAsyncThunk(
  'onboarding/goToStep',
  async (stepId, { rejectWithValue }) => {
    try {
      const response = await onboardingApi.gotoStep(stepId);
      return response.data.data;
    } catch (error) {
      return rejectWithValue(error.response?.data?.message || 'Failed to navigate to step');
    }
  }
);

export const discardDraft = createAsyncThunk(
  'onboarding/discardDraft',
  async (_, { rejectWithValue }) => {
    try {
      const response = await onboardingApi.deleteDraft();
      return response.data.data;
    } catch (error) {
      return rejectWithValue(error.response?.data?.message || 'Could not reset the application');
    }
  }
);

export const reopenOnboarding = createAsyncThunk(
  'onboarding/reopen',
  async (_, { rejectWithValue }) => {
    try {
      const response = await onboardingApi.reopenOnboarding();
      return response.data.data;
    } catch (error) {
      return rejectWithValue(error.response?.data?.message || 'Failed to reopen the application');
    }
  }
);

const onboardingSlice = createSlice({
  name: 'onboarding',
  initialState: {
    status: null,
    steps: [],
    currentStep: null,
    userType: null,
    subcategory: null,
    onboardingId: null,
    startedAt: null,
    countryCode: null,
    decidedAt: null,
    decisionComment: null,
    userTypes: [],
    questionGroups: [],
    answers: {},
    kycDocStatus: {},
    loading: false,
    error: null,
  },
  reducers: {
    setAnswer: (state, action) => {
      const { questionId, value } = action.payload;
      state.answers[questionId] = value;
    },
    setKycDocStatus: (state, action) => {
      const { key, present } = action.payload;
      state.kycDocStatus[key] = present;
    },
    clearOnboarding: (state) => {
      state.status = null;
      state.steps = [];
      state.currentStep = null;
      state.questionGroups = [];
      state.answers = {};
    },
  },
  extraReducers: (builder) => {
    builder
      // Fetch Status
      .addCase(fetchOnboardingStatus.pending, (state) => {
        state.loading = true;
      })
      .addCase(fetchOnboardingStatus.fulfilled, (state, action) => {
        state.loading = false;
        state.status = action.payload.status;
        state.steps = action.payload.steps;
        state.currentStep = action.payload.current_step;
        state.userType = action.payload.user_type || null;
        state.subcategory = action.payload.subcategory || null;
        state.onboardingId = action.payload.id ?? null;
        state.startedAt = action.payload.started_at ?? null;
        state.countryCode = action.payload.country_code ?? null;
        state.decidedAt = action.payload.decided_at ?? null;
        state.decisionComment = action.payload.decision_comment ?? null;
      })
      .addCase(fetchOnboardingStatus.rejected, (state, action) => {
        state.loading = false;
        state.error = action.payload;
      })
      // Start over: fresh application, local answers wiped.
      .addCase(discardDraft.fulfilled, (state, action) => {
        state.status = action.payload.status;
        state.steps = action.payload.steps;
        state.currentStep = action.payload.current_step;
        state.userType = null;
        state.subcategory = null;
        state.countryCode = null;
        state.questionGroups = [];
        state.answers = {};
        state.kycDocStatus = {};
        state.error = null;
      })
      .addCase(discardDraft.rejected, (state, action) => {
        state.error = action.payload;
      })
      // Reopen (rejected → editable again); payload has the same shape as
      // the status endpoint.
      .addCase(reopenOnboarding.fulfilled, (state, action) => {
        state.status = action.payload.status;
        state.steps = action.payload.steps;
        state.currentStep = action.payload.current_step;
        state.decidedAt = null;
        state.decisionComment = null;
        state.error = null;
      })
      .addCase(reopenOnboarding.rejected, (state, action) => {
        state.error = action.payload;
      })
      // Fetch User Types
      .addCase(fetchUserTypes.fulfilled, (state, action) => {
        state.userTypes = action.payload;
      })
      // Select User Type
      .addCase(selectUserType.fulfilled, (state, action) => {
        state.status = action.payload.status;
      })
      // Fetch Questions
      .addCase(fetchQuestions.pending, (state) => {
        state.loading = true;
      })
      .addCase(fetchQuestions.fulfilled, (state, action) => {
        state.loading = false;
        state.questionGroups = action.payload;
        // Populate existing answers (parse JSON strings for multi_select and table)
        action.payload.forEach((group) => {
          group.questions.forEach((q) => {
            if (q.answer !== null && q.answer !== undefined) {
              if ((q.type === 'multi_select' || q.type === 'table' || q.type === 'ubo') && typeof q.answer === 'string') {
                try {
                  const parsed = JSON.parse(q.answer);
                  state.answers[q.id] = Array.isArray(parsed) ? parsed : q.answer;
                } catch {
                  state.answers[q.id] = q.answer;
                }
              } else if (q.type === 'address' && typeof q.answer === 'string') {
                try {
                  state.answers[q.id] = JSON.parse(q.answer);
                } catch {
                  state.answers[q.id] = q.answer;
                }
              } else {
                state.answers[q.id] = q.answer;
              }
            }
          });
        });
      })
      .addCase(fetchQuestions.rejected, (state, action) => {
        state.loading = false;
        state.error = action.payload;
      })
      // Submit Answers
      .addCase(submitAnswers.pending, (state) => {
        state.loading = true;
      })
      .addCase(submitAnswers.fulfilled, (state) => {
        state.loading = false;
      })
      .addCase(submitAnswers.rejected, (state, action) => {
        state.loading = false;
        // payload is { message, documentValidation } — keep the store's error
        // a plain string; QuestionsStep reads the structured part itself.
        state.error = action.payload?.message || 'Failed to save answers';
      })
      // Complete Step
      .addCase(completeOnboardingStep.fulfilled, (state, action) => {
        state.status = action.payload.status;
        state.steps = action.payload.steps;
        state.currentStep = action.payload.current_step;
      })
      // Previous Step
      .addCase(goToPreviousStep.fulfilled, (state, action) => {
        state.status = action.payload.status;
        state.steps = action.payload.steps;
        state.currentStep = action.payload.current_step;
      })
      // Jump to an earlier step
      .addCase(goToOnboardingStep.fulfilled, (state, action) => {
        state.status = action.payload.status;
        state.steps = action.payload.steps;
        state.currentStep = action.payload.current_step;
      })
      // Clear onboarding state on logout (sync clearAuth or async logoutUser.fulfilled)
      .addCase(clearAuth, (state) => {
        state.status = null;
        state.steps = [];
        state.currentStep = null;
        state.userType = null;
        state.subcategory = null;
        state.onboardingId = null;
        state.startedAt = null;
        state.userTypes = [];
        state.questionGroups = [];
        state.answers = {};
        state.kycDocStatus = {};
        state.loading = false;
        state.error = null;
      })
      .addCase(logoutUser.fulfilled, (state) => {
        state.status = null;
        state.steps = [];
        state.currentStep = null;
        state.userType = null;
        state.subcategory = null;
        state.onboardingId = null;
        state.startedAt = null;
        state.userTypes = [];
        state.questionGroups = [];
        state.answers = {};
        state.kycDocStatus = {};
        state.loading = false;
        state.error = null;
      });
  },
});

export const { setAnswer, setKycDocStatus, clearOnboarding } = onboardingSlice.actions;
export default onboardingSlice.reducer;
