import React from 'react';

// The KYB flow runs to 11 steps, which never fit across the page — rendering
// them all needed a horizontal scrollbar of its own. Instead, show a short
// window around the current step and let the count and progress bar carry the
// overall position.
const WINDOW = 3;

function StepIndicator({ steps, currentStepId }) {
  const total = steps.length;
  if (total === 0) return null;

  const found = steps.findIndex((step) => step.id === currentStepId);
  const currentIndex = found === -1 ? 0 : found;
  const completed = steps.filter((step) => step.status === 'completed').length;
  const percent = Math.round((completed / total) * 100);

  // Slide the window so the current step sits in the middle where it can, and
  // clamps at either end of the flow.
  const start = Math.min(Math.max(currentIndex - 1, 0), Math.max(total - WINDOW, 0));
  const visible = steps.slice(start, start + WINDOW);
  const hiddenBefore = start;
  const hiddenAfter = total - (start + visible.length);

  return (
    <div className="step-indicator">
      <div className="step-indicator-head">
        <span className="step-indicator-count">
          Step {currentIndex + 1} of {total}
        </span>
        <span className="step-indicator-percent">{percent}% complete</span>
      </div>

      <div
        className="step-progress"
        role="progressbar"
        aria-valuenow={percent}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-label="Onboarding progress"
      >
        <div className="step-progress-fill" style={{ width: `${percent}%` }} />
      </div>

      <ol className="step-window">
        {hiddenBefore > 0 && (
          <li className="step-ellipsis" aria-hidden="true">
            +{hiddenBefore}
          </li>
        )}
        {visible.map((step, offset) => {
          const index = start + offset;
          const isCurrent = index === currentIndex;
          const isCompleted = step.status === 'completed';

          return (
            <li
              key={step.id}
              className={`step-chip ${isCompleted ? 'is-done' : ''} ${isCurrent ? 'is-current' : ''}`}
              aria-current={isCurrent ? 'step' : undefined}
            >
              <span className="step-chip-circle" aria-hidden="true">
                {isCompleted ? '✓' : index + 1}
              </span>
              <span className="step-chip-label">{step.name}</span>
            </li>
          );
        })}
        {hiddenAfter > 0 && (
          <li className="step-ellipsis" aria-hidden="true">
            +{hiddenAfter}
          </li>
        )}
      </ol>
    </div>
  );
}

export default StepIndicator;
