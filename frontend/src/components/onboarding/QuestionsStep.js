import React, { useEffect, useState, useMemo, useRef, useCallback } from 'react';
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
  const [tableCellErrors, setTableCellErrors] = useState({});
  const [submitError, setSubmitError] = useState(null);

  // File answers stored outside Redux (File objects are not serializable)
  const fileAnswersRef = useRef({});

  useEffect(() => {
    dispatch(fetchQuestions());
  }, [dispatch]);

  const isQuestionVisible = (question) => {
    if (!question.conditional_rules || question.conditional_rules.length === 0) {
      return true;
    }
    return evaluateConditionalRules(question.conditional_rules, answers);
  };

  // Reorder a group's questions so any conditional child is emitted directly
  // after the parent referenced by its rule (when the parent lives in the
  // same group). Children whose parent lives elsewhere keep their natural
  // backend order.
  const reorderQuestionsWithChildren = (questions) => {
    if (!Array.isArray(questions) || questions.length === 0) return [];

    const byId = new Map(questions.map((q) => [q.id, q]));
    const childrenByParent = new Map();
    const anchored = new Set();

    questions.forEach((q) => {
      const rule = q.conditional_rules && q.conditional_rules[0];
      if (rule && byId.has(rule.parent_question_id)) {
        const parentId = rule.parent_question_id;
        if (!childrenByParent.has(parentId)) childrenByParent.set(parentId, []);
        childrenByParent.get(parentId).push(q);
        anchored.add(q.id);
      }
    });

    const result = [];
    const seen = new Set();

    const emit = (q) => {
      if (seen.has(q.id)) return;
      seen.add(q.id);
      result.push(q);
      const kids = childrenByParent.get(q.id);
      if (kids && kids.length) {
        const sorted = [...kids].sort((a, b) => (a.order || 0) - (b.order || 0));
        sorted.forEach(emit);
      }
    };

    questions.forEach((q) => {
      if (anchored.has(q.id)) return;
      emit(q);
    });

    return result;
  };

  // Filter out groups that have no visible questions
  const visibleGroups = useMemo(() => {
    return questionGroups.filter((group) =>
      group.questions.some((q) => isQuestionVisible(q))
    );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [questionGroups, answers]);

  const activeGroup = visibleGroups[activeGroupIndex];
  const activeQuestions = useMemo(() => {
    if (!activeGroup) return [];
    return reorderQuestionsWithChildren(activeGroup.questions).filter(isQuestionVisible);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeGroup, answers]);
  const isLastGroup = activeGroupIndex === visibleGroups.length - 1;
  const isFirstGroup = activeGroupIndex === 0;

  // Build a set of file-type question IDs for quick lookup
  const fileQuestionIds = useMemo(() => {
    const ids = new Set();
    questionGroups.forEach((group) => {
      group.questions.forEach((q) => {
        if (q.type === 'file') ids.add(q.id);
      });
    });
    return ids;
  }, [questionGroups]);

  const handleAnswerChange = useCallback((questionId, value) => {
    if (fileQuestionIds.has(questionId)) {
      // Store File objects in ref (not Redux)
      fileAnswersRef.current[questionId] = value;
      // Marker dispatched to Redux must change whenever the selection
      // changes, otherwise Immer/useSelector skip the update and the
      // re-render that surfaces the new ref value never happens. Encode
      // the file count + each file's name/size so add, remove, and
      // replace all yield a different marker string.
      let marker = '';
      if (Array.isArray(value) && value.length > 0) {
        const sig = value.map((f) => `${f?.name ?? ''}|${f?.size ?? ''}`).join(',');
        marker = `__files__:${value.length}:${sig}`;
      }
      dispatch(setAnswer({ questionId, value: marker }));
    } else {
      dispatch(setAnswer({ questionId, value }));
    }
    if (validationErrors[questionId]) {
      setValidationErrors((prev) => {
        const next = { ...prev };
        delete next[questionId];
        return next;
      });
    }
    setTableCellErrors((prev) => {
      if (!prev[questionId]) return prev;
      const next = { ...prev };
      delete next[questionId];
      return next;
    });
  }, [dispatch, fileQuestionIds, validationErrors]);

  const isCellFilled = (v) => {
    if (v === null || v === undefined || v === '') return false;
    if (Array.isArray(v) && v.length === 0) return false;
    return true;
  };

  const getTableRows = (val) => {
    let rows = val;
    if (typeof rows === 'string') {
      try { rows = JSON.parse(rows); } catch { rows = []; }
    }
    return Array.isArray(rows) ? rows : [];
  };

  // Returns { message, cells } when invalid, otherwise null.
  const validateTableQuestion = (question) => {
    const columns = (question.options && question.options.columns) || [];
    const rows = getTableRows(answers[question.id]);
    const requiredColumns = columns.filter((c) => c.required);
    const isRowEmpty = (row) => !columns.some((col) => isCellFilled(row?.[col.key]));
    const filledRows = rows.filter((r) => !isRowEmpty(r));
    // Treat the table as required when EITHER the question itself is
    // marked required OR any column is required. Otherwise a conditional
    // table with required columns but an unticked "Required" checkbox
    // lets users advance past an empty table without filling anything.
    const mustBeFilled = question.is_required || requiredColumns.length > 0;
    const requiredAndEmpty = mustBeFilled && filledRows.length === 0;

    const cells = {};

    // Ensure the first row's required cells are flagged when the question is
    // required but the user hasn't filled anything yet (rows may not even exist
    // in the answer store until the first edit).
    if (requiredAndEmpty) {
      requiredColumns.forEach((col) => {
        cells[`0_${col.key}`] = true;
      });
    }

    rows.forEach((row, rowIndex) => {
      // Skip wholly empty trailing rows when not required; always validate the first row.
      if (isRowEmpty(row) && rowIndex !== 0) return;
      requiredColumns.forEach((col) => {
        if (!isCellFilled(row?.[col.key])) {
          cells[`${rowIndex}_${col.key}`] = true;
        }
      });
    });

    if (requiredAndEmpty) {
      return { message: 'This field is required.', cells };
    }
    if (Object.keys(cells).length > 0) {
      return { message: 'Please fill all required fields in the table.', cells };
    }
    return null;
  };

  const isAnswerEmpty = (question) => {
    // For file questions, check if new files selected or existing server files present
    if (question.type === 'file') {
      const newFiles = fileAnswersRef.current[question.id];
      if (Array.isArray(newFiles) && newFiles.length > 0) return false;
      if (question.files && question.files.length > 0) return false;
      // After a successful save the ref is cleared and Redux's
      // state.questionGroups isn't refreshed (we only refetch on Back),
      // so question.files stays stale even though the file is actually
      // saved server-side. Trust the marker we kept in state.answers as
      // a non-empty signal so validation across pages doesn't bounce
      // the user back to a page whose file already uploaded.
      const stored = answers[question.id];
      if (typeof stored === 'string' && stored.startsWith('__files__')) return false;
      return true;
    }
    const val = answers[question.id];
    return val === undefined || val === null || val === '' || (Array.isArray(val) && val.length === 0);
  };

  const collectErrors = (questions) => {
    const errors = {};
    const cellErrors = {};
    questions.forEach((question) => {
      if (question.type === 'table') {
        const result = validateTableQuestion(question);
        if (result) {
          errors[question.id] = result.message;
          if (Object.keys(result.cells).length > 0) {
            cellErrors[question.id] = result.cells;
          }
        }
        return;
      }
      if (question.is_required && isAnswerEmpty(question)) {
        errors[question.id] = 'This field is required.';
      }
    });
    return { errors, cellErrors };
  };

  const validateCurrentGroup = () => collectErrors(activeQuestions);

  const validateAllGroups = () => {
    const allQuestions = [];
    visibleGroups.forEach((group) => {
      group.questions.forEach((question) => {
        if (isQuestionVisible(question)) allQuestions.push(question);
      });
    });
    return collectErrors(allQuestions);
  };

  // Map of table-question id -> question (for column-type lookups during save).
  const tableQuestionMap = useMemo(() => {
    const map = {};
    questionGroups.forEach((g) =>
      g.questions.forEach((q) => {
        if (q.type === 'table') map[q.id] = q;
      })
    );
    return map;
  }, [questionGroups]);

  const handleSave = async () => {
    setSubmitError(null);

    const tableFilePayload = [];

    // Separate non-file answers from file answers, extracting any File objects
    // that were dropped into table cells so they can be uploaded as multipart.
    const answersPayload = Object.entries(answers)
      .filter(([questionId]) => !fileQuestionIds.has(parseInt(questionId)))
      .map(([questionId, value]) => {
        const qid = parseInt(questionId);
        const question = tableQuestionMap[qid];
        if (!question) {
          return { question_id: qid, value };
        }

        let rows = value;
        if (typeof rows === 'string') {
          try { rows = JSON.parse(rows); } catch { rows = []; }
        }
        if (!Array.isArray(rows)) {
          return { question_id: qid, value };
        }

        const fileColumnKeys = ((question.options && question.options.columns) || [])
          .filter((c) => c.type === 'file')
          .map((c) => c.key);

        if (fileColumnKeys.length === 0) {
          return { question_id: qid, value: rows };
        }

        const cleanedRows = rows.map((row, rowIndex) => {
          const cleaned = { ...(row || {}) };
          fileColumnKeys.forEach((columnKey) => {
            const cellValue = cleaned[columnKey];
            if (cellValue instanceof File) {
              tableFilePayload.push({ questionId: qid, rowIndex, columnKey, file: cellValue });
              cleaned[columnKey] = '';
            }
          });
          return cleaned;
        });

        return { question_id: qid, value: cleanedRows };
      });

    // Collect file answers (only those with actual File objects)
    const filePayload = {};
    Object.entries(fileAnswersRef.current).forEach(([questionId, files]) => {
      if (Array.isArray(files) && files.length > 0) {
        filePayload[questionId] = files;
      }
    });

    const result = await dispatch(submitAnswers({
      answers: answersPayload,
      fileAnswers: filePayload,
      tableFileAnswers: tableFilePayload,
    }));
    if (!result.error) {
      // Clear file ref after successful upload
      fileAnswersRef.current = {};
      return true;
    }
    setSubmitError('Failed to save answers. Please try again.');
    return false;
  };

  const handleNextGroup = async () => {
    const { errors, cellErrors } = validateCurrentGroup();
    if (Object.keys(errors).length > 0) {
      setValidationErrors(errors);
      setTableCellErrors(cellErrors);
      return;
    }
    setTableCellErrors({});

    // Auto-save on group navigation
    await handleSave();
    setActiveGroupIndex((prev) => prev + 1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const handlePrevGroup = async () => {
    if (isFirstGroup) {
      onBack();
      return;
    }
    setTableCellErrors({});
    setValidationErrors({});
    // 1) Persist any pending edits in the current group so they aren't
    //    dropped on refresh. We do this even if the save is empty/invalid;
    //    the user explicitly asked to navigate back, so don't block them.
    await handleSave();
    setSubmitError(null);
    // 2) Refresh from the backend BEFORE switching groups so the previous
    //    group renders with up-to-date answers and uploaded files (signed
    //    URLs, question.files, etc.) instead of briefly showing stale
    //    state while the network request resolves.
    await dispatch(fetchQuestions());
    setActiveGroupIndex((prev) => Math.max(prev - 1, 0));
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const handleSubmitAll = async () => {
    // Validate current group first
    const { errors: currentErrors, cellErrors: currentCellErrors } = validateCurrentGroup();
    if (Object.keys(currentErrors).length > 0) {
      setValidationErrors(currentErrors);
      setTableCellErrors(currentCellErrors);
      return;
    }

    // Validate all groups
    const { errors: allErrors, cellErrors: allCellErrors } = validateAllGroups();
    if (Object.keys(allErrors).length > 0) {
      // Find the first group with errors and navigate to it
      for (let i = 0; i < visibleGroups.length; i++) {
        const groupHasError = visibleGroups[i].questions.some(
          (q) => isQuestionVisible(q) && allErrors[q.id]
        );
        if (groupHasError) {
          setActiveGroupIndex(i);
          setValidationErrors(allErrors);
          setTableCellErrors(allCellErrors);
          return;
        }
      }
    }
    setTableCellErrors({});

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
              value={
                // File questions store File objects outside Redux (in
                // fileAnswersRef) and only put a '__files__' marker into
                // state. Surface the actual array so FileUploadField can
                // preview the selection before submit.
                question.type === 'file'
                  ? (fileAnswersRef.current[question.id] || [])
                  : answers[question.id]
              }
              onChange={handleAnswerChange}
              cellErrors={tableCellErrors[question.id]}
            />
            {validationErrors[question.id] && (
              <div className="question-error">{validationErrors[question.id]}</div>
            )}
          </div>
        ))}
      </div>

      <div className="ob-card-footer">
        {!(isFirstStep && isFirstGroup) ? (
          <button
            className="btn-secondary-custom"
            onClick={handlePrevGroup}
            disabled={loading}
          >
            {loading ? 'Loading...' : '← Back'}
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
