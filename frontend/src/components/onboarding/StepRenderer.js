import React from 'react';
import SelectTypeStep from './SelectTypeStep';
import RegistrationStep from './RegistrationStep';
import QuestionsStep from './QuestionsStep';
import KycStep from './KycStep';
import ReviewStep from './ReviewStep';

/**
 * Dynamically renders the correct component based on the step's component_key.
 * To add a new step type, just register it in the STEP_COMPONENTS map.
 */
const STEP_COMPONENTS = {
  select_type: SelectTypeStep,
  registration: RegistrationStep,
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

  // Key by the step so React remounts (not reuses) the component when the step
  // changes. Consecutive steps share a type (several `questions` section steps
  // in a row), and without a distinct key React keeps one instance — leaking
  // internal state like the active question-group index. A single-group step
  // that follows a multi-group step then points past its groups and renders
  // blank until a full refresh remounts it. The key makes every step start fresh.
  return <Component key={step.id ?? step.component_key} step={step} onBack={onBack} isFirstStep={isFirstStep} />;
}

export default StepRenderer;
