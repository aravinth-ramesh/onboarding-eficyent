import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';
import { logoutUser, clearAuth } from './authSlice';
import * as notificationsApi from '../../api/notifications';

export const fetchNotifications = createAsyncThunk(
  'notifications/fetchAll',
  async (page = 1, { rejectWithValue }) => {
    try {
      const response = await notificationsApi.getNotifications(page);
      return response.data;
    } catch (error) {
      return rejectWithValue(error.response?.data?.message || 'Failed to fetch notifications');
    }
  }
);

export const fetchUnreadCount = createAsyncThunk(
  'notifications/fetchUnreadCount',
  async (_, { rejectWithValue }) => {
    try {
      const response = await notificationsApi.getUnreadCount();
      return response.data.count;
    } catch (error) {
      return rejectWithValue(error.response?.data?.message || 'Failed to fetch count');
    }
  }
);

export const fetchNotificationDetail = createAsyncThunk(
  'notifications/fetchDetail',
  async (id, { rejectWithValue }) => {
    try {
      const response = await notificationsApi.getNotification(id);
      return response.data.data;
    } catch (error) {
      return rejectWithValue(error.response?.data?.message || 'Failed to fetch notification');
    }
  }
);

export const markNotificationAsRead = createAsyncThunk(
  'notifications/markAsRead',
  async (id, { rejectWithValue }) => {
    try {
      await notificationsApi.markAsRead(id);
      return id;
    } catch (error) {
      return rejectWithValue(error.response?.data?.message || 'Failed to mark as read');
    }
  }
);

export const resolveNotification = createAsyncThunk(
  'notifications/resolve',
  async ({ id, value }, { rejectWithValue }) => {
    try {
      await notificationsApi.resolveNotification(id, value);
      return id;
    } catch (error) {
      return rejectWithValue(error.response?.data?.message || 'Failed to submit response');
    }
  }
);

export const resolveNotificationWithFile = createAsyncThunk(
  'notifications/resolveWithFile',
  async ({ id, formData }, { rejectWithValue }) => {
    try {
      await notificationsApi.resolveNotificationWithFile(id, formData);
      return id;
    } catch (error) {
      return rejectWithValue(error.response?.data?.message || 'Failed to upload file');
    }
  }
);

const initialState = {
  notifications: [],
  unreadCount: 0,
  selectedNotification: null,
  currentPage: 1,
  lastPage: 1,
  loading: false,
  detailLoading: false,
  resolving: false,
  error: null,
};

const notificationSlice = createSlice({
  name: 'notifications',
  initialState,
  reducers: {
    clearSelectedNotification: (state) => {
      state.selectedNotification = null;
    },
    clearNotificationError: (state) => {
      state.error = null;
    },
  },
  extraReducers: (builder) => {
    builder
      // Fetch notifications
      .addCase(fetchNotifications.pending, (state) => {
        state.loading = true;
      })
      .addCase(fetchNotifications.fulfilled, (state, action) => {
        state.loading = false;
        state.notifications = action.payload.data;
        state.currentPage = action.payload.current_page;
        state.lastPage = action.payload.last_page;
      })
      .addCase(fetchNotifications.rejected, (state, action) => {
        state.loading = false;
        state.error = action.payload;
      })
      // Unread count
      .addCase(fetchUnreadCount.fulfilled, (state, action) => {
        state.unreadCount = action.payload;
      })
      // Notification detail
      .addCase(fetchNotificationDetail.pending, (state) => {
        state.detailLoading = true;
      })
      .addCase(fetchNotificationDetail.fulfilled, (state, action) => {
        state.detailLoading = false;
        state.selectedNotification = action.payload;
      })
      .addCase(fetchNotificationDetail.rejected, (state, action) => {
        state.detailLoading = false;
        state.error = action.payload;
      })
      // Mark as read
      .addCase(markNotificationAsRead.fulfilled, (state, action) => {
        const id = action.payload;
        const notif = state.notifications.find((n) => n.id === id);
        if (notif && !notif.read_at) {
          notif.read_at = new Date().toISOString();
          state.unreadCount = Math.max(0, state.unreadCount - 1);
        }
      })
      // Resolve
      .addCase(resolveNotification.pending, (state) => {
        state.resolving = true;
        state.error = null;
      })
      .addCase(resolveNotification.fulfilled, (state, action) => {
        state.resolving = false;
        const id = action.payload;
        const notif = state.notifications.find((n) => n.id === id);
        if (notif) {
          notif.status = 'resolved';
          notif.resolved_at = new Date().toISOString();
        }
        if (state.selectedNotification && state.selectedNotification.id === id) {
          state.selectedNotification.status = 'resolved';
          state.selectedNotification.resolved_at = new Date().toISOString();
        }
      })
      .addCase(resolveNotification.rejected, (state, action) => {
        state.resolving = false;
        state.error = action.payload;
      })
      // Resolve with file
      .addCase(resolveNotificationWithFile.pending, (state) => {
        state.resolving = true;
        state.error = null;
      })
      .addCase(resolveNotificationWithFile.fulfilled, (state, action) => {
        state.resolving = false;
        const id = action.payload;
        const notif = state.notifications.find((n) => n.id === id);
        if (notif) {
          notif.status = 'resolved';
          notif.resolved_at = new Date().toISOString();
        }
        if (state.selectedNotification && state.selectedNotification.id === id) {
          state.selectedNotification.status = 'resolved';
          state.selectedNotification.resolved_at = new Date().toISOString();
        }
      })
      .addCase(resolveNotificationWithFile.rejected, (state, action) => {
        state.resolving = false;
        state.error = action.payload;
      })
      // Clear on logout
      .addCase(clearAuth, () => initialState)
      .addCase(logoutUser.fulfilled, () => initialState);
  },
});

export const { clearSelectedNotification, clearNotificationError } = notificationSlice.actions;
export default notificationSlice.reducer;
