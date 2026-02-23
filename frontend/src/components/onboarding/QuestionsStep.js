import React, { useEffect, useState } from 'react';
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
  const [validationErrors, setValidationErrors] = useState({});
  const [submitError, setSubmitError] = useState(null);
  const [expandedGroups, setExpandedGroups] = useState({});

  useEffect(() => {
    dispatch(fetchQuestions());
  }, [dispatch]);

  // Auto-expand first group
  useEffect(() => {
    if (questionGroups.length > 0 && Object.keys(expandedGroups).length === 0) {
      setExpandedGroups({ [questionGroups[0].id]: true });
    }
  }, [questionGroups, expandedGroups]);

  const toggleGroup = (groupId) => {
    setExpandedGroups((prev) => ({ ...prev, [groupId]: !prev[groupId] }));
  };

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

  const isQuestionVisible = (question) => {
    if (!question.conditional_rules || question.conditional_rules.length === 0) {
      return true;
    }
    return evaluateConditionalRules(question.conditional_rules, answers);
  };

  const validate = () => {
    const errors = {};
    questionGroups.forEach((group) => {
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

  const handleContinue = async () => {
    const errors = validate();
    if (Object.keys(errors).length > 0) {
      setValidationErrors(errors);
      // Expand groups with errors
      const groupsWithErrors = {};
      questionGroups.forEach((group) => {
        group.questions.forEach((q) => {
          if (errors[q.id]) groupsWithErrors[group.id] = true;
        });
      });
      setExpandedGroups((prev) => ({ ...prev, ...groupsWithErrors }));
      return;
    }

    const saved = await handleSave();
    if (saved) {
      await dispatch(completeOnboardingStep(step.id));
      dispatch(fetchOnboardingStatus());
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
        <h5>Onboarding Questions</h5>
        <button className="btn-outline-custom" onClick={handleSave} disabled={loading}>
          {loading ? 'Saving...' : 'Save Draft'}
        </button>
      </div>
      <div className="ob-card-body">
        {submitError && (
          <div className="alert-corporate danger" style={{ marginBottom: 16 }}>{submitError}</div>
        )}

        {questionGroups.map((group) => {
          const visibleQuestions = group.questions.filter(isQuestionVisible);
          if (visibleQuestions.length === 0) return null;

          const isExpanded = expandedGroups[group.id] !== false;
          const hasErrors = visibleQuestions.some((q) => validationErrors[q.id]);

          return (
            <div key={group.id} className="question-group">
              <div
                className="question-group-header"
                onClick={() => toggleGroup(group.id)}
              >
                <span>
                  {group.name}
                  {hasErrors && <span style={{ color: 'var(--color-danger)', marginLeft: 8, fontSize: '0.75rem' }}>Has errors</span>}
                </span>
                <span style={{ fontSize: '0.75rem', color: 'var(--color-text-muted)' }}>
                  {isExpanded ? '\u25B2' : '\u25BC'}
                </span>
              </div>
              {isExpanded && (
                <div className="question-group-body">
                  {group.description && (
                    <p style={{ color: 'var(--color-text-muted)', fontSize: '0.8rem', marginBottom: 16 }}>
                      {group.description}
                    </p>
                  )}
                  {visibleQuestions.map((question) => (
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
              )}
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
        <button className="btn-primary-custom" onClick={handleContinue} disabled={loading}>
          {loading ? 'Saving...' : 'Save & Continue \u2192'}
        </button>
      </div>
    </div>
  );
}

export default QuestionsStep;
