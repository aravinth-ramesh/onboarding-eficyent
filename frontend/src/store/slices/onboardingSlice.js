import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';
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
  async (answers, { rejectWithValue }) => {
    try {
      const response = await onboardingApi.saveAnswers(answers);
      return response.data;
    } catch (error) {
      return rejectWithValue(error.response?.data?.message || 'Failed to save answers');
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

const onboardingSlice = createSlice({
  name: 'onboarding',
  initialState: {
    status: null,
    steps: [],
    currentStep: null,
    userTypes: [],
    questionGroups: [],
    answers: {},
    loading: false,
    error: null,
  },
  reducers: {
    setAnswer: (state, action) => {
      const { questionId, value } = action.payload;
      state.answers[questionId] = value;
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
      })
      .addCase(fetchOnboardingStatus.rejected, (state, action) => {
        state.loading = false;
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
        // Populate existing answers
        action.payload.forEach((group) => {
          group.questions.forEach((q) => {
            if (q.answer !== null) {
              state.answers[q.id] = q.answer;
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
        state.error = action.payload;
      })
      // Complete Step
      .addCase(completeOnboardingStep.fulfilled, (state, action) => {
        state.status = action.payload.status;
        state.steps = action.payload.steps;
        state.currentStep = action.payload.current_step;
      });
  },
});

export const { setAnswer, clearOnboarding } = onboardingSlice.actions;
export default onboardingSlice.reducer;
