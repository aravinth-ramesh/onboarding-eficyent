import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';
import * as authApi from '../../api/auth';

export const fetchUser = createAsyncThunk('auth/fetchUser', async (_, { rejectWithValue }) => {
  try {
    const response = await authApi.getMe();
    return response.data.data.user;
  } catch (error) {
    return rejectWithValue(error.response?.data?.message || 'Failed to fetch user');
  }
});

export const sendOtp = createAsyncThunk('auth/sendOtp', async (email, { rejectWithValue }) => {
  try {
    const response = await authApi.sendOtp(email);
    return response.data;
  } catch (error) {
    return rejectWithValue(error.response?.data?.message || 'Failed to send OTP');
  }
});

export const verifyOtp = createAsyncThunk('auth/verifyOtp', async ({ email, code }, { rejectWithValue }) => {
  try {
    const response = await authApi.verifyOtp(email, code);
    const { user, token } = response.data.data;
    localStorage.setItem('auth_token', token);
    return user;
  } catch (error) {
    return rejectWithValue(error.response?.data?.message || 'Invalid verification code');
  }
});

export const logoutUser = createAsyncThunk('auth/logout', async () => {
  try {
    await authApi.logout();
  } catch {
    // Server-side logout may fail (token expired, network error, etc.)
    // Proceed with client-side cleanup regardless
  }
  localStorage.removeItem('auth_token');
});

const authSlice = createSlice({
  name: 'auth',
  initialState: {
    user: null,
    isAuthenticated: false,
    loading: false,
    otpSent: false,
    error: null,
  },
  reducers: {
    clearError: (state) => {
      state.error = null;
    },
    resetOtpState: (state) => {
      state.otpSent = false;
      state.error = null;
    },
  },
  extraReducers: (builder) => {
    builder
      // Fetch User
      .addCase(fetchUser.pending, (state) => {
        state.loading = true;
      })
      .addCase(fetchUser.fulfilled, (state, action) => {
        state.loading = false;
        state.user = action.payload;
        state.isAuthenticated = true;
      })
      .addCase(fetchUser.rejected, (state) => {
        state.loading = false;
        state.isAuthenticated = false;
        state.user = null;
      })
      // Send OTP
      .addCase(sendOtp.pending, (state) => {
        state.loading = true;
        state.error = null;
      })
      .addCase(sendOtp.fulfilled, (state) => {
        state.loading = false;
        state.otpSent = true;
      })
      .addCase(sendOtp.rejected, (state, action) => {
        state.loading = false;
        state.error = action.payload;
      })
      // Verify OTP
      .addCase(verifyOtp.pending, (state) => {
        state.loading = true;
        state.error = null;
      })
      .addCase(verifyOtp.fulfilled, (state, action) => {
        state.loading = false;
        state.user = action.payload;
        state.isAuthenticated = true;
        state.otpSent = false;
      })
      .addCase(verifyOtp.rejected, (state, action) => {
        state.loading = false;
        state.error = action.payload;
      })
      // Logout
      .addCase(logoutUser.fulfilled, (state) => {
        state.user = null;
        state.isAuthenticated = false;
        state.otpSent = false;
      });
  },
});

export const { clearError, resetOtpState } = authSlice.actions;
export default authSlice.reducer;
