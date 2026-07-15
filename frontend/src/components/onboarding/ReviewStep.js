import React, { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import {
  fetchQuestions,
  completeOnboardingStep,
  fetchOnboardingStatus,
  goToOnboardingStep,
} from '../../store/slices/onboardingSlice';
import appConfig from '../../appConfig';
import TableAnswerView from './TableAnswerView';
import formatAnswerDisplay from './formatAnswerDisplay';

function ReviewStep({ step, onBack, isFirstStep }) {
  const dispatch = useDispatch();
  const { questionGroups, answers, loading, steps } = useSelector((state) => state.onboarding);
  const user = useSelector((state) => state.auth.user);

  // Find the section step that renders a given group, so Review can jump there.
  const stepForGroup = (slug) =>
    steps.find((s) => Array.isArray(s.config?.groups) && s.config.groups.includes(slug));

  const handleEditSection = (targetStep) => {
    dispatch(goToOnboardingStep(targetStep.id)).then((result) => {
      if (!result.error) dispatch(fetchOnboardingStatus());
    });
  };
  const [submitting, setSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [confirming, setConfirming] = useState(false);

  // Always refetch on mount so the review reflects the latest server state,
  // including files uploaded during the questions step. The questionGroups
  // already in Redux are from the initial fetch (before any upload), so
  // question.files would be stale and the uploaded document links would
  // render as a hyphen until a manual page refresh.
  useEffect(() => {
    dispatch(fetchQuestions());
  }, [dispatch]);

  const formatAnswer = formatAnswerDisplay;

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

  // Final confirmation: verify the Name and Position before submitting.
  if (confirming) {
    return (
      <div className="ob-card">
        <div className="ob-card-header">
          <h5>Confirm Your Details</h5>
        </div>
        <div className="ob-card-body">
          <p style={{ marginBottom: 16 }}>
            Please verify the details below before submitting your application.
          </p>
          <table className="review-table">
            <tbody>
              <tr>
                <td className="review-label">Name</td>
                <td className="review-value">{user?.name || '\u2014'}</td>
              </tr>
              <tr>
                <td className="review-label">Position / Designation</td>
                <td className="review-value">{user?.position || '\u2014'}</td>
              </tr>
            </tbody>
          </table>
          <div className="alert-corporate warning" style={{ marginTop: 16 }}>
            Note: The Name and Position cannot be updated after submission. Please ensure
            the information is correct before proceeding.
          </div>
        </div>
        <div className="ob-card-footer">
          <button
            className="btn-secondary-custom"
            onClick={() => setConfirming(false)}
            disabled={submitting}
          >
            &#8592; Back
          </button>
          <button className="btn-success-custom" onClick={handleSubmit} disabled={submitting}>
            {submitting ? 'Submitting...' : '\u2713 Confirm & Submit'}
          </button>
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

        {questionGroups.map((group) => {
          const editStep = stepForGroup(group.slug);
          return (
          <div key={group.id} style={{ marginBottom: 24 }}>
            <div className="review-section-head">
              <p className="section-label" style={{ margin: 0 }}>{group.name}</p>
              {editStep && (
                <button type="button" className="review-edit-btn" onClick={() => handleEditSection(editStep)}>
                  ✎ Edit
                </button>
              )}
            </div>
            <table className="review-table">
              <tbody>
                {group.questions.map((question) => {
                  if (question.type === 'table') {
                    return (
                      <tr key={question.id} className="review-table-row-fullwidth">
                        <td colSpan={2} className="review-table-fullwidth">
                          <div className="review-table-block-label">{question.label}</div>
                          <TableAnswerView question={question} value={answers[question.id]} />
                        </td>
                      </tr>
                    );
                  }
                  return (
                    <tr key={question.id}>
                      <td className="review-label">{question.label}</td>
                      <td className="review-value">{formatAnswer(question, answers[question.id])}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          );
        })}
      </div>
      <div className="ob-card-footer">
        {!isFirstStep ? (
          <button className="btn-secondary-custom" onClick={onBack}>
            &#8592; Back
          </button>
        ) : <div />}
        <button
          className="btn-success-custom"
          onClick={() => setConfirming(true)}
          disabled={submitting}
        >
          {'\u2713 Submit Application'}
        </button>
      </div>
    </div>
  );
}

export default ReviewStep;
