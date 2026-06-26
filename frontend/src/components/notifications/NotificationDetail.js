import React, { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import {
  fetchNotificationDetail,
  resolveNotification,
  resolveNotificationWithFile,
  clearSelectedNotification,
  fetchUnreadCount,
  fetchNotifications,
} from '../../store/slices/notificationSlice';
import QuestionField from '../onboarding/QuestionField';
import FileUploadField from '../onboarding/FileUploadField';

function NotificationDetail({ notificationId, onClose }) {
  const dispatch = useDispatch();
  const { selectedNotification, detailLoading, resolving, error } = useSelector(
    (state) => state.notifications
  );
  const [answer, setAnswer] = useState('');
  const [files, setFiles] = useState([]);
  const [submitSuccess, setSubmitSuccess] = useState(false);

  useEffect(() => {
    dispatch(fetchNotificationDetail(notificationId));
    return () => {
      dispatch(clearSelectedNotification());
    };
  }, [dispatch, notificationId]);

  const handleFieldChange = (questionId, value) => {
    setAnswer(value);
  };

  const handleFileChange = (questionId, value) => {
    // value is the File array from FileUploadField
    if (Array.isArray(value)) {
      setFiles(value);
    } else {
      setAnswer(value);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!selectedNotification) return;

    const question = selectedNotification.question;
    const isFileType = question?.type === 'file';

    try {
      if (isFileType && files.length > 0) {
        const formData = new FormData();
        files.forEach((file) => formData.append('files[]', file));
        await dispatch(resolveNotificationWithFile({ id: notificationId, formData })).unwrap();
      } else {
        await dispatch(resolveNotification({ id: notificationId, value: answer })).unwrap();
      }
      setSubmitSuccess(true);
      dispatch(fetchUnreadCount());
      dispatch(fetchNotifications());
    } catch {
      // Error is in Redux state
    }
  };

  const handleClose = () => {
    onClose();
  };

  const formatOldAnswer = (notification) => {
    if (!notification.old_answer) return null;
    const question = notification.question;
    const val = notification.old_answer;

    if (question.type === 'multi_select') {
      try {
        const arr = typeof val === 'string' ? JSON.parse(val) : val;
        const labels = arr.map((v) => {
          const opt = (question.options || []).find((o) => o.value === v);
          return opt ? opt.label : v;
        });
        return labels.join(', ');
      } catch {
        return val;
      }
    }
    if (['radio', 'select'].includes(question.type)) {
      const opt = (question.options || []).find((o) => o.value === val);
      return opt ? opt.label : val;
    }
    if (question.type === 'file' && notification.files && notification.files.length > 0) {
      return notification.files.map((f) => f.original_filename).join(', ');
    }
    if (question.type === 'table') {
      try {
        const rows = typeof val === 'string' ? JSON.parse(val) : val;
        if (Array.isArray(rows)) {
          const columns = (question.options && question.options.columns) || [];
          if (columns.length === 0) return `${rows.length} row(s)`;
          return (
            <div className="table-field-readonly" style={{ marginTop: 4 }}>
              <table className="table-field-table readonly">
                <thead>
                  <tr>
                    <th className="table-field-row-num">#</th>
                    {columns.map((col) => <th key={col.key}>{col.label}</th>)}
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row, i) => (
                    <tr key={i}>
                      <td className="table-field-row-num">{i + 1}</td>
                      {columns.map((col) => {
                        const cellVal = row[col.key] || '';
                        if (col.type === 'select' && col.options) {
                          const opt = col.options.find((o) => o.value === cellVal);
                          return <td key={col.key}>{opt ? opt.label : cellVal || '\u2014'}</td>;
                        }
                        return <td key={col.key}>{cellVal || '\u2014'}</td>;
                      })}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          );
        }
      } catch { /* fall through */ }
      return val;
    }
    return val;
  };

  if (detailLoading) {
    return (
      <div className="modal-overlay" onClick={handleClose}>
        <div className="notification-detail-dialog" onClick={(e) => e.stopPropagation()}>
          <div className="spinner-corporate" style={{ padding: '3rem' }}>
            <div className="spinner-border" role="status" />
            <p>Loading...</p>
          </div>
        </div>
      </div>
    );
  }

  if (!selectedNotification) return null;

  const notification = selectedNotification;
  const question = notification.question;
  const isResolved = notification.status === 'resolved';
  const isChangeRequest = notification.type === 'change_request';
  const isFileType = question?.type === 'file';

  return (
    <div className="modal-overlay" onClick={handleClose}>
      <div className="notification-detail-dialog" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h5>
            {isChangeRequest ? 'Change Requested' : 'New Question'}
          </h5>
          <button className="modal-close" onClick={handleClose}>
            {'\u2715'}
          </button>
        </div>

        <div className="notification-detail-body">
          {/* Admin message */}
          <div className="notification-detail-message">
            <div className="notification-detail-message-label">Admin Message</div>
            <div className="notification-detail-message-text">{notification.message}</div>
          </div>

          {/* Question info */}
          {question && (
            <div className="notification-detail-question">
              <div className="notification-detail-question-label">{question.label}</div>
              {question.description && (
                <div className="notification-detail-question-desc">{question.description}</div>
              )}
              {question.help_text && (
                <div className="notification-detail-question-help">{question.help_text}</div>
              )}
            </div>
          )}

          {/* Old answer (for change requests) */}
          {isChangeRequest && notification.old_answer !== undefined && (
            <div className="notification-detail-old-answer">
              <div className="notification-detail-old-answer-label">Your Previous Answer</div>
              <div className="notification-detail-old-answer-value">
                {formatOldAnswer(notification) || '\u2014'}
              </div>
            </div>
          )}

          {/* Success message */}
          {submitSuccess && (
            <div className="alert-corporate success" style={{ marginTop: 16, marginBottom: 8 }}>
              Your response has been submitted successfully!
            </div>
          )}

          {/* Error message */}
          {error && (
            <div className="alert-corporate danger" style={{ marginTop: 16, marginBottom: 8 }}>
              {error}
            </div>
          )}

          {/* Answer form */}
          {!isResolved && !submitSuccess && question && (
            <form onSubmit={handleSubmit} className="notification-detail-form">
              <div className="notification-detail-form-label">
                {isChangeRequest ? 'Updated Answer' : 'Your Answer'}
              </div>
              {isFileType ? (
                <div className="notification-detail-file-upload">
                  <FileUploadField
                    question={question}
                    value={files}
                    onChange={handleFileChange}
                  />
                </div>
              ) : (
                <QuestionField
                  question={question}
                  value={answer}
                  onChange={handleFieldChange}
                />
              )}
              <div className="notification-detail-form-actions">
                <button
                  type="button"
                  className="btn-secondary-custom"
                  onClick={handleClose}
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="btn-primary-custom"
                  disabled={resolving || (!answer && files.length === 0)}
                >
                  {resolving ? 'Submitting...' : 'Submit Response'}
                </button>
              </div>
            </form>
          )}

          {/* Already resolved */}
          {isResolved && !submitSuccess && (
            <div className="notification-detail-resolved">
              <div className="notification-detail-resolved-badge">Resolved</div>
              <p>You have already submitted your response for this notification.</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

export default NotificationDetail;
