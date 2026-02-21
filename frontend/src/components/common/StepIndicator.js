import React from 'react';

function StepIndicator({ steps, currentStepId }) {
  return (
    <div className="d-flex justify-content-center mb-4">
      {steps.map((step, index) => {
        const isCurrent = step.id === currentStepId;
        const isCompleted = step.status === 'completed';

        return (
          <div key={step.id} className="d-flex align-items-center">
            <div className="text-center">
              <div
                className={`rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1 ${
                  isCompleted
                    ? 'bg-success text-white'
                    : isCurrent
                    ? 'bg-primary text-white'
                    : 'bg-light text-muted border'
                }`}
                style={{ width: 36, height: 36, fontSize: 14 }}
              >
                {isCompleted ? '\u2713' : index + 1}
              </div>
              <small
                className={`d-block ${
                  isCurrent ? 'fw-bold text-primary' : 'text-muted'
                }`}
                style={{ fontSize: 11, maxWidth: 80 }}
              >
                {step.name}
              </small>
            </div>
            {index < steps.length - 1 && (
              <div
                className={`mx-2 ${isCompleted ? 'bg-success' : 'bg-secondary'}`}
                style={{ height: 2, width: 40, marginTop: -16 }}
              />
            )}
          </div>
        );
      })}
    </div>
  );
}

export default StepIndicator;
