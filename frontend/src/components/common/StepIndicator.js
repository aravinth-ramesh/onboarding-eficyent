import React, { useRef, useEffect } from 'react';

function StepIndicator({ steps, currentStepId }) {
  const activeRef = useRef(null);

  // Keep the active step in view when the flow has many steps (KYB).
  useEffect(() => {
    if (activeRef.current) {
      activeRef.current.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
    }
  }, [currentStepId]);

  return (
    <div className="step-indicator">
      {steps.map((step, index) => {
        const isCurrent = step.id === currentStepId;
        const isCompleted = step.status === 'completed';

        return (
          <div key={step.id} className="step-item" ref={isCurrent ? activeRef : undefined}>
            <div className="step-item-content">
              <div
                className={`step-circle ${isCompleted ? 'completed' : ''} ${isCurrent ? 'active' : ''}`}
              >
                {isCompleted ? '✓' : index + 1}
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
