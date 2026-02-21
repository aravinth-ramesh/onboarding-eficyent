import React, { useEffect, useState } from 'react';
import { Card, Button, Spinner, Alert, Table } from 'react-bootstrap';
import { useDispatch, useSelector } from 'react-redux';
import {
  fetchQuestions,
  completeOnboardingStep,
  fetchOnboardingStatus,
} from '../../store/slices/onboardingSlice';

function ReviewStep({ step }) {
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
    if (!value) return '-';

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
      <div className="text-center py-4">
        <Spinner animation="border" />
      </div>
    );
  }

  if (submitted) {
    return (
      <Card>
        <Card.Body className="text-center py-5">
          <h3 className="text-success mb-3">Onboarding Complete!</h3>
          <p className="text-muted">
            Your application has been submitted successfully. We will review your
            information and get back to you shortly.
          </p>
        </Card.Body>
      </Card>
    );
  }

  return (
    <Card>
      <Card.Header>
        <h5 className="mb-0">Review Your Information</h5>
      </Card.Header>
      <Card.Body>
        <Alert variant="info">
          Please review all your answers below before submitting. You can go back to
          previous steps to make changes if needed.
        </Alert>

        {questionGroups.map((group) => (
          <div key={group.id} className="mb-4">
            <h6 className="border-bottom pb-2">{group.name}</h6>
            <Table striped bordered size="sm">
              <tbody>
                {group.questions.map((question) => (
                  <tr key={question.id}>
                    <td className="fw-bold" style={{ width: '40%' }}>
                      {question.label}
                    </td>
                    <td>{formatAnswer(question, answers[question.id])}</td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </div>
        ))}

        <div className="mt-4 d-flex justify-content-end">
          <Button variant="success" size="lg" onClick={handleSubmit} disabled={submitting}>
            {submitting ? <Spinner size="sm" /> : 'Submit Application'}
          </Button>
        </div>
      </Card.Body>
    </Card>
  );
}

export default ReviewStep;
