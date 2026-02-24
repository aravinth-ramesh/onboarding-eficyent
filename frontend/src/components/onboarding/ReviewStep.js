import React, { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import {
  fetchQuestions,
  completeOnboardingStep,
  fetchOnboardingStatus,
} from '../../store/slices/onboardingSlice';
import appConfig from '../../appConfig';

function ReviewStep({ step, onBack, isFirstStep }) {
  const dispatch = useDispatch();
  const { questionGroups, answers, loading } = useSelector((state) => state.onboarding);
  const [submitting, setSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);

  useEffect(() => {
    if (questionGroups.length === 0) {
      dispatch(fetchQuestions());
    }
  }, [dispatch, questionGroups.length]);

  const formatAnswer = (question, value) => {
    if (!value) return '\u2014';

    // File-type questions: show file links from question.files
    if (question.type === 'file' && question.files && question.files.length > 0) {
      return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
          {question.files.map((file) => (
            <a
              key={file.id}
              href={file.url}
              target="_blank"
              rel="noopener noreferrer"
              className="kyc-file-link"
              style={{ fontSize: '0.85rem' }}
            >
              {'\u{1F4CE}'} {file.original_filename}
            </a>
          ))}
        </div>
      );
    }

    // File marker from local selection (not yet uploaded)
    if (question.type === 'file') {
      return '\u2014';
    }

    if (question.type === 'multi_select') {
      try {
        const arr = typeof value === 'string' ? JSON.parse(value) : value;
        const labels = arr.map((v) => {
          const opt = (question.options || []).find((o) => o.value === v);
          return opt ? opt.label : v;
        });
        return labels.join(', ');
      } catch {
        return value;
      }
    }

    if (['radio', 'select'].includes(question.type)) {
      const opt = (question.options || []).find((o) => o.value === value);
      return opt ? opt.label : value;
    }

    return value;
  };

  const handleSubmit = async () => {
    setSubmitting(true);
    await dispatch(completeOnboardingStep(step.id));
    dispatch(fetchOnboardingStatus());
    setSubmitting(false);
    setSubmitted(true);
  };

  if (loading && questionGroups.length === 0) {
    return (
      <div className="spinner-corporate">
        <div className="spinner-border" role="status" />
        <p>Loading review...</p>
      </div>
    );
  }

  if (submitted) {
    return (
      <div className="ob-card">
        <div className="ob-card-body">
          <div className="completion-screen">
            <div className="completion-icon">{'\u2713'}</div>
            <h2>{appConfig.onboardingComplete.heading}</h2>
            <p>{appConfig.onboardingComplete.message}</p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="ob-card">
      <div className="ob-card-header">
        <h5>Review Your Information</h5>
      </div>
      <div className="ob-card-body">
        <div className="alert-corporate info" style={{ marginBottom: 20 }}>
          Please review all your answers before submitting. Use the Back button to make changes.
        </div>

        {questionGroups.map((group) => (
          <div key={group.id} style={{ marginBottom: 24 }}>
            <p className="section-label">{group.name}</p>
            <table className="review-table">
              <tbody>
                {group.questions.map((question) => (
                  <tr key={question.id}>
                    <td className="review-label">{question.label}</td>
                    <td className="review-value">{formatAnswer(question, answers[question.id])}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ))}
      </div>
      <div className="ob-card-footer">
        {!isFirstStep ? (
          <button className="btn-secondary-custom" onClick={onBack}>
            &#8592; Back
          </button>
        ) : <div />}
        <button className="btn-success-custom" onClick={handleSubmit} disabled={submitting}>
          {submitting ? 'Submitting...' : '\u2713 Submit Application'}
        </button>
      </div>
    </div>
  );
}

export default ReviewStep;
