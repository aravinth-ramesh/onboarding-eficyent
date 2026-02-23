import React from 'react';
import SelectTypeStep from './SelectTypeStep';
import QuestionsStep from './QuestionsStep';
import KycStep from './KycStep';
import ReviewStep from './ReviewStep';

/**
 * Dynamically renders the correct component based on the step's component_key.
 * To add a new step type, just register it in the STEP_COMPONENTS map.
 */
const STEP_COMPONENTS = {
  select_type: SelectTypeStep,
  questions: QuestionsStep,
  kyc: KycStep,
  review: ReviewStep,
};

function StepRenderer({ step, onBack, isFirstStep }) {
  const Component = STEP_COMPONENTS[step.component_key];

  if (!Component) {
    return (
      <div className="alert-corporate danger">
        Unknown step type: <code>{step.component_key}</code>
      </div>
    );
  }

  return <Component step={step} onBack={onBack} isFirstStep={isFirstStep} />;
}

export default StepRenderer;
