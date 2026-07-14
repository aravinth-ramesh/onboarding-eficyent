import React, { useEffect } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { fetchQuestions } from '../../store/slices/onboardingSlice';
import TableAnswerView from './TableAnswerView';
import { formatMcc, formatAddress, formatUbo } from '../../utils/answerFormat';
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

  const formatAnswer = (question, value) => {
    if (!value) return '\u2014';

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

    if (question.type === 'mcc') {
      return formatMcc(value);
    }

    if (question.type === 'address') {
      return formatAddress(value);
    }

    if (question.type === 'ubo') {
      return formatUbo(value);
    }

    if (question.type === 'table') {
      return <TableAnswerView question={question} value={value} />;
    }

    return value;
  };

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
