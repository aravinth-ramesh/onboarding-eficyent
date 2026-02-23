import React, { useEffect, useState, useMemo } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import {
  fetchQuestions,
  submitAnswers,
  setAnswer,
  completeOnboardingStep,
  fetchOnboardingStatus,
} from '../../store/slices/onboardingSlice';
import { evaluateConditionalRules } from '../../utils/conditionalEngine';
import QuestionField from './QuestionField';

function QuestionsStep({ step, onBack, isFirstStep }) {
  const dispatch = useDispatch();
  const { questionGroups, answers, loading } = useSelector((state) => state.onboarding);
  const [activeGroupIndex, setActiveGroupIndex] = useState(0);
  const [validationErrors, setValidationErrors] = useState({});
  const [submitError, setSubmitError] = useState(null);

  useEffect(() => {
    dispatch(fetchQuestions());
  }, [dispatch]);

  const isQuestionVisible = (question) => {
    if (!question.conditional_rules || question.conditional_rules.length === 0) {
      return true;
    }
    return evaluateConditionalRules(question.conditional_rules, answers);
  };

  // Filter out groups that have no visible questions
  const visibleGroups = useMemo(() => {
    return questionGroups.filter((group) =>
      group.questions.some((q) => isQuestionVisible(q))
    );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [questionGroups, answers]);

  const activeGroup = visibleGroups[activeGroupIndex];
  const activeQuestions = activeGroup
    ? activeGroup.questions.filter(isQuestionVisible)
    : [];
  const isLastGroup = activeGroupIndex === visibleGroups.length - 1;
  const isFirstGroup = activeGroupIndex === 0;

  const handleAnswerChange = (questionId, value) => {
    dispatch(setAnswer({ questionId, value }));
    if (validationErrors[questionId]) {
      setValidationErrors((prev) => {
        const next = { ...prev };
        delete next[questionId];
        return next;
      });
    }
  };

  const validateCurrentGroup = () => {
    const errors = {};
    activeQuestions.forEach((question) => {
      if (question.is_required) {
        const val = answers[question.id];
        if (val === undefined || val === null || val === '' || (Array.isArray(val) && val.length === 0)) {
          errors[question.id] = 'This field is required.';
        }
      }
    });
    return errors;
  };

  const validateAllGroups = () => {
    const errors = {};
    visibleGroups.forEach((group) => {
      group.questions.forEach((question) => {
        if (!isQuestionVisible(question)) return;
        if (question.is_required) {
          const val = answers[question.id];
          if (val === undefined || val === null || val === '' || (Array.isArray(val) && val.length === 0)) {
            errors[question.id] = 'This field is required.';
          }
        }
      });
    });
    return errors;
  };

  const handleSave = async () => {
    setSubmitError(null);
    const answersPayload = Object.entries(answers).map(([questionId, value]) => ({
      question_id: parseInt(questionId),
      value,
    }));

    const result = await dispatch(submitAnswers(answersPayload));
    if (!result.error) return true;
    setSubmitError('Failed to save answers. Please try again.');
    return false;
  };

  const handleNextGroup = async () => {
    const errors = validateCurrentGroup();
    if (Object.keys(errors).length > 0) {
      setValidationErrors(errors);
      return;
    }

    // Auto-save on group navigation
    await handleSave();
    setActiveGroupIndex((prev) => prev + 1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const handlePrevGroup = () => {
    if (isFirstGroup) {
      onBack();
    } else {
      setActiveGroupIndex((prev) => prev - 1);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  };

  const handleSubmitAll = async () => {
    // Validate current group first
    const currentErrors = validateCurrentGroup();
    if (Object.keys(currentErrors).length > 0) {
      setValidationErrors(currentErrors);
      return;
    }

    // Validate all groups
    const allErrors = validateAllGroups();
    if (Object.keys(allErrors).length > 0) {
      // Find the first group with errors and navigate to it
      for (let i = 0; i < visibleGroups.length; i++) {
        const groupHasError = visibleGroups[i].questions.some(
          (q) => isQuestionVisible(q) && allErrors[q.id]
        );
        if (groupHasError) {
          setActiveGroupIndex(i);
          setValidationErrors(allErrors);
          return;
        }
      }
    }

    const saved = await handleSave();
    if (saved) {
      await dispatch(completeOnboardingStep(step.id));
      dispatch(fetchOnboardingStatus());
    }
  };

  const handleGroupClick = (index) => {
    // Allow navigating to any previously visited group or current
    if (index <= activeGroupIndex) {
      setActiveGroupIndex(index);
    }
  };

  if (loading && questionGroups.length === 0) {
    return (
      <div className="spinner-corporate">
        <div className="spinner-border" role="status" />
        <p>Loading questions...</p>
      </div>
    );
  }

  return (
    <div className="ob-card">
      <div className="ob-card-header">
        <h5>{activeGroup ? activeGroup.name : 'Onboarding Questions'}</h5>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <span className="group-counter">
            {activeGroupIndex + 1} of {visibleGroups.length}
          </span>
          <button className="btn-outline-custom" onClick={handleSave} disabled={loading}>
            {loading ? 'Saving...' : 'Save Draft'}
          </button>
        </div>
      </div>

      {/* Group Stepper */}
      {visibleGroups.length > 1 && (
        <div className="group-stepper">
          {visibleGroups.map((group, index) => {
            let status = 'pending';
            if (index < activeGroupIndex) status = 'completed';
            if (index === activeGroupIndex) status = 'active';

            return (
              <div
                key={group.id}
                className={`group-stepper-item ${status}`}
                onClick={() => handleGroupClick(index)}
                title={group.name}
              >
                <div className="group-stepper-dot">
                  {status === 'completed' ? '\u2713' : index + 1}
                </div>
                <span className="group-stepper-label">{group.name}</span>
              </div>
            );
          })}
          <div className="group-stepper-progress">
            <div
              className="group-stepper-progress-fill"
              style={{ width: `${(activeGroupIndex / Math.max(visibleGroups.length - 1, 1)) * 100}%` }}
            />
          </div>
        </div>
      )}

      <div className="ob-card-body">
        {submitError && (
          <div className="alert-corporate danger" style={{ marginBottom: 16 }}>{submitError}</div>
        )}

        {activeGroup && activeGroup.description && (
          <p style={{ color: 'var(--color-text-muted)', fontSize: '0.8rem', marginBottom: 20 }}>
            {activeGroup.description}
          </p>
        )}

        {activeQuestions.map((question) => (
          <div key={question.id} className="question-field">
            <label className="question-label">
              {question.label}
              {question.is_required && <span className="required">*</span>}
            </label>
            {question.help_text && (
              <div className="question-help">{question.help_text}</div>
            )}
            <QuestionField
              question={question}
              value={answers[question.id]}
              onChange={handleAnswerChange}
            />
            {validationErrors[question.id] && (
              <div className="question-error">{validationErrors[question.id]}</div>
            )}
          </div>
        ))}
      </div>

      <div className="ob-card-footer">
        {!(isFirstStep && isFirstGroup) ? (
          <button className="btn-secondary-custom" onClick={handlePrevGroup}>
            &#8592; Back
          </button>
        ) : <div />}

        {isLastGroup ? (
          <button className="btn-primary-custom" onClick={handleSubmitAll} disabled={loading}>
            {loading ? 'Saving...' : 'Save & Continue \u2192'}
          </button>
        ) : (
          <button className="btn-primary-custom" onClick={handleNextGroup} disabled={loading}>
            {loading ? 'Saving...' : 'Next \u2192'}
          </button>
        )}
      </div>
    </div>
  );
}

export default QuestionsStep;
