import React, { useEffect } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { fetchQuestions } from '../../store/slices/onboardingSlice';
import TableAnswerView from './TableAnswerView';
import formatAnswerDisplay from './formatAnswerDisplay';
import { downloadApplicationPdf } from '../../api/onboarding';

/**
 * Read-only view of submitted answers.
 * Shown after onboarding is completed.
 */
function SubmittedAnswersView({ onBack }) {
  const dispatch = useDispatch();
  const { questionGroups, answers, loading } = useSelector((state) => state.onboarding);

  useEffect(() => {
    if (questionGroups.length === 0) {
      dispatch(fetchQuestions());
    }
  }, [dispatch, questionGroups.length]);

  const formatAnswer = formatAnswerDisplay;

  if (loading && questionGroups.length === 0) {
    return (
      <div className="spinner-corporate">
        <div className="spinner-border" role="status" />
        <p>Loading your answers...</p>
      </div>
    );
  }

  return (
    <div className="ob-card">
      <div className="ob-card-header">
        <h5>Submitted Answers</h5>
        <div style={{ display: 'flex', gap: 8 }}>
          <button
            className="btn-secondary-custom"
            onClick={() => downloadApplicationPdf().catch(() => {})}
          >
            &#8595; Download PDF
          </button>
          <button className="btn-secondary-custom" onClick={onBack}>
            &#8592; Back
          </button>
        </div>
      </div>
      <div className="ob-card-body">
        <div className="alert-corporate success" style={{ marginBottom: 20 }}>
          Your application has been submitted. Below is a read-only summary of your responses.
        </div>

        {questionGroups.map((group) => {
          const answeredQuestions = group.questions.filter(
            (q) => answers[q.id] !== undefined && answers[q.id] !== null && answers[q.id] !== ''
          );
          if (answeredQuestions.length === 0) return null;

          return (
            <div key={group.id} style={{ marginBottom: 24 }}>
              <p className="section-label">{group.name}</p>
              <table className="review-table">
                <tbody>
                  {answeredQuestions.map((question) => {
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
    </div>
  );
}

export default SubmittedAnswersView;
