import React, { useEffect, useState } from 'react';
import { Card, Form, Button, Alert, Spinner, Accordion } from 'react-bootstrap';
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

function QuestionsStep({ step }) {
  const dispatch = useDispatch();
  const { questionGroups, answers, loading } = useSelector((state) => state.onboarding);
  const [validationErrors, setValidationErrors] = useState({});
  const [submitError, setSubmitError] = useState(null);

  useEffect(() => {
    dispatch(fetchQuestions());
  }, [dispatch]);

  const handleAnswerChange = (questionId, value) => {
    dispatch(setAnswer({ questionId, value }));
    // Clear validation error on change
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
    if (!result.error) {
      return true;
    }
    setSubmitError('Failed to save answers.');
    return false;
  };

  const handleContinue = async () => {
    const errors = validate();
    if (Object.keys(errors).length > 0) {
      setValidationErrors(errors);
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
      <div className="text-center py-4">
        <Spinner animation="border" />
      </div>
    );
  }

  return (
    <Card>
      <Card.Header className="d-flex justify-content-between align-items-center">
        <h5 className="mb-0">Onboarding Questions</h5>
        <Button variant="outline-secondary" size="sm" onClick={handleSave} disabled={loading}>
          {loading ? <Spinner size="sm" /> : 'Save Draft'}
        </Button>
      </Card.Header>
      <Card.Body>
        {submitError && <Alert variant="danger">{submitError}</Alert>}

        <Accordion defaultActiveKey={questionGroups[0]?.id?.toString()} alwaysOpen>
          {questionGroups.map((group) => {
            const visibleQuestions = group.questions.filter(isQuestionVisible);
            if (visibleQuestions.length === 0) return null;

            return (
              <Accordion.Item eventKey={group.id.toString()} key={group.id}>
                <Accordion.Header>{group.name}</Accordion.Header>
                <Accordion.Body>
                  {group.description && (
                    <p className="text-muted mb-3">{group.description}</p>
                  )}
                  {visibleQuestions.map((question) => (
                    <Form.Group key={question.id} className="mb-3">
                      <Form.Label>
                        {question.label}
                        {question.is_required && <span className="text-danger"> *</span>}
                      </Form.Label>
                      {question.help_text && (
                        <Form.Text className="d-block text-muted mb-1">
                          {question.help_text}
                        </Form.Text>
                      )}
                      <QuestionField
                        question={question}
                        value={answers[question.id]}
                        onChange={handleAnswerChange}
                      />
                      {validationErrors[question.id] && (
                        <Form.Text className="text-danger">
                          {validationErrors[question.id]}
                        </Form.Text>
                      )}
                    </Form.Group>
                  ))}
                </Accordion.Body>
              </Accordion.Item>
            );
          })}
        </Accordion>

        <div className="mt-4 d-flex justify-content-end">
          <Button variant="primary" onClick={handleContinue} disabled={loading}>
            {loading ? <Spinner size="sm" /> : 'Save & Continue'}
          </Button>
        </div>
      </Card.Body>
    </Card>
  );
}

export default QuestionsStep;
