import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
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
import TableAnswerView from '../onboarding/TableAnswerView';
import FileUploadField from '../onboarding/FileUploadField';

/**
 * Never hand React a raw object. A table file cell is stored as one, and an
 * unrenderable child throws "Objects are not valid as a React child" — with no
 * error boundary above this modal that tears the tree down, leaving the spinner
 * up forever (report item 13).
 */
const renderable = (val) =>
  val === null || val === undefined
    ? ''
    : typeof val === 'string' || typeof val === 'number' || React.isValidElement(val)
      ? val
      : JSON.stringify(val);


/**
 * Split a table answer into the JSON-safe value and the uploads that have to
 * travel as multipart. A cell holding a freshly picked File is left untouched
 * in the value so the server's existing reference survives, and the File is
 * returned separately to be merged in after the value is written.
 */
const extractTableFiles = (answer) => {
  let rows = answer;
  if (typeof rows === 'string') {
    try { rows = JSON.parse(rows); } catch { return { value: answer, tableFileAnswers: [] }; }
  }
  if (!Array.isArray(rows)) return { value: answer, tableFileAnswers: [] };

  const tableFileAnswers = [];
  const cleaned = rows.map((row, rowIndex) => {
    if (!row || typeof row !== 'object') return row;
    const next = { ...row };
    Object.entries(row).forEach(([columnKey, cell]) => {
      if (typeof File !== 'undefined' && cell instanceof File) {
        tableFileAnswers.push({ rowIndex, columnKey, file: cell });
        delete next[columnKey];
      }
    });
    return next;
  });

  return { value: JSON.stringify(cleaned), tableFileAnswers };
};

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

  // Prefill the editor with the previous answer so the client edits only what
  // was flagged instead of re-entering everything — and, for multi-row tables,
  // so untouched rows aren't wiped when the answer is saved (EOP-72). Files are
  // re-uploaded, so they are not prefilled.
  useEffect(() => {
    if (!selectedNotification) return;
    if (selectedNotification.type !== 'change_request') return;
    if (selectedNotification.question?.type === 'file') return;
    const prev = selectedNotification.old_answer;
    setAnswer(prev == null ? '' : prev);
  }, [selectedNotification]);

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
        // Lift any File out of the table rows before the value is serialised:
        // JSON.stringify turns a File into {}, which silently dropped the
        // document and left the cell holding an empty object that crashed the
        // modal on every later change request (report item 13).
        const { value, tableFileAnswers } = extractTableFiles(answer);
        await dispatch(resolveNotification({ id: notificationId, value, tableFileAnswers })).unwrap();
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
        return renderable(val);
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
          // Rendered by the shared component rather than a local copy. The copy
          // dropped each cell straight into JSX, and a file cell is stored as an
          // object -- React 19 throws "Objects are not valid as a React child",
          // and with no error boundary the tree is torn down, so the modal never
          // got past its spinner and the section could not be updated at all
          // (report item 13). TableAnswerView already renders a file cell as a
          // link, and is what the review and submitted-answer screens use.
          return <TableAnswerView question={question} value={rows} />;
        }
      } catch { /* fall through */ }
      // fall through to the safe rendering below
    }

    return renderable(val);
  };

  // Portal to <body>: the bell (and this modal) live inside the topbar, whose
  // backdrop-filter makes it the containing block for position:fixed — the
  // overlay would otherwise be sized to the topbar and clip the dialog.
  if (detailLoading) {
    return createPortal(
      <div className="modal-overlay" onClick={handleClose}>
        <div className="notification-detail-dialog" onClick={(e) => e.stopPropagation()}>
          <div className="spinner-corporate" style={{ padding: '3rem' }}>
            <div className="spinner-border" role="status" />
            <p>Loading...</p>
          </div>
        </div>
      </div>,
      document.body
    );
  }

  if (!selectedNotification) return null;

  const notification = selectedNotification;
  const question = notification.question;
  const isResolved = notification.status === 'resolved';
  const isChangeRequest = notification.type === 'change_request';
  const isFileType = question?.type === 'file';

  return createPortal(
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
    </div>,
    document.body
  );
}

export default NotificationDetail;
