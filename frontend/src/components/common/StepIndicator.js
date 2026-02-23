import React from 'react';

function StepIndicator({ steps, currentStepId }) {
  return (
    <div className="step-indicator">
      {steps.map((step, index) => {
        const isCurrent = step.id === currentStepId;
        const isCompleted = step.status === 'completed';

        return (
          <div key={step.id} className="step-item">
            <div className="step-item-content">
              <div
                className={`step-circle ${isCompleted ? 'completed' : ''} ${isCurrent ? 'active' : ''}`}
              >
                {isCompleted ? '\u2713' : index + 1}
              </div>
              <span
                className={`step-label ${isCompleted ? 'completed' : ''} ${isCurrent ? 'active' : ''}`}
              >
                {step.name}
              </span>
            </div>
            {index < steps.length - 1 && (
              <div className={`step-connector ${isCompleted ? 'completed' : ''}`} />
            )}
          </div>
        );
      })}
    </div>
  );
}

export default StepIndicator;
